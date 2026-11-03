<?php
/**
 * PaymentHandler — оплата Т‑Банк (физлица) и выставление счёта (юрлица).
 *
 * Настройки MODX (Системные настройки):
 *   pay_tbank_terminal   — TerminalKey
 *   pay_tbank_password   — пароль терминала
 *   pay_price_year         — цена за 1 год для физлиц, ₽ (по умолчанию 500)
 *   pay_price_year_company — цена за 1 год для юрлиц, ₽ (по умолчанию 5000)
 *   pay_script_path      — путь к серверному скрипту после оплаты
 *   pay_admin_key        — ADMIN_KEY API календаря (выдача токена)
 *   pay_token_api        — базовый URL API календаря (по умолчанию http://176.126.103.96:3000)
 *   pay_success_url      — URL возврата после оплаты (страница оплаты)
 *   pay_fail_url         — URL при ошибке оплаты
 *   pay_notify_url       — URL вебхука NotificationURL
 *   pay_dadata_token     — API-ключ DaData (организация по ИНН)
 *
 * Вызов как сниппет с connector или через resource с &action:
 *   [[!PaymentHandler]]
 * Или AJAX POST на страницу/connector с action=pay_init|pay_notify|pay_result|invoice|find_party
 */

$modx->lexicon->load('core:default');

$action = $_REQUEST['action'] ?? $modx->getOption('action', $scriptProperties, '');

// Вебхук Т‑Банк без action: JSON с OrderId + Status + Token
if ($action === '') {
    $rawNotify = file_get_contents('php://input');
    $notifyBody = json_decode($rawNotify ?: '[]', true);
    if (is_array($notifyBody) && !empty($notifyBody['OrderId']) && isset($notifyBody['Status'], $notifyBody['Token'])) {
        $action = 'pay_notify';
        // прокинем тело в payNotify через php://input (уже прочитан) — сохраним во временную переменную
        $GLOBALS['_pay_notify_raw'] = $notifyBody;
    }
}

if ($action === '') {
    return '';
}

$priceYear = (int)$modx->getOption('pay_price_year', null, 500);
$priceYearCompany = (int)$modx->getOption('pay_price_year_company', null, 5000);
$terminal  = (string)$modx->getOption('pay_tbank_terminal', null, '');
$password  = (string)$modx->getOption('pay_tbank_password', null, '');
$scriptPath = (string)$modx->getOption('pay_script_path', null, '');
$adminKey = (string)$modx->getOption('pay_admin_key', null, '');
$tokenApi = (string)$modx->getOption('pay_token_api', null, 'https://176.126.103.96:3000');
if ($tokenApi !== '' && str_starts_with($tokenApi, 'http://')) {
    $tokenApi = 'https://' . substr($tokenApi, 7);
}
$baseUrl = rtrim($modx->getOption('site_url'), '/');
$successUrl = (string)$modx->getOption('pay_success_url', null, $baseUrl . '/oplata/');
$failUrl = (string)$modx->getOption('pay_fail_url', null, $successUrl . '?pay=fail');
$notifyUrl = (string)$modx->getOption('pay_notify_url', null, $baseUrl . '/ajaxress');

$ordersDir = $modx->getOption('base_path') . 'assets/pay/orders/';
$invoicesDir = $modx->getOption('base_path') . 'assets/pay/invoices/';
foreach ([$ordersDir, $invoicesDir] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'pay_init':
        echo json_encode(payInit($modx, [
            'terminal' => $terminal,
            'password' => $password,
            'priceYear' => $priceYear,
            'ordersDir' => $ordersDir,
            'successUrl' => $successUrl,
            'failUrl' => $failUrl,
            'notifyUrl' => $notifyUrl,
        ], $_POST), JSON_UNESCAPED_UNICODE);
        exit;

    case 'pay_notify':
        // T-Bank webhook — ответ "OK" текстом
        header('Content-Type: text/plain; charset=utf-8');
        $notifyPayload = $GLOBALS['_pay_notify_raw'] ?? (file_get_contents('php://input') ?: $_POST);
        echo payNotify($modx, [
            'password' => $password,
            'ordersDir' => $ordersDir,
            'scriptPath' => $scriptPath,
            'adminKey' => $adminKey,
            'tokenApi' => $tokenApi,
        ], $notifyPayload);
        exit;

    case 'pay_result':
        echo json_encode(payResult($ordersDir, $_GET['order'] ?? $_POST['order'] ?? ''), JSON_UNESCAPED_UNICODE);
        exit;

    case 'invoice':
        echo json_encode(payInvoice($modx, [
            'priceYear' => $priceYearCompany,
            'invoicesDir' => $invoicesDir,
            'baseUrl' => $baseUrl,
        ], $_POST), JSON_UNESCAPED_UNICODE);
        exit;

    case 'find_party':
        echo json_encode(payFindParty($modx, $_POST['inn'] ?? $_GET['inn'] ?? ''), JSON_UNESCAPED_UNICODE);
        exit;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
        exit;
}

// ─── helpers ───────────────────────────────────────────────

function payJsonResponse($data) {
    return $data;
}

function payValidateTarget($target) {
    $target = trim((string)$target);
    if ($target === '' || strlen($target) > 253) return false;
    if (filter_var($target, FILTER_VALIDATE_IP)) return $target;
    // домен: латиница/кириллица, точки, дефисы
    if (preg_match('/^(?:[a-zа-яё0-9](?:[a-zа-яё0-9-]{0,61}[a-zа-яё0-9])?\.)+[a-zа-яё]{2,}$/iu', $target)) {
        return mb_strtolower($target, 'UTF-8');
    }
    return false;
}

function payYears($years) {
    $y = (int)$years;
    return ($y === 1 || $y === 2) ? $y : 0;
}

function payAmountRub($priceYear, $years) {
    return (int)$priceYear * (int)$years;
}

function payOrderId() {
    return 'SW' . date('ymdHis') . bin2hex(random_bytes(3));
}

function paySaveOrder($dir, $orderId, array $data) {
    $file = $dir . preg_replace('/[^A-Za-z0-9_-]/', '', $orderId) . '.json';
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    return $file;
}

