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
 *   pay_success_url      — URL возврата после оплаты (страница оплаты)
 *   pay_fail_url         — URL при ошибке оплаты
 *   pay_notify_url       — URL вебхука NotificationURL
 *
 * Вызов как сниппет с connector или через resource с &action:
 *   [[!PaymentHandler]]
 * Или AJAX POST на страницу/connector с action=pay_init|pay_notify|pay_result|invoice
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
            $result = payRunScript($cfg['scriptPath'], $order);
            $order['result'] = $result['output'];
            $order['resultCode'] = $result['code'];
            $order['status'] = $result['ok'] ? 'done' : 'script_error';
            $order['finished'] = date('c');
        }
    } elseif (in_array($status, ['REJECTED', 'CANCELED', 'DEADLINE_EXPIRED'], true)) {
        $order['status'] = 'failed';
    }

    paySaveOrder($cfg['ordersDir'], $orderId, $order);
    return 'OK';
}

function payRunScript($scriptPath, array $order) {
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
    $cmd = escapeshellcmd($scriptPath) . " {$target} {$years} {$orderId} {$email} 2>&1";

    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    $text = implode("\n", $output);
    if ($text === '') {
        $text = $code === 0 ? 'Скрипт выполнен успешно.' : "Скрипт завершился с кодом {$code}.";
    }

    return ['ok' => $code === 0, 'code' => $code, 'output' => $text];
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
    $target = payValidateTarget($post['target'] ?? '');
    $years = payYears($post['years'] ?? 0);
    $email = filter_var(trim((string)($post['email'] ?? '')), FILTER_VALIDATE_EMAIL);

    if (!preg_match('/^\d{10}$|^\d{12}$/', $inn)) {
        return ['ok' => false, 'error' => 'ИНН должен содержать 10 или 12 цифр'];
    }
    if ($company === '') return ['ok' => false, 'error' => 'Укажите наименование организации'];
    if (!$target) return ['ok' => false, 'error' => 'Укажите корректный IP или домен'];
    if (!$years) return ['ok' => false, 'error' => 'Выберите срок: 1 или 2 года'];
    if (!$email) return ['ok' => false, 'error' => 'Укажите корректный e-mail'];

    $amount = payAmountRub($cfg['priceYear'], $years);
    $invoiceId = 'INV-' . date('ymdHis') . bin2hex(random_bytes(2));
    $created = date('d.m.Y');
    $due = date('d.m.Y', strtotime('+5 days'));

    $html = payInvoiceHtml([
        'invoiceId' => $invoiceId,
        'created' => $created,
        'due' => $due,
        'inn' => $inn,
        'company' => $company,
        'target' => $target,
        'years' => $years,
        'amount' => $amount,
        'email' => $email,
        'seller' => $modx->getOption('site_name', null, 'Studio West'),
    ]);

    $fileName = $invoiceId . '.html';
    $filePath = $cfg['invoicesDir'] . $fileName;
    file_put_contents($filePath, $html, LOCK_EX);

    $meta = [
        'invoiceId' => $invoiceId,
        'inn' => $inn,
        'company' => $company,
        'target' => $target,
        'years' => $years,
        'email' => $email,
        'amount' => $amount,
        'created' => date('c'),
        'file' => $fileName,
    ];
    file_put_contents($cfg['invoicesDir'] . $invoiceId . '.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    // письмо бухгалтерии / клиенту (если настроен mail)
    $mailBody = "Счёт {$invoiceId}\nИНН: {$inn}\n{$company}\nЦель: {$target}\nСрок: {$years} г.\nСумма: {$amount} ₽\n";
    $modx->getService('mail', 'mail.modPHPMailer');
    $modx->mail->set(modMail::MAIL_BODY, $mailBody);
    $modx->mail->set(modMail::MAIL_FROM, $modx->getOption('emailsender'));
    $modx->mail->set(modMail::MAIL_FROM_NAME, $modx->getOption('site_name'));
    $modx->mail->set(modMail::MAIL_SUBJECT, "Счёт {$invoiceId}");
    $modx->mail->address('to', $email);
    $modx->mail->setHTML(false);
    @$modx->mail->send();
    $modx->mail->reset();

    $url = rtrim($cfg['baseUrl'], '/') . '/assets/pay/invoices/' . rawurlencode($fileName);

    return [
        'ok' => true,
        'invoiceId' => $invoiceId,
        'amount' => $amount,
        'url' => $url,
        'result' => "Счёт {$invoiceId} сформирован.\nИНН: {$inn}\n{$company}\nЦель: {$target}\nСрок: {$years} г.\nСумма: " . number_format($amount, 0, '', ' ') . " ₽\nСсылка: {$url}",
    ];
}

function payInvoiceHtml(array $d) {
    $amountFmt = number_format($d['amount'], 0, ',', ' ');
    $yearsLabel = $d['years'] === 1 ? '1 год' : '2 года';
    $company = htmlspecialchars($d['company'], ENT_QUOTES, 'UTF-8');
    $inn = htmlspecialchars($d['inn'], ENT_QUOTES, 'UTF-8');
    $target = htmlspecialchars($d['target'], ENT_QUOTES, 'UTF-8');
    $seller = htmlspecialchars($d['seller'], ENT_QUOTES, 'UTF-8');
    $invoiceId = htmlspecialchars($d['invoiceId'], ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Счёт {$invoiceId}</title>
<style>
body{font:14px/1.4 system-ui,sans-serif;color:#222;max-width:800px;margin:24px auto;padding:0 16px}
h1{font-size:20px;margin:0 0 8px}
table{width:100%;border-collapse:collapse;margin:16px 0}
th,td{border:1px solid #ccc;padding:8px 10px;text-align:left}
th{background:#f5f5f5}
.meta{margin:0 0 16px;color:#555}
.total{font-size:16px;font-weight:700}
@media print{body{margin:0}}
</style>
</head>
<body>
<h1>Счёт на оплату № {$invoiceId}</h1>
<p class="meta">от {$d['created']} · оплатить до {$d['due']}</p>
<p><strong>Поставщик:</strong> {$seller}</p>
<p><strong>Покупатель:</strong> {$company}<br>ИНН: {$inn}<br>E-mail: {$d['email']}</p>
<table>
  <thead><tr><th>№</th><th>Наименование</th><th>Кол-во</th><th>Сумма, ₽</th></tr></thead>
  <tbody>
    <tr>
      <td>1</td>
      <td>Услуга ({$yearsLabel}) для {$target}</td>
      <td>1</td>
      <td>{$amountFmt}</td>
    </tr>
  </tbody>
</table>
<p class="total">Итого к оплате: {$amountFmt} ₽</p>
<p class="meta">Счёт сформирован автоматически. Основание — заявка с сайта.</p>
</body>
</html>
HTML;
}
