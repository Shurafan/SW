#!/usr/bin/env php
<?php
/**
 * Деплой swHtmlCache + anonymous_sessions=0.
 * На сервере: php /tmp/deploy-sw-html-cache.php
 */
declare(strict_types=1);

include '/var/www/studiowest.ru/core/config/config.inc.php';

$pdo = new PDO(
    "mysql:host={$database_server};dbname={$dbase};charset=utf8mb4",
    $database_user,
    $database_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$keyFile = '/tmp/plugin.swHtmlCache.php';
$code = file_get_contents($keyFile);
if ($code === false) {
    fwrite(STDERR, "missing plugin source\n");
    exit(1);
}
$code = preg_replace('/^<\?php\s*/', '', $code) ?? $code;

$pdo->prepare('UPDATE modx_system_settings SET value=? WHERE `key`=?')
    ->execute(['0', 'anonymous_sessions']);
echo "anonymous_sessions=0\n";

$name = 'swHtmlCache';
$desc = 'CWV: Cache-Control для анонимного HTML без PHP-сессии';
$st = $pdo->prepare('SELECT id FROM modx_site_plugins WHERE name=?');
$st->execute([$name]);
$id = $st->fetchColumn();

if ($id) {
    $pdo->prepare('UPDATE modx_site_plugins SET plugincode=?, description=?, disabled=0 WHERE id=?')
        ->execute([$code, $desc, $id]);
    echo "plugin update id=$id\n";
} else {
    $pdo->prepare(
        'INSERT INTO modx_site_plugins
        (source, property_preprocess, name, description, editor_type, category, cache_type, plugincode, locked, properties, disabled, moduleguid, static, static_file)
        VALUES (1, 0, ?, ?, 0, 0, 0, ?, 0, NULL, 0, \'\', 0, \'\')'
    )->execute([$name, $desc, $code]);
    $id = (int) $pdo->lastInsertId();
    echo "plugin insert id=$id\n";
}

$pdo->prepare('DELETE FROM modx_site_plugin_events WHERE pluginid=?')->execute([$id]);
$pdo->prepare(
    'INSERT INTO modx_site_plugin_events (pluginid, event, priority, propertyset) VALUES (?,?,0,0)'
)->execute([$id, 'OnWebPagePrerender']);
echo "event OnWebPagePrerender\n";

require __DIR__ . '/sw-cache-safe.php';
sw_cache_reset(['includes', 'scripts', 'system_settings', 'context_settings', 'resource', 'pdotools', 'default']);