function payLoadOrder($dir, $orderId) {
    $file = $dir . preg_replace('/[^A-Za-z0-9_-]/', '', $orderId) . '.json';
    if (!is_file($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

/**
 * Подпись Token для API Т‑Банк / Тинькофф
 * @see https://developer.tbank.ru/eacq/intro/preparation/token
 */
function payTbankToken(array $params, $password) {
    $flat = [];
    foreach ($params as $key => $value) {
        if (is_array($value)) continue;
        $flat[$key] = (string)$value;
    }
    $flat['Password'] = (string)$password;
    ksort($flat);
    return hash('sha256', implode('', $flat));
}

function payTbankRequest($method, array $payload) {
    $url = 'https://securepay.tinkoff.ru/v2/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        return ['Success' => false, 'Message' => $err ?: 'curl error', 'http' => $code];
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : ['Success' => false, 'Message' => 'bad json', 'raw' => $raw];
}

function payInit($modx, array $cfg, array $post) {
    if ($cfg['terminal'] === '' || $cfg['password'] === '') {
        return ['ok' => false, 'error' => 'Терминал Т‑Банк не настроен (pay_tbank_terminal / pay_tbank_password)'];
    }

    $target = payValidateTarget($post['target'] ?? '');
    $years = payYears($post['years'] ?? 0);
    $email = filter_var(trim((string)($post['email'] ?? '')), FILTER_VALIDATE_EMAIL);

    if (!$target) return ['ok' => false, 'error' => 'Укажите корректный IP или домен'];
    if (!$years) return ['ok' => false, 'error' => 'Выберите срок: 1 или 2 года'];
    if (!$email) return ['ok' => false, 'error' => 'Укажите корректный e-mail'];

    $amountRub = payAmountRub($cfg['priceYear'], $years);
    $amountKop = $amountRub * 100;
    $orderId = payOrderId();

    $order = [
        'orderId' => $orderId,
        'type' => 'person',
        'target' => $target,
        'years' => $years,
        'email' => $email,
        'amount' => $amountRub,
        'status' => 'new',
        'created' => date('c'),
        'result' => null,
        'paymentId' => null,
    ];
    paySaveOrder($cfg['ordersDir'], $orderId, $order);

    $payload = [
        'TerminalKey' => $cfg['terminal'],
        'Amount' => $amountKop,
        'OrderId' => $orderId,
        'Description' => "Оплата {$years} г. для {$target}",
        'NotificationURL' => $cfg['notifyUrl'],
        'SuccessURL' => $cfg['successUrl'] . (strpos($cfg['successUrl'], '?') === false ? '?' : '&') . 'order=' . urlencode($orderId),
        'FailURL' => $cfg['failUrl'],
        'DATA' => [
            'Email' => $email,
            'target' => $target,
            'years' => (string)$years,
        ],
        'Receipt' => [
            'Email' => $email,
            'Taxation' => 'usn_income',
            'Items' => [[
                'Name' => "Услуга на {$years} " . ($years === 1 ? 'год' : 'года') . ": {$target}",
                'Price' => $amountKop,
                'Quantity' => 1.0,
                'Amount' => $amountKop,
                'Tax' => 'none',
                'PaymentMethod' => 'full_payment',
                'PaymentObject' => 'service',
            ]],
        ],
    ];
    $payload['Token'] = payTbankToken($payload, $cfg['password']);

    $resp = payTbankRequest('Init', $payload);
    if (empty($resp['Success']) || empty($resp['PaymentURL'])) {
        $order['status'] = 'init_error';
        $order['error'] = $resp['Message'] ?? $resp['Details'] ?? 'Init failed';
        paySaveOrder($cfg['ordersDir'], $orderId, $order);
        $modx->log(modX::LOG_LEVEL_ERROR, '[PaymentHandler] Init fail: ' . json_encode($resp, JSON_UNESCAPED_UNICODE));
        return ['ok' => false, 'error' => $order['error'], 'orderId' => $orderId];
    }

    $order['status'] = 'pending';
    $order['paymentId'] = $resp['PaymentId'] ?? null;
    paySaveOrder($cfg['ordersDir'], $orderId, $order);

    return [
        'ok' => true,
        'orderId' => $orderId,
        'paymentUrl' => $resp['PaymentURL'],
        'amount' => $amountRub,
    ];
}

function payNotify($modx, array $cfg, $raw) {
    $data = is_array($raw) ? $raw : (json_decode((string)$raw, true) ?: []);
    if (empty($data['OrderId'])) {
        $modx->log(modX::LOG_LEVEL_WARN, '[PaymentHandler] notify without OrderId');
        return 'OK';
    }

    // проверка Token
    $token = $data['Token'] ?? '';
    $check = $data;
    unset($check['Token']);
    $expected = payTbankToken($check, $cfg['password']);
    if (!hash_equals($expected, (string)$token)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[PaymentHandler] bad notify token for ' . $data['OrderId']);
        return 'OK';
    }

    $orderId = (string)$data['OrderId'];
    $order = payLoadOrder($cfg['ordersDir'], $orderId);
    if (!$order) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[PaymentHandler] order not found: ' . $orderId);
        return 'OK';
    }

    $status = (string)($data['Status'] ?? '');
    $order['tbankStatus'] = $status;
    $order['paymentId'] = $data['PaymentId'] ?? $order['paymentId'];
    $order['notified'] = date('c');

    if (in_array($status, ['CONFIRMED', 'AUTHORIZED'], true)) {
        if ($order['status'] !== 'done') {
            $order['status'] = 'paid';
            $result = payRunScript($cfg['scriptPath'], $order, [
                'PAY_ADMIN_KEY' => (string)($cfg['adminKey'] ?? ''),
                'PAY_TOKEN_API' => (string)($cfg['tokenApi'] ?? ''),
            ]);
            $order['result'] = $result['output'];
            $order['resultCode'] = $result['code'];
            $order['status'] = $result['ok'] ? 'done' : 'script_error';
            $order['finished'] = date('c');
            if ($result['ok'] && empty($order['emailSent'])) {
                $order['emailSent'] = paySendTokenEmail($modx, $order, $result['output']);
            }
        }
        // На успешных статусах T-Bank (CONFIRMED/AUTHORIZED) добавляем покупателя в группу,
        // даже если скрипт/отправка письма для этого заказа уже были выполнены ранее.
        if (empty($order['buyerGroupUpdated'])) {
            $order['buyerGroupUpdated'] = payUpsertBuyerInGroup($modx, $order);
        }
    } elseif (in_array($status, ['REJECTED', 'CANCELED', 'DEADLINE_EXPIRED'], true)) {
        $order['status'] = 'failed';
    }

    paySaveOrder($cfg['ordersDir'], $orderId, $order);
    return 'OK';
}

function payRunScript($scriptPath, array $order, array $env = []) {
    if ($scriptPath === '' || !is_file($scriptPath)) {
        return [
            'ok' => true,
            'code' => 0,
            'output' => "Оплата подтверждена.\nЗаказ: {$order['orderId']}\nЦель: {$order['target']}\nСрок: {$order['years']} г.\n"
                . "(Серверный скрипт не настроен: pay_script_path)",
        ];
    }

    $target = escapeshellarg($order['target']);
    $years = escapeshellarg((string)$order['years']);
    $orderId = escapeshellarg($order['orderId']);
    $email = escapeshellarg($order['email']);

    // pay_admin_key / pay_token_api из настроек MODX → env для issue-token.sh
    $envPrefix = '';
    foreach (['PAY_ADMIN_KEY', 'PAY_TOKEN_API', 'ADMIN_KEY'] as $ek) {
        if (!empty($env[$ek])) {
            $envPrefix .= $ek . '=' . escapeshellarg($env[$ek]) . ' ';
        }
    }

    $cmd = $envPrefix . escapeshellcmd($scriptPath) . " {$target} {$years} {$orderId} {$email} 2>&1";

    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    $text = implode("\n", $output);
    if ($text === '') {
        $text = $code === 0 ? 'Скрипт выполнен успешно.' : "Скрипт завершился с кодом {$code}.";
    }

    return ['ok' => $code === 0, 'code' => $code, 'output' => $text];
}

function payInitUtf8Mail($modx) {
    $modx->getService('mail', 'mail.modPHPMailer');
    $modx->mail->set(modMail::MAIL_CHARSET, 'UTF-8');
    $modx->mail->set(modMail::MAIL_ENCODING, 'base64');
    return $modx->mail;
}

function payExtractTokenPayload(string $scriptOutput): ?array {
    foreach (preg_split('/\R/', $scriptOutput) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] !== '{') {
            continue;
        }
        $data = json_decode($line, true);
        if (is_array($data) && !empty($data['token'])) {
            return $data;
        }
    }
    return null;
}

