<?php
/**
 * Деплой security hardening: plugins + settings.
 * На сервере: php81 /tmp/deploy-security-hardening.php
 */
declare(strict_types=1);

include '/home/srv86525/htdocs/core/config/config.inc.php';

$pdo = new PDO(
    "mysql:host={$database_server};dbname={$dbase};charset=utf8mb4",
    $database_user,
    $database_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function upsertPlugin(PDO $pdo, string $name, string $desc, string $code, array $events): void
{
    $code = preg_replace('/^<\?php\s*/', '', $code) ?? $code;
    $st = $pdo->prepare('SELECT id FROM modx_site_plugins WHERE name=?');
    $st->execute([$name]);
    $id = $st->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE modx_site_plugins SET plugincode=?, description=?, disabled=0 WHERE id=?')
            ->execute([$code, $desc, $id]);
        echo "plugin update $name id=$id\n";
    } else {
        $pdo->prepare(
            'INSERT INTO modx_site_plugins
            (source, property_preprocess, name, description, editor_type, category, cache_type, plugincode, locked, properties, disabled, moduleguid, static, static_file)
            VALUES (1, 0, ?, ?, 0, 0, 0, ?, 0, NULL, 0, \'\', 0, \'\')'
        )->execute([$name, $desc, $code]);
        $id = (int)$pdo->lastInsertId();
        echo "plugin insert $name id=$id\n";
    }
    $pdo->prepare('DELETE FROM modx_site_plugin_events WHERE pluginid=?')->execute([$id]);
    $ins = $pdo->prepare('INSERT INTO modx_site_plugin_events (pluginid, event, priority, propertyset) VALUES (?,?,0,0)');
    foreach ($events as $ev) {
        $ins->execute([$id, $ev]);
        echo "  event $ev\n";
    }
}

function upsertSetting(PDO $pdo, string $key, string $value, string $xtype, string $area, string $ns = 'core'): void
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM modx_system_settings WHERE `key`=?');
    $st->execute([$key]);
    if ((int)$st->fetchColumn() > 0) {
        $pdo->prepare('UPDATE modx_system_settings SET value=? WHERE `key`=?')->execute([$value, $key]);
        echo "setting update $key\n";
    } else {
        $pdo->prepare(
            'INSERT INTO modx_system_settings (`key`, value, xtype, namespace, area, editedon)
             VALUES (?, ?, ?, ?, ?, UNIX_TIMESTAMP())'
        )->execute([$key, $value, $xtype, $ns, $area]);
        echo "setting insert $key\n";
    }
}

$secCode = file_get_contents('/tmp/plugin.swSecurityHeaders.php');
if ($secCode === false) {
    fwrite(STDERR, "missing /tmp/plugin.swSecurityHeaders.php\n");
    exit(1);
}
upsertPlugin(
    $pdo,
    'swSecurityHeaders',
    'Security headers, CSRF cookie, admin Basic/IP guard',
    $secCode,
    ['OnHandleRequest', 'OnWebPagePrerender', 'OnManagerPageBeforeRender']
);

$htmlCache = '/tmp/plugin.swHtmlCache.php';
if (is_file($htmlCache)) {
    upsertPlugin(
        $pdo,
        'swHtmlCache',
        'CWV Cache-Control + security headers for anonymous HTML',
        file_get_contents($htmlCache),
        ['OnWebPagePrerender']
    );
}

$basicUser = getenv('SW_ADMIN_BASIC_USER') ?: 'swadmin';
$basicPass = getenv('SW_ADMIN_BASIC_PASS') ?: '';
if ($basicPass === '') {
    fwrite(STDERR, "SW_ADMIN_BASIC_PASS required\n");
    exit(1);
}

upsertSetting($pdo, 'sw.admin_basic_user', $basicUser, 'textfield', 'sw.security');
upsertSetting($pdo, 'sw.admin_basic_pass', $basicPass, 'text-password', 'sw.security');
upsertSetting($pdo, 'sw.admin_allow_ips', '', 'textfield', 'sw.security');
upsertSetting($pdo, 'pay_token_api', 'https://176.126.103.96:3000', 'textfield', 'pay');

// clear caches
$cache = '/home/srv86525/htdocs/core/cache';
foreach (['includes', 'scripts', 'system_settings', 'context_settings', 'resource', 'lexicon_topics'] as $dir) {
    $path = $cache . '/' . $dir;
    if (is_dir($path)) {
        passthru('rm -rf ' . escapeshellarg($path));
        echo "cleared $dir\n";
    }
}

echo "DONE\n";
