<?php
/**
 * AJAX endpoint (ресурс ajaxress): next / payment / stendMail.
 * CSRF: cookie sw_csrf + поле/заголовок; Origin/Referer same-site.
 */

if (PHP_SAPI === 'cli') {
    return;
}

$action = filter_input(INPUT_POST, 'action');
if (empty($action)) {
    $rawNotify = file_get_contents('php://input');
    $notifyBody = json_decode($rawNotify ?: '', true);
    if (is_array($notifyBody) && !empty($notifyBody['OrderId']) && isset($notifyBody['Status'], $notifyBody['Token'])) {
        $action = 'pay_notify';
        $GLOBALS['_pay_notify_raw'] = $notifyBody;
    }
}
if (empty($action)) {
    die('stop');
}

$isPayNotify = ($action === 'pay_notify');

// CSRF / same-site (кроме webhook Т‑Банк)
if (!$isPayNotify) {
    if (!swRequestSameSite($modx)) {
        http_response_code(403);
        die('stop');
    }
    if (in_array($action, ['stendMail', 'pay_init', 'invoice', 'find_party'], true) && !swRequestCsrfOk()) {
        http_response_code(403);
        die('stop');
    }
}

switch ($action) {
    case 'next':
        $invet = $_SERVER['HTTP_REFERER'] ?? filter_input(INPUT_POST, 'invet');
        $path = trim((string)parse_url((string)$invet, PHP_URL_PATH), '/');
        [$catalog, $alias] = array_pad(explode('/', $path), 2, '');

        $parent = $modx->getObject('modResource', ['published' => 1, 'deleted' => 0, 'alias' => $catalog]);
        if (!$parent) {
            echo 'stop';
            exit;
        }

        $children = $modx->getCollection('modResource', [
            'parent' => $parent->get('id'),
            'published' => 1,
            'deleted' => 0,
        ]);
        if (empty($children)) {
            echo 'stop';
            exit;
        }

        uasort($children, 'cmp');

        $res = null;
        $found = false;

        if ($alias) {
            foreach ($children as $child) {
                if ($found) {
                    $res = otvet($catalog, $child);
                    break;
                }
                if ($child->get('alias') === $alias) {
                    $found = true;
                }
            }
        } else {
            reset($children);
            $first = current($children);
            if ($first) {
                $res = otvet($catalog, $first);
            }
        }
        break;

    case 'pay_init':
    case 'pay_result':
    case 'invoice':
    case 'find_party':
    case 'pay_notify':
        include $modx->getOption('base_path') . 'assets/teme_sw/snippet/PaymentHandler.php';
        exit;

    case 'stendMail':
        // honeypot
        if (!empty($_POST['mail'])) {
            die('stop');
        }

        $attachPath = null;
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $attachPath = swSafeStoreUpload($_FILES['file'], $modx->getOption('base_path') . 'assets/attach');
            if ($attachPath === null) {
                die('stop');
            }
        }

        $memail = 'shurafan@yandex.ru';
        $siteFrom = 'noreply@studiowest.ru';
        $name = swMailSafeText($_POST['name'] ?? '', 80) ?: 'no-reply';
        $theme = 'Заявка с сайта studiowest.ru';
        $replyTo = swMailSafeEmail($_POST['contact'] ?? '');
        if ($replyTo === '') {
            $replyTo = $siteFrom;
        }

        $fields = [
            'phone' => 'Телефон',
            'time' => 'Дата',
            'tip' => 'Тип',
            'bulk' => 'Объём',
            'dostavka' => 'Адрес',
            'prise' => 'Цена',
            'sale' => 'Скидка',
            'contact' => 'Контакты',
            'questions' => 'Вопросы',
            'message' => 'Сообщение',
        ];
        $message = '<html><head><title>Письмо с сайта studiowest.ru</title></head><body>';
        foreach ($fields as $key => $label) {
            if (empty($_POST[$key])) {
                continue;
            }
            $message .= $label . ': ' . htmlspecialchars(swMailSafeText($_POST[$key], 2000), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>';
        }
        $message .= '</body></html>';

        $modx->getService('mail', 'mail.modPHPMailer');
        $modx->mail->set(modMail::MAIL_BODY, $message);
        $modx->mail->set(modMail::MAIL_FROM, $siteFrom);
        $modx->mail->set(modMail::MAIL_FROM_NAME, $name);
        $modx->mail->set(modMail::MAIL_SUBJECT, $theme);
        $modx->mail->address('to', $memail);
        $modx->mail->address('reply-to', $replyTo);
        $modx->mail->setHTML(true);
        if ($attachPath) {
            $modx->mail->attach($attachPath);
        }
        if (!$modx->mail->send()) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'An error occurred while trying to send the email: ' . $modx->mail->mailer->ErrorInfo);
        } else {
            $res = 'success';
        }
        $modx->mail->reset();
        break;
}