function paySendTokenEmail($modx, array $order, string $scriptOutput): bool {
    $email = filter_var(trim((string)($order['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        return false;
    }

    $payload = payExtractTokenPayload($scriptOutput);
    if (!$payload) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[PaymentHandler] token email: no token in script output for ' . ($order['orderId'] ?? ''));
        return false;
    }

    $apiToken = (string)$payload['token'];
    $target = (string)($payload['host'] ?? $payload['ip'] ?? $order['target'] ?? '');
    $validFrom = (string)($payload['validFrom'] ?? '');
    $validTo = (string)($payload['validTo'] ?? '');
    $orderId = (string)($order['orderId'] ?? '');
    $years = (int)($order['years'] ?? 1);
    $apiBase = rtrim((string)$modx->getOption('pay_token_api', null, 'https://176.126.103.96:3000'), '/');
    if (str_starts_with($apiBase, 'http://')) {
        $apiBase = 'https://' . substr($apiBase, 7);
    }

    $siteName = (string)$modx->getOption('site_name', null, 'Studio West');
    $subject = 'Токен API производственного календаря — заказ ' . $orderId;
    $orderIdEsc = htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8');
    $targetEsc = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
    $validFromEsc = htmlspecialchars($validFrom, ENT_QUOTES, 'UTF-8');
    $validToEsc = htmlspecialchars($validTo, ENT_QUOTES, 'UTF-8');
    $tokenEsc = htmlspecialchars($apiToken, ENT_QUOTES, 'UTF-8');
    $apiBaseEsc = htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8');
    $siteNameEsc = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $requestUrlEsc = htmlspecialchars($apiBase . '/api/calendar?token=' . $apiToken, ENT_QUOTES, 'UTF-8');
    $logoUrlEsc = 'https://studiowest.ru/assets/teme_sw/logo.svg';

    $body = <<<HTML
<html lang="ru">
<body style="margin:0;padding:0;background-color:#f5f7fb;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f5f7fb;">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:600px;background:#ffffff;border:1px solid #e6e9ef;border-radius:10px;">
          <tr>
            <td align="left" style="padding:20px 24px;border-bottom:1px solid #eef1f6;">
              <img src="{$logoUrlEsc}" alt="{$siteNameEsc}" width="180" style="display:block;border:0;outline:none;text-decoration:none;height:auto;">
            </td>
          </tr>
          <tr>
            <td style="padding:24px;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
              <div style="font-size:20px;line-height:28px;font-weight:700;margin:0 0 12px;">Ваш API-токен готов</div>
              <div style="font-size:14px;line-height:22px;margin:0 0 16px;">Здравствуйте! Оплата получена, по заказу <strong>#{$orderIdEsc}</strong> выпущен токен для <strong>{$targetEsc}</strong>.</div>

              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:0 0 16px;">
                <tr>
                  <td style="padding:8px 0;font-size:13px;line-height:20px;color:#6b7280;width:140px;">Срок:</td>
                  <td style="padding:8px 0;font-size:13px;line-height:20px;color:#111827;"><strong>{$years} г.</strong></td>
                </tr>
                <tr>
                  <td style="padding:8px 0;font-size:13px;line-height:20px;color:#6b7280;">Действует с:</td>
                  <td style="padding:8px 0;font-size:13px;line-height:20px;color:#111827;"><strong>{$validFromEsc}</strong></td>
                </tr>
                <tr>
                  <td style="padding:8px 0;font-size:13px;line-height:20px;color:#6b7280;">Действует до:</td>
                  <td style="padding:8px 0;font-size:13px;line-height:20px;color:#111827;"><strong>{$validToEsc}</strong></td>
                </tr>
                <tr>
                  <td style="padding:8px 0;font-size:13px;line-height:20px;color:#6b7280;">API Base URL:</td>
                  <td style="padding:8px 0;font-size:13px;line-height:20px;color:#111827;">{$apiBaseEsc}</td>
                </tr>
              </table>

              <div style="font-size:13px;line-height:20px;color:#6b7280;margin:0 0 8px;">Токен:</div>
              <div style="font-family:'Courier New',Courier,monospace;font-size:14px;line-height:20px;color:#111827;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;padding:10px 12px;word-break:break-all;margin:0 0 16px;">
                {$tokenEsc}
              </div>

              <div style="font-size:13px;line-height:20px;color:#6b7280;margin:0 0 8px;">Пример запроса:</div>
              <div style="font-family:'Courier New',Courier,monospace;font-size:13px;line-height:20px;color:#111827;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:10px 12px;word-break:break-all;margin:0 0 16px;">
                {$requestUrlEsc}
              </div>

              <div style="font-size:12px;line-height:18px;color:#6b7280;">Не передавайте токен третьим лицам. При подозрении на утечку отзовите токен и выпустите новый.</div>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 24px;border-top:1px solid #eef1f6;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#9ca3af;">
              <div style="margin:0 0 4px;">{$siteNameEsc} · Автоматическое уведомление</div>
              <div style="margin:0;">Если это письмо попало к вам по ошибке, просто проигнорируйте его.</div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    $plainBody = "Ваш API-токен готов\n\n"
        . "Заказ: {$orderId}\n"
        . "Привязка: {$target}\n"
        . "Срок: {$years} " . ($years === 1 ? 'год' : 'года') . "\n"
        . "Действует с: {$validFrom}\n"
        . "Действует до: {$validTo}\n"
        . "API Base URL: {$apiBase}\n\n"
        . "Токен:\n{$apiToken}\n\n"
        . "Пример запроса:\n{$apiBase}/api/calendar?token={$apiToken}\n\n"
        . "Автоматическое уведомление от {$siteName}\n";

    payInitUtf8Mail($modx);
    $modx->mail->set(modMail::MAIL_BODY, $body);
    // Alt-текст для клиентов, которые плохо отображают HTML
    $modx->mail->set(modMail::MAIL_BODY_TEXT, $plainBody);
    $modx->mail->set(modMail::MAIL_FROM, $modx->getOption('emailsender'));
    $modx->mail->set(modMail::MAIL_FROM_NAME, $modx->getOption('site_name'));
    $modx->mail->set(modMail::MAIL_SUBJECT, $subject);
    $modx->mail->address('to', $email);
    $modx->mail->setHTML(true);

    if (!$modx->mail->send()) {
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            '[PaymentHandler] token email failed for ' . $orderId . ': ' . $modx->mail->mailer->ErrorInfo
        );
        $modx->mail->reset();
        return false;
    }

    $modx->mail->reset();
    return true;
}

function payUpsertBuyerInGroup($modx, array $order): bool {
    $email = filter_var(trim((string)($order['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    if (!$email) return false;

    try {
        $groupName = 'Покупатели';

        // MODX 3: email хранится в profile (modx_user_attributes), не в modUser.
        $user = $modx->getObject('modUser', ['username' => $email]);
        if (!$user) {
            $profileByEmail = $modx->getObject('modUserProfile', ['email' => $email]);
            if ($profileByEmail) {
                $user = $modx->getObject('modUser', ['id' => (int)$profileByEmail->get('internalKey')]);
            }
        }

        $tmpPassword = bin2hex(random_bytes(8));
        if (!$user) {
            $user = $modx->newObject('modUser');
            $user->set('username', $email);
            $user->set('active', 1);
            if (method_exists($user, 'setPassword')) {
                $user->setPassword($tmpPassword);
            } else {
                $user->set('password', $tmpPassword);
            }

            $profile = $modx->newObject('modUserProfile');
            $profile->set('email', $email);
            $user->addOne($profile, 'Profile');
            if (!$user->save()) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[PaymentHandler] buyer create failed for ' . $email);
                return false;
            }
        }

        if (!$user || !$user->get('id')) return false;

        $profile = $user->getOne('Profile');
        if (!$profile) {
            $profile = $modx->newObject('modUserProfile');
            $profile->set('internalKey', $user->get('id'));
        }
        $profile->set('email', $email);

        $extended = $profile->get('extended');
        if (!is_array($extended)) {
            $extended = [];
        }
        $extended['buyerTarget'] = (string)($order['target'] ?? '');
        $extended['buyerYears'] = (int)($order['years'] ?? 0);
        $extended['buyerOrderId'] = (string)($order['orderId'] ?? $order['invoiceId'] ?? '');
        if (!empty($order['invoiceId'])) {
            $extended['buyerInvoiceId'] = (string)$order['invoiceId'];
        }
        if (!empty($order['company'])) {
            $extended['buyerCompany'] = (string)$order['company'];
        }
        if (!empty($order['inn'])) {
            $extended['buyerInn'] = (string)$order['inn'];
        }
        $extended['buyerUpdatedAt'] = date('c');
        $profile->set('extended', $extended);
        $profile->save();

        // В MODX 3 $modx сам является xPDO — отдельного $modx->xpdo нет.
        $groupId = 0;
        $stmt = $modx->prepare('SELECT id FROM ' . $modx->getTableName('modUserGroup') . ' WHERE name = ? LIMIT 1');
        if ($stmt && $stmt->execute([$groupName])) {
            $groupId = (int)$stmt->fetchColumn();
        }

        if ($groupId <= 0) {
            $group = $modx->newObject('modUserGroup');
            $group->set('name', $groupName);
            $group->set('description', 'Покупатели (доступ к API календаря)');
            if ($group->save()) {
                $groupId = (int)$group->get('id');
            }
        }

        if ($groupId <= 0) return false;

        $member = $modx->getObject('modUserGroupMember', [
            'user_group' => $groupId,
            'member' => (int)$user->get('id'),
        ]);
        if (!$member) {
            $member = $modx->newObject('modUserGroupMember');
            $member->set('user_group', $groupId);
            $member->set('member', (int)$user->get('id'));
            $member->set('role', 1);
            $member->set('rank', 0);
            if (!$member->save()) {
                return false;
            }
        }

        return true;
    } catch (\Throwable $e) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[PaymentHandler] buyer upsert failed: ' . $e->getMessage());
        return false;
    }
}

function payResult($ordersDir, $orderId) {
    $orderId = trim((string)$orderId);
    if ($orderId === '') {
        return ['ok' => false, 'error' => 'Не указан order'];
    }
    $order = payLoadOrder($ordersDir, $orderId);
    if (!$order) {
        return ['ok' => false, 'error' => 'Заказ не найден'];
    }
    return [
        'ok' => true,
        'orderId' => $order['orderId'],
        'status' => $order['status'],
        'target' => $order['target'],
        'years' => $order['years'],
        'amount' => $order['amount'],
        'result' => $order['result'],
    ];
}

function payInvoice($modx, array $cfg, array $post) {
    $inn = preg_replace('/\D+/', '', (string)($post['inn'] ?? ''));
    $company = trim((string)($post['company'] ?? ''));
    $kpp = preg_replace('/\D+/', '', (string)($post['kpp'] ?? ''));
    $ogrn = preg_replace('/\D+/', '', (string)($post['ogrn'] ?? ''));
    $address = trim((string)($post['address'] ?? ''));
    $target = payValidateTarget($post['target'] ?? '');
    $years = payYears($post['years'] ?? 0);
    $email = filter_var(trim((string)($post['email'] ?? '')), FILTER_VALIDATE_EMAIL);

    if (!preg_match('/^\d{10}$|^\d{12}$/', $inn)) {
        return ['ok' => false, 'error' => 'ИНН должен содержать 10 или 12 цифр'];
    }
    if (!$target) return ['ok' => false, 'error' => 'Укажите корректный IP или домен'];
    if (!$years) return ['ok' => false, 'error' => 'Выберите срок: 1 или 2 года'];
    if (!$email) return ['ok' => false, 'error' => 'Укажите корректный e-mail'];

    // Подтягиваем реквизиты из DaData по ИНН (юрлицо / ИП).
    $party = payFindParty($modx, $inn);
    if (!empty($party['ok']) && !empty($party['party'])) {
        $p = $party['party'];
        if ($company === '' && !empty($p['name'])) {
            $company = (string)$p['name'];
        }
        if ($kpp === '' && !empty($p['kpp'])) {
            $kpp = (string)$p['kpp'];
        }
        if ($ogrn === '' && !empty($p['ogrn'])) {
            $ogrn = (string)$p['ogrn'];
        }
        if ($address === '' && !empty($p['address'])) {
            $address = (string)$p['address'];
        }
    }

    if ($company === '') {
        return ['ok' => false, 'error' => 'Не удалось определить организацию по ИНН. Укажите наименование вручную.'];
    }

    $amount = payAmountRub($cfg['priceYear'], $years);
    $invoiceId = 'INV-' . date('ymdHis') . bin2hex(random_bytes(2));
    $createdTs = time();
    $dueTs = strtotime('+7 days', $createdTs);
    $created = payInvoiceDateRu($createdTs);
    $due = payInvoiceDateRu($dueTs);
    $createdShort = date('d.m.Y', $createdTs);

    $sellerName = trim((string)$modx->getOption('pay_invoice_seller_name', null, ''));
    if ($sellerName === '') {
        $sellerName = (string)$modx->getOption('site_name', null, 'Studio West');
    }

    $baseUrl = rtrim((string)($cfg['baseUrl'] ?? ''), '/');
    $purpose = 'Оплата по счёту № ' . $invoiceId . ' от ' . $createdShort
        . ' за доступ к API производственного календаря (' . $target . ', ' . $years . ' г.) Без НДС';

    $html = payInvoiceHtml([
        'invoiceId' => $invoiceId,
        'created' => $created,
        'createdShort' => $createdShort,
        'due' => $due,
        'inn' => $inn,
        'kpp' => $kpp,
        'ogrn' => $ogrn,
        'address' => $address,
        'company' => $company,
        'target' => $target,
        'years' => $years,
        'amount' => $amount,
        'email' => $email,
        'baseUrl' => $baseUrl,
        'purpose' => $purpose,
        'seller' => $sellerName,
        'sellerInn' => $modx->getOption('pay_invoice_seller_inn', null, ''),
        'sellerKpp' => $modx->getOption('pay_invoice_seller_kpp', null, ''),
        'sellerAccount' => $modx->getOption('pay_invoice_seller_account', null, ''),
        'sellerBank' => $modx->getOption('pay_invoice_seller_bank', null, ''),
        'sellerBik' => $modx->getOption('pay_invoice_seller_bik', null, ''),
        'sellerCorr' => $modx->getOption('pay_invoice_seller_corr', null, ''),
        'sellerAddress' => $modx->getOption('pay_invoice_seller_address', null, ''),
    ]);

    $fileName = $invoiceId . '.html';
    $filePath = $cfg['invoicesDir'] . $fileName;
    file_put_contents($filePath, $html, LOCK_EX);

    $pdfName = $invoiceId . '.pdf';
    $pdfPath = $cfg['invoicesDir'] . $pdfName;
    $pdfOk = payInvoiceToPdf($modx, $html, $pdfPath);

    $url = $baseUrl . '/assets/pay/invoices/' . rawurlencode($fileName);
    $resultPage = (string)$modx->getOption('pay_success_url', null, $baseUrl . '/oplata/');
    $resultPage = rtrim($resultPage, '/');
    if (strpos($resultPage, '?') === false) {
        $resultPage .= '/?invoice=' . rawurlencode($invoiceId);
    } else {
        $resultPage .= '&invoice=' . rawurlencode($invoiceId);
    }

    $meta = [
        'invoiceId' => $invoiceId,
        'inn' => $inn,
        'kpp' => $kpp,
        'ogrn' => $ogrn,
        'address' => $address,
        'company' => $company,
        'target' => $target,
        'years' => $years,
        'email' => $email,
        'amount' => $amount,
        'created' => date('c'),
        'file' => $fileName,
        'pdf' => $pdfOk ? $pdfName : null,
        'url' => $url,
        'resultPage' => $resultPage,
    ];
    file_put_contents($cfg['invoicesDir'] . $invoiceId . '.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    $buyerOk = payUpsertBuyerInGroup($modx, [
        'email' => $email,
        'target' => $target,
        'years' => $years,
        'invoiceId' => $invoiceId,
        'orderId' => $invoiceId,
        'company' => $company,
        'inn' => $inn,
    ]);
    $meta['buyerGroupUpdated'] = $buyerOk;
    file_put_contents($cfg['invoicesDir'] . $invoiceId . '.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    paySendInvoiceEmail($modx, [
        'invoiceId' => $invoiceId,
        'inn' => $inn,
        'kpp' => $kpp,
        'ogrn' => $ogrn,
        'address' => $address,
        'company' => $company,
        'target' => $target,
        'years' => $years,
        'email' => $email,
        'amount' => $amount,
        'url' => $url,
        'resultPage' => $resultPage,
        'pdfPath' => $pdfOk ? $pdfPath : '',
        'pdfName' => $pdfName,
    ]);

    return [
        'ok' => true,
        'invoiceId' => $invoiceId,
        'amount' => $amount,
        'url' => $url,
        'resultPage' => $resultPage,
        'pdf' => $pdfOk ? ($baseUrl . '/assets/pay/invoices/' . rawurlencode($pdfName)) : null,
        'party' => [
            'inn' => $inn,
            'kpp' => $kpp,
            'ogrn' => $ogrn,
            'name' => $company,
            'address' => $address,
        ],
        'result' => "Счёт {$invoiceId} сформирован.\nИНН: {$inn}\n{$company}\nЦель: {$target}\nСрок: {$years} г.\nСумма: " . number_format($amount, 0, '', ' ') . " ₽\nСчёт: {$url}\nРезультат: {$resultPage}",
    ];
}

/**
 * HTML → PDF (Dompdf). Возвращает true при успехе.
 */
function payInvoiceToPdf($modx, string $html, string $pdfPath): bool {
    $autoload = $modx->getOption('base_path') . 'assets/pay/vendor/autoload.php';
    if (!is_file($autoload)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[PaymentHandler] Dompdf autoload missing: ' . $autoload);
        return false;
    }
    require_once $autoload;
    if (!class_exists('\\Dompdf\\Dompdf')) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[PaymentHandler] Dompdf class not found');
        return false;
    }

    $htmlForPdf = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $htmlForPdf = preg_replace(
        '/font-family:\s*Arial,\s*Helvetica,\s*sans-serif/i',
        'font-family: DejaVu Sans, Arial, sans-serif',
        $htmlForPdf
    );

    try {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($htmlForPdf, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $out = $dompdf->output();
        if ($out === '' || $out === null) {
            return false;
        }
        return file_put_contents($pdfPath, $out, LOCK_EX) !== false;
    } catch (\Throwable $e) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[PaymentHandler] PDF error: ' . $e->getMessage());
        return false;
    }
}

