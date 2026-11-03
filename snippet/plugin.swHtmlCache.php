<?php
/**
 * swHtmlCache — Cache-Control для анонимного HTML (CWV / bfcache).
 *
 * Событие: OnWebPagePrerender (до отдачи HTML — иначе headers_sent).
 * Не трогает менеджер, авторизованных и ответы, где уже открыта PHP-сессия.
 *
 * @var modX $modx
 */

if (PHP_SAPI === 'cli') {
    return;
}

// Менеджер / API / AJAX — не кешируем
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
if ((strpos($uri, '/manager')===0) || (strpos($uri, '/connectors')===0) || (strpos($uri, '/ajax')===0)) {
    return;
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    return;
}

if ($modx->user && method_exists($modx->user, 'isAuthenticated') && $modx->user->isAuthenticated($modx->context->key ?? 'web')) {
    return;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

// Короткий браузерный кеш + разрешение bfcache; HTML всё ещё инвалидируется при правках через resource cache MODX.
if (!headers_sent()) {
    header('Cache-Control: public, max-age=120, stale-while-revalidate=60');
    header_remove('Pragma');
    header_remove('Expires');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