if (!empty($res)) {
    echo $res;
} else {
    die('stop');
}

/**
 * Same-site Origin/Referer.
 */
function swRequestSameSite(modX $modx): bool
{
    $siteHost = parse_url((string)$modx->getOption('site_url'), PHP_URL_HOST);
    if (!$siteHost) {
        $siteHost = $_SERVER['HTTP_HOST'] ?? '';
    }
    $siteHost = preg_replace('/^www\./i', '', strtolower((string)$siteHost));
    $check = static function ($url) use ($siteHost) {
        if ($url === '' || $url === null) {
            return null;
        }
        $h = parse_url((string)$url, PHP_URL_HOST);
        if (!$h) {
            return false;
        }
        $h = preg_replace('/^www\./i', '', strtolower($h));
        return $h === $siteHost;
    };
    $origin = $check($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === true) {
        return true;
    }
    $referer = $check($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer === true) {
        return true;
    }
    // Нет Origin/Referer — отклоняем state-changing запросы
    return false;
}

function swRequestCsrfOk(): bool
{
    $cookie = (string)($_COOKIE['sw_csrf'] ?? '');
    $token = (string)($_POST['sw_csrf'] ?? '');
    if ($token === '' && !empty($_SERVER['HTTP_X_SW_CSRF'])) {
        $token = (string)$_SERVER['HTTP_X_SW_CSRF'];
    }
    if ($cookie === '' || $token === '' || !hash_equals($cookie, $token)) {
        return false;
    }
    return true;
}

function swMailSafeText($value, int $max = 500): string
{
    $value = trim(str_replace(["\r", "\n", "\0"], ' ', (string)$value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

function swMailSafeEmail($value): string
{
    $value = swMailSafeText($value, 180);
    // contact может быть телефоном — тогда не reply-to email
    if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    return $value;
}

/**
 * Сохранить upload: только whitelist, random name, без PHP.
 * @return string|null absolute path
 */
function swSafeStoreUpload(array $file, string $dir)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        return null;
    }
    $orig = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = [
        'pdf' => true,
        'doc' => true,
        'docx' => true,
        'pages' => true,
        'jpg' => true,
        'jpeg' => true,
        'png' => true,
        'webp' => true,
        'gif' => true,
        'txt' => true,
        'odt' => true,
        'rtf' => true,
        'xls' => true,
        'xlsx' => true,
        'zip' => true,
    ];
    if ($ext === '' || empty($allowed[$ext])) {
        return null;
    }
    // двойные расширения / php*.phtml
    if (preg_match('/\.(php|phtml|phar|cgi|pl|asp|aspx|js|html|htm|shtml)(\.|$)/i', $orig)) {
        return null;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return null;
    }
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = rtrim($dir, '/') . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    @chmod($dest, 0640);
    return $dest;
}

function parseResourceField($modx, $resource, string $field): string
{
    $content = (string)$resource->get($field);
    if ($content === '') {
        return '';
    }
    $oldResource = $modx->resource;
    $modx->resource = $resource;
    $parser = $modx->getParser();
    if ($parser) {
        $maxIterations = (int)$modx->getOption('parser_max_iterations', null, 10);
        $parser->processElementTags('', $content, false, false, '[[', ']]', [], $maxIterations);
        $parser->processElementTags('', $content, true, false, '[[', ']]', [], $maxIterations);
    }
    $modx->resource = $oldResource;
    return $content;
}

function otvet($catalog, $ress)
{
    global $modx;
    $url = $catalog . '/' . $ress->get('alias');
    return json_encode([
        'URL' => $url,
        'ANONS' => parseResourceField($modx, $ress, 'introtext'),
        'CONT' => parseResourceField($modx, $ress, 'content'),
        'FON' => $ress->getTVValue('fonSite'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function cmp($a, $b)
{
    $a = $a->menuindex;
    $b = $b->menuindex;
    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}