function paySendInvoiceEmail($modx, array $d): bool {
    $email = filter_var(trim((string)($d['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        return false;
    }

    $invoiceId = (string)($d['invoiceId'] ?? '');
    $company = (string)($d['company'] ?? '');
    $inn = (string)($d['inn'] ?? '');
    $kpp = (string)($d['kpp'] ?? '');
    $ogrn = (string)($d['ogrn'] ?? '');
    $address = (string)($d['address'] ?? '');
    $target = (string)($d['target'] ?? '');
    $years = (int)($d['years'] ?? 1);
    $amount = (int)($d['amount'] ?? 0);
    $url = (string)($d['url'] ?? '');
    $resultPage = (string)($d['resultPage'] ?? '');
    $pdfPath = (string)($d['pdfPath'] ?? '');
    $pdfName = (string)($d['pdfName'] ?? ($invoiceId . '.pdf'));
    $siteName = (string)$modx->getOption('site_name', null, 'Studio West');
    $amountFmt = number_format($amount, 0, '', ' ');

    $invoiceIdEsc = htmlspecialchars($invoiceId, ENT_QUOTES, 'UTF-8');
    $companyEsc = htmlspecialchars($company, ENT_QUOTES, 'UTF-8');
    $innEsc = htmlspecialchars($inn, ENT_QUOTES, 'UTF-8');
    $kppEsc = htmlspecialchars($kpp, ENT_QUOTES, 'UTF-8');
    $ogrnEsc = htmlspecialchars($ogrn, ENT_QUOTES, 'UTF-8');
    $addressEsc = htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
    $targetEsc = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
    $urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $resultEsc = htmlspecialchars($resultPage, ENT_QUOTES, 'UTF-8');
    $siteNameEsc = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $logoUrlEsc = 'https://studiowest.ru/assets/teme_sw/logo.svg';

    $extraRows = '';
    if ($kpp !== '') {
        $extraRows .= "<tr><td style=\"padding:8px 0;font-size:13px;color:#6b7280;width:140px;\">КПП:</td><td style=\"padding:8px 0;font-size:13px;color:#111827;\"><strong>{$kppEsc}</strong></td></tr>";
    }
    if ($ogrn !== '') {
        $extraRows .= "<tr><td style=\"padding:8px 0;font-size:13px;color:#6b7280;\">ОГРН:</td><td style=\"padding:8px 0;font-size:13px;color:#111827;\"><strong>{$ogrnEsc}</strong></td></tr>";
    }
    if ($address !== '') {
        $extraRows .= "<tr><td style=\"padding:8px 0;font-size:13px;color:#6b7280;\">Адрес:</td><td style=\"padding:8px 0;font-size:13px;color:#111827;\">{$addressEsc}</td></tr>";
    }

    $attachNote = is_file($pdfPath)
        ? 'Счёт во вложении в формате PDF.'
        : 'PDF во вложении недоступен — откройте счёт по ссылке ниже.';

    $body = <<<HTML
<html lang="ru">
<body style="margin:0;padding:0;background-color:#f5f7fb;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f5f7fb;">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:600px;background:#ffffff;border:1px solid #e6e9ef;border-radius:10px;">
          <tr>
            <td align="left" style="padding:20px 24px;border-bottom:1px solid #eef1f6;">
              <img src="{$logoUrlEsc}" alt="{$siteNameEsc}" width="180" style="display:block;border:0;outline:none;text-decoration:none;height:auto;">
            </td>
          </tr>
          <tr>
            <td style="padding:24px;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
              <div style="font-size:20px;line-height:28px;font-weight:700;margin:0 0 12px;">Счёт на оплату готов</div>
              <div style="font-size:14px;line-height:22px;margin:0 0 16px;">Здравствуйте! Для <strong>{$companyEsc}</strong> сформирован счёт <strong>№ {$invoiceIdEsc}</strong>.</div>
              <div style="font-size:14px;line-height:22px;margin:0 0 16px;">{$attachNote}</div>

              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:0 0 16px;">
                <tr>
                  <td style="padding:8px 0;font-size:13px;color:#6b7280;width:140px;">ИНН:</td>
                  <td style="padding:8px 0;font-size:13px;color:#111827;"><strong>{$innEsc}</strong></td>
                </tr>
                {$extraRows}
                <tr>
                  <td style="padding:8px 0;font-size:13px;color:#6b7280;">Цель:</td>
                  <td style="padding:8px 0;font-size:13px;color:#111827;"><strong>{$targetEsc}</strong></td>
                </tr>
                <tr>
                  <td style="padding:8px 0;font-size:13px;color:#6b7280;">Срок:</td>
                  <td style="padding:8px 0;font-size:13px;color:#111827;"><strong>{$years} г.</strong></td>
                </tr>
                <tr>
                  <td style="padding:8px 0;font-size:13px;color:#6b7280;">Сумма:</td>
                  <td style="padding:8px 0;font-size:13px;color:#111827;"><strong>{$amountFmt} ₽</strong></td>
                </tr>
              </table>

              <div style="margin:0 0 12px;">
                <a href="{$resultEsc}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;font-size:14px;line-height:20px;padding:10px 16px;border-radius:6px;">Открыть страницу с результатом</a>
              </div>
              <div style="font-size:13px;line-height:20px;color:#6b7280;margin:0 0 6px;">Страница счёта (печать / PDF):</div>
              <div style="font-size:13px;line-height:20px;word-break:break-all;margin:0 0 8px;"><a href="{$urlEsc}" style="color:#2563eb;">{$urlEsc}</a></div>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 24px;border-top:1px solid #eef1f6;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#9ca3af;">
              <div style="margin:0 0 4px;">{$siteNameEsc} · Автоматическое уведомление</div>
              <div style="margin:0;">Если это письмо попало к вам по ошибке, просто проигнорируйте его.</div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    $plainBody = "Счёт на оплату готов\n\n"
        . "Счёт: {$invoiceId}\n"
        . "Организация: {$company}\n"
        . "ИНН: {$inn}\n"
        . ($kpp !== '' ? "КПП: {$kpp}\n" : '')
        . ($ogrn !== '' ? "ОГРН: {$ogrn}\n" : '')
        . ($address !== '' ? "Адрес: {$address}\n" : '')
        . "Цель: {$target}\n"
        . "Срок: {$years} г.\n"
        . "Сумма: {$amountFmt} ₽\n\n"
        . "Страница с результатом:\n{$resultPage}\n\n"
        . "Страница счёта:\n{$url}\n\n"
        . "Автоматическое уведомление от {$siteName}\n";

    payInitUtf8Mail($modx);
    $modx->mail->set(modMail::MAIL_BODY, $body);
    $modx->mail->set(modMail::MAIL_BODY_TEXT, $plainBody);
    $modx->mail->set(modMail::MAIL_FROM, $modx->getOption('emailsender'));
    $modx->mail->set(modMail::MAIL_FROM_NAME, $modx->getOption('site_name'));
    $modx->mail->set(modMail::MAIL_SUBJECT, "Счёт {$invoiceId}");
    $modx->mail->address('to', $email);
    $modx->mail->setHTML(true);

    if (is_file($pdfPath)) {
        $modx->mail->attach($pdfPath, $pdfName, 'base64', 'application/pdf');
    }

    if (!$modx->mail->send()) {
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            '[PaymentHandler] invoice email failed for ' . $invoiceId . ': ' . $modx->mail->mailer->ErrorInfo
        );
        $modx->mail->reset();
        return false;
    }

    $modx->mail->reset();
    return true;
}

/**
 * DaData: организация / ИП по ИНН или ОГРН.
 * @see https://dadata.ru/api/find-party/
 */
function payDadataToken($modx) {
    return trim((string)$modx->getOption(
        'pay_dadata_token',
        null,
        '6582965ff5937562597b8849ef712479971b1e28'
    ));
}

function payFindParty($modx, $query) {
    $query = trim((string)$query);
    $digits = preg_replace('/\D+/', '', $query);
    if ($digits === '' || (!preg_match('/^\d{10}$|^\d{12}$/', $digits) && !preg_match('/^\d{13}$|^\d{15}$/', $digits))) {
        return ['ok' => false, 'error' => 'Укажите ИНН (10/12) или ОГРН (13/15)'];
    }

    $token = payDadataToken($modx);
    if ($token === '') {
        return ['ok' => false, 'error' => 'DaData не настроен (pay_dadata_token)'];
    }

    $body = json_encode([
        'query' => $digits,
        'branch_type' => 'MAIN',
        'count' => 1,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Token ' . $token,
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $http >= 400) {
        return ['ok' => false, 'error' => 'DaData недоступна' . ($err ? (': ' . $err) : " (HTTP {$http})")];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Некорректный ответ DaData'];
    }

    $suggestions = $json['suggestions'] ?? [];
    if (!is_array($suggestions) || empty($suggestions[0]['data'])) {
        return ['ok' => false, 'error' => 'Организация по указанному ИНН/ОГРН не найдена'];
    }

    $item = $suggestions[0];
    $data = $item['data'];
    $name = '';
    if (!empty($item['value'])) {
        $name = (string)$item['value'];
    } elseif (!empty($data['name']['short_with_opf'])) {
        $name = (string)$data['name']['short_with_opf'];
    } elseif (!empty($data['name']['full_with_opf'])) {
        $name = (string)$data['name']['full_with_opf'];
    }
    if ($name === '' && !empty($data['fio']) && is_array($data['fio'])) {
        $fio = $data['fio'];
        $name = trim(implode(' ', array_filter([
            $fio['surname'] ?? '',
            $fio['name'] ?? '',
            $fio['patronymic'] ?? '',
        ])));
        if ($name !== '') {
            $name = 'ИП ' . $name;
        }
    }

    $address = '';
    if (!empty($data['address']['unrestricted_value'])) {
        $address = (string)$data['address']['unrestricted_value'];
    } elseif (!empty($data['address']['value'])) {
        $address = (string)$data['address']['value'];
    } elseif (!empty($data['address']['data']['source'])) {
        $address = (string)$data['address']['data']['source'];
    }

    $party = [
        'inn' => (string)($data['inn'] ?? $digits),
        'kpp' => (string)($data['kpp'] ?? ''),
        'ogrn' => (string)($data['ogrn'] ?? ''),
        'name' => $name,
        'address' => $address,
        'type' => (string)($data['type'] ?? ''),
        'status' => (string)(($data['state']['status'] ?? '')),
        'branch_type' => (string)($data['branch_type'] ?? ''),
        'management' => (string)(($data['management']['name'] ?? '')),
    ];

    return [
        'ok' => true,
        'party' => $party,
    ];
}

function payInvoiceDateRu($ts): string {
    static $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];
    $ts = (int)$ts;
    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    return $d . ' ' . ($months[$m] ?? date('m', $ts)) . ' ' . $y . ' г.';
}

function payRubInWords(int $amount): string {
    $n = abs($amount);
    $units = [
        ['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'],
        ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'],
    ];
    $teens = [
        'десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать',
        'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать',
    ];
    $tens = [
        '', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят',
        'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто',
    ];
    $hundreds = [
        '', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот',
        'шестьсот', 'семьсот', 'восемьсот', 'девятьсот',
    ];

    $triad = function (int $num, int $gender) use ($units, $teens, $tens, $hundreds): string {
        $num = $num % 1000;
        if ($num === 0) return '';
        $h = intdiv($num, 100);
        $t = intdiv($num % 100, 10);
        $u = $num % 10;
        $parts = [];
        if ($h) $parts[] = $hundreds[$h];
        if ($t === 1) {
            $parts[] = $teens[$u];
        } else {
            if ($t) $parts[] = $tens[$t];
            if ($u) $parts[] = $units[$gender][$u];
        }
        return implode(' ', $parts);
    };

    $plural = function (int $n, array $forms): string {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) return $forms[2];
        if ($n1 > 1 && $n1 < 5) return $forms[1];
        if ($n1 === 1) return $forms[0];
        return $forms[2];
    };

    if ($n === 0) {
        return 'Ноль рублей 00 копеек';
    }

    $millions = intdiv($n, 1000000);
    $thousands = intdiv($n % 1000000, 1000);
    $rest = $n % 1000;
    $parts = [];

    if ($millions) {
        $parts[] = trim($triad($millions, 0) . ' ' . $plural($millions, ['миллион', 'миллиона', 'миллионов']));
    }
    if ($thousands) {
        $parts[] = trim($triad($thousands, 1) . ' ' . $plural($thousands, ['тысяча', 'тысячи', 'тысяч']));
    }
    if ($rest || !$parts) {
        $parts[] = $triad($rest, 0);
    }

    $words = trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
    $words = mb_strtoupper(mb_substr($words, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($words, 1, null, 'UTF-8');
    return $words . ' ' . $plural($n, ['рубль', 'рубля', 'рублей']) . ' 00 копеек';
}

function payInvoiceQrPayload(array $d): string {
    $sumKop = (int)round(((float)$d['amount']) * 100);
    $fields = [
        'Name' => (string)($d['seller'] ?? ''),
        'PersonalAcc' => preg_replace('/\D+/', '', (string)($d['sellerAccount'] ?? '')),
        'BankName' => (string)($d['sellerBank'] ?? ''),
        'BIC' => preg_replace('/\D+/', '', (string)($d['sellerBik'] ?? '')),
        'CorrespAcc' => preg_replace('/\D+/', '', (string)($d['sellerCorr'] ?? '')),
        'PayeeINN' => preg_replace('/\D+/', '', (string)($d['sellerInn'] ?? '')),
        'KPP' => preg_replace('/\D+/', '', (string)($d['sellerKpp'] ?? '')),
        'Sum' => (string)$sumKop,
        'Purpose' => (string)($d['purpose'] ?? ''),
    ];
    $parts = ['ST00012'];
    foreach ($fields as $k => $v) {
        $v = trim(str_replace(["\r", "\n", '|'], ' ', (string)$v));
        if ($v === '') continue;
        $parts[] = $k . '=' . $v;
    }
    return implode('|', $parts);
}

function payInvoiceQrDataUri(string $payload): string {
    $payload = trim($payload);
    if ($payload === '') return '';

    $urls = [
        'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=8&ecc=M&data=' . rawurlencode($payload),
        'https://quickchart.io/qr?size=180&margin=1&text=' . rawurlencode($payload),
    ];

    foreach ($urls as $url) {
        $bin = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'StudioWest-Invoice/1.0',
            ]);
            $bin = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($bin === false || $code >= 400 || strlen((string)$bin) < 40) {
                $bin = false;
            }
        }
        if ($bin === false && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['timeout' => 8], 'ssl' => ['verify_peer' => true]]);
            $bin = @file_get_contents($url, false, $ctx);
        }
        if (is_string($bin) && strlen($bin) > 40) {
            return 'data:image/png;base64,' . base64_encode($bin);
        }
    }
    return '';
}

