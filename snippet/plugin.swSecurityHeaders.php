<?php
/**
 * swSecurityHeaders — security headers + CSRF cookie + защита mgr.
 *
 * События: OnHandleRequest, OnWebPagePrerender, OnManagerPageBeforeRender
 *
 * Настройка: sw.admin_allow_ips — CSV IP (пусто = не режем по IP, только headers).
 *            sw.admin_basic_user / sw.admin_basic_pass — HTTP Basic перед /wp-admin/ и /stender/
 *              (через OnHandleRequest для manager; для stender — отдельный gate).
 *
 * @var modX $modx
 */

if (PHP_SAPI === 'cli') {
    return;
}

$event = $modx->event->name ?? '';

if ($event === 'OnHandleRequest' || $event === 'OnWebPagePrerender') {
    swSecEnsureCsrfCookie();
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        // HSTS только на HTTPS
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        // Базовый CSP (без жёсткого block inline — сайт использует inline)
        if (!header_sent_named('Content-Security-Policy')) {
            header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
        }
    }
}

if ($event === 'OnHandleRequest' || $event === 'OnManagerPageBeforeRender') {
    $ctx = $modx->context->key ?? '';
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $isMgr = ($ctx === 'mgr')
        || (strpos($uri, '/wp-admin')===0)
        || (strpos($uri, '/manager')===0);

    if ($isMgr) {
        swSecEnforceAdminIp($modx);
        swSecEnforceBasicAuth($modx);
        if (!headers_sent()) {
            header('X-Frame-Options: DENY');
            header('Cache-Control: no-store');
        }
    }
}

function header_sent_named(string $name): bool
{
    foreach (headers_list() as $h) {
        if (stripos($h, $name . ':') === 0) {
            return true;
        }
    }
    return false;
}

function swSecEnsureCsrfCookie(): void
{
    if (!empty($_COOKIE['sw_csrf']) && strlen((string)$_COOKIE['sw_csrf']) >= 32) {
        return;
    }
    $token = bin2hex(random_bytes(32));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    setcookie('sw_csrf', $token, [
        'expires' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false, // нужен JS FormData
        'samesite' => 'Strict',
    ]);
    $_COOKIE['sw_csrf'] = $token;
}

function swSecClientIp(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

function swSecEnforceAdminIp(modX $modx): void
{
    $raw = trim((string)$modx->getOption('sw.admin_allow_ips', null, ''));
    if ($raw === '') {
        return;
    }
    $allow = array_values(array_filter(array_map('trim', explode(',', $raw))));
    $ip = swSecClientIp();
    if ($ip !== '' && in_array($ip, $allow, true)) {
        return;
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

function swSecEnforceBasicAuth(modX $modx): void
{
    $user = (string)$modx->getOption('sw.admin_basic_user', null, '');
    $pass = (string)$modx->getOption('sw.admin_basic_pass', null, '');
    if ($user === '' || $pass === '') {
        return;
    }
    $ru = (string)($_SERVER['PHP_AUTH_USER'] ?? '');
    $rp = (string)($_SERVER['PHP_AUTH_PW'] ?? '');
    if ($ru === $user && hash_equals($pass, $rp)) {
        return;
    }
    header('WWW-Authenticate: Basic realm="Studio West Admin"');
    http_response_code(401);
    echo 'Authentication required';
    exit;
}