function payInvoiceHtml(array $d) {
    $amount = (int)$d['amount'];
    $amountFmt = number_format($amount, 2, ',', ' ');
    $amountWords = payRubInWords($amount);
    $yearsLabel = ((int)$d['years'] === 1) ? '1 год' : ((int)$d['years'] . ' года');
    $itemName = 'Доступ к API производственного календаря (' . $yearsLabel . ') для ' . $d['target'];

    $company = htmlspecialchars($d['company'], ENT_QUOTES, 'UTF-8');
    $inn = htmlspecialchars($d['inn'], ENT_QUOTES, 'UTF-8');
    $kpp = htmlspecialchars((string)($d['kpp'] ?? ''), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars((string)($d['address'] ?? ''), ENT_QUOTES, 'UTF-8');
    $seller = htmlspecialchars((string)$d['seller'], ENT_QUOTES, 'UTF-8');
    $invoiceId = htmlspecialchars($d['invoiceId'], ENT_QUOTES, 'UTF-8');
    $sellerInn = htmlspecialchars((string)($d['sellerInn'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sellerKpp = htmlspecialchars((string)($d['sellerKpp'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sellerAccount = htmlspecialchars((string)($d['sellerAccount'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sellerBank = htmlspecialchars((string)($d['sellerBank'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sellerBik = htmlspecialchars((string)($d['sellerBik'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sellerCorr = htmlspecialchars((string)($d['sellerCorr'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sellerAddress = htmlspecialchars((string)($d['sellerAddress'] ?? ''), ENT_QUOTES, 'UTF-8');
    $purpose = htmlspecialchars((string)($d['purpose'] ?? ''), ENT_QUOTES, 'UTF-8');
    $itemNameEsc = htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8');
    $created = htmlspecialchars((string)$d['created'], ENT_QUOTES, 'UTF-8');
    $due = htmlspecialchars((string)$d['due'], ENT_QUOTES, 'UTF-8');
    $amountWordsEsc = htmlspecialchars($amountWords, ENT_QUOTES, 'UTF-8');

    $baseUrl = rtrim((string)($d['baseUrl'] ?? ''), '/');
    if ($baseUrl === '') $baseUrl = 'https://studiowest.ru';
    $printSrc = htmlspecialchars($baseUrl . '/assets/teme_sw/js/print1.js', ENT_QUOTES, 'UTF-8');

    $buyerLine = $company;
    if ($inn !== '') $buyerLine .= ', ИНН ' . $inn;
    if ($kpp !== '') $buyerLine .= ', КПП ' . $kpp;
    if ($address !== '') $buyerLine .= ', ' . $address;

    $sellerLine = $seller;
    if ($sellerInn !== '') $sellerLine .= ', ИНН ' . $sellerInn;
    if ($sellerKpp !== '') $sellerLine .= ', КПП ' . $sellerKpp;
    if ($sellerAddress !== '') $sellerLine .= ', ' . $sellerAddress;

    $qrPayload = payInvoiceQrPayload($d);
    $qrDataUri = payInvoiceQrDataUri($qrPayload);
    $qrHtml = $qrDataUri !== ''
        ? '<img class="qr-img" src="' . htmlspecialchars($qrDataUri, ENT_QUOTES, 'UTF-8') . '" alt="QR для оплаты" width="140" height="140">'
        : '<div class="qr-fallback">QR недоступен</div>';

    return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Счёт на оплату № {$invoiceId}</title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.35;color:#111;max-width:900px;margin:18px auto;padding:0 14px;background:#fff}
.due{margin:0 0 10px;font-size:12px}
.bank-wrap{display:table;width:100%;border-collapse:collapse;margin:0 0 16px}
.bank-qr,.bank-table{display:table-cell;vertical-align:top}
.bank-qr{width:160px;padding-right:12px}
.qr-img{display:block;width:140px;height:140px;border:1px solid #111}
.qr-fallback{width:140px;height:140px;border:1px solid #111;display:flex;align-items:center;justify-content:center;text-align:center;font-size:11px;color:#666}
.bank{width:100%;border-collapse:collapse}
.bank td{border:1px solid #111;padding:4px 6px;vertical-align:top}
.bank .lbl{font-size:10px;color:#444}
.bank .val{font-size:12px;font-weight:700}
.bank .muted{font-weight:400}
.title{font-size:18px;font-weight:700;margin:14px 0 12px;padding-bottom:6px;border-bottom:2px solid #111}
.party{margin:0 0 8px}
.party .lbl{font-weight:700;display:inline}
.items{width:100%;border-collapse:collapse;margin-top:12px}
.items th,.items td{border:1px solid #111;padding:5px 6px;vertical-align:top}
.items th{text-align:center;font-weight:700;background:#f3f3f3}
.items .num,.items .qty,.items .unit,.items .nds,.items .money{text-align:center;white-space:nowrap}
.items .money{text-align:right}
.totals{margin-top:10px;width:100%}
.totals td{padding:2px 0}
.totals .right{text-align:right}
.totals .sum{font-weight:700;font-size:13px}
.words{margin-top:8px;font-weight:700}
.purpose{margin-top:10px;font-size:11px;color:#333}
.signs{margin-top:34px;width:100%;border-collapse:collapse}
.signs td{width:50%;padding:0 18px 0 0;vertical-align:bottom}
.signs .role{margin-bottom:28px}
.signs .line{border-bottom:1px solid #111;height:18px}
.note{margin-top:16px;font-size:10px;color:#555}
@media print{body{margin:0;max-width:none}.no-print{display:none!important}}
</style>
</head>
<body>
<p class="due">Оплату необходимо произвести до {$due}</p>

<div class="bank-wrap">
  <div class="bank-qr">{$qrHtml}</div>
  <div class="bank-table">
    <table class="bank">
      <tr>
        <td colspan="2">
          <div class="lbl">Банк получателя</div>
          <div class="val">{$sellerBank}</div>
        </td>
        <td style="width:18%">
          <div class="lbl">БИК</div>
          <div class="val">{$sellerBik}</div>
        </td>
        <td style="width:28%">
          <div class="lbl">Сч. №</div>
          <div class="val">{$sellerCorr}</div>
        </td>
      </tr>
      <tr>
        <td style="width:18%">
          <div class="lbl">ИНН</div>
          <div class="val">{$sellerInn}</div>
        </td>
        <td style="width:18%">
          <div class="lbl">КПП</div>
          <div class="val">{$sellerKpp}</div>
        </td>
        <td colspan="2">
          <div class="lbl">Сч. №</div>
          <div class="val">{$sellerAccount}</div>
        </td>
      </tr>
      <tr>
        <td colspan="4">
          <div class="lbl">Получатель</div>
          <div class="val">{$seller}</div>
        </td>
      </tr>
    </table>
  </div>
</div>

<div class="title">Счет на оплату № {$invoiceId} от {$created}</div>

<div class="party"><span class="lbl">Поставщик:</span> {$sellerLine}</div>
<div class="party"><span class="lbl">Покупатель:</span> {$buyerLine}</div>

<table class="items">
  <thead>
    <tr>
      <th style="width:36px">№</th>
      <th>Товары (работы, услуги)</th>
      <th style="width:56px">кол-во</th>
      <th style="width:44px">Ед.</th>
      <th style="width:70px">НДС</th>
      <th style="width:90px">Цена</th>
      <th style="width:100px">Сумма</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="num">1</td>
      <td>{$itemNameEsc}</td>
      <td class="qty">1</td>
      <td class="unit">шт</td>
      <td class="nds">Без НДС</td>
      <td class="money">{$amountFmt}</td>
      <td class="money">{$amountFmt}</td>
    </tr>
  </tbody>
</table>

<table class="totals">
  <tr>
    <td>Всего наименований 1 на сумму {$amountFmt} руб.</td>
    <td class="right sum">Итого к оплате: {$amountFmt}</td>
  </tr>
</table>
<div class="words">{$amountWordsEsc}</div>
<div class="purpose"><strong>Назначение платежа:</strong> {$purpose}</div>

<table class="signs">
  <tr>
    <td>
      <div class="role">Руководитель</div>
      <div class="line"></div>
    </td>
    <td>
      <div class="role">Бухгалтер</div>
      <div class="line"></div>
    </td>
  </tr>
</table>

<div class="note">Счёт сформирован автоматически. Для оплаты отсканируйте QR-код в мобильном банке.</div>
<script src="{$printSrc}"></script>
</body>
</html>
HTML;
}
