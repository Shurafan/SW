#!/usr/bin/env php
<?php
/**
 * Одноразовый деплой swIndexNow: ключ, файл в public, плагин + события.
 * Запуск на сервере: php /tmp/deploy-sw-indexnow.php
 */
declare(strict_types=1);

include '/var/www/studiowest.ru/core/config/config.inc.php';

$pdo = new PDO(
    "mysql:host={$database_server};dbname={$dbase};charset=utf8mb4",
    $database_user,
    $database_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$keyFile = '/tmp/plugin.swIndexNow.php';
$code = file_get_contents($keyFile);
if ($code === false) {
    fwrite(STDERR, "missing plugin source\n");
    exit(1);
}
// MODX сам добавляет <?php при выполнении из БД — убираем открывающий тег
$code = preg_replace('/^<\?php\s*/', '', $code) ?? $code;

$key = 'a952b5e7f9e141d0894cb3abb1671318';
$publicKey = '/var/www/studiowest.ru/public/' . $key . '.txt';
file_put_contents($publicKey, $key);
chmod($publicKey, 0644);
chown($publicKey, 'www-data');
echo "key file $publicKey\n";

// system setting
$st = $pdo->prepare('SELECT COUNT(*) FROM modx_system_settings WHERE `key`=?');
$st->execute(['sw_indexnow_key']);
if ((int) $st->fetchColumn() === 0) {
    $pdo->prepare(
        'INSERT INTO modx_system_settings (`key`, value, xtype, namespace, area, editedon) VALUES (?,?,?,?,?,NOW())'
    )->execute(['sw_indexnow_key', $key, 'textfield', 'core', 'site']);
    echo "setting insert\n";
} else {
    $pdo->prepare('UPDATE modx_system_settings SET value=? WHERE `key`=?')->execute([$key, 'sw_indexnow_key']);
    echo "setting update\n";
}

$name = 'swIndexNow';
$st = $pdo->prepare('SELECT id FROM modx_site_plugins WHERE name=?');
$st->execute([$name]);
$id = $st->fetchColumn();
$desc = 'IndexNow: переобход URL при публикации/удалении + обновление sitemap.xml';

if ($id) {
    $pdo->prepare(
        'UPDATE modx_site_plugins SET plugincode=?, description=?, disabled=0, category=0 WHERE id=?'
    )->execute([$code, $desc, $id]);
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

$events = [
    'OnDocFormSave',
    'OnResourceDelete',
    'OnResourceUndelete',
    'OnResourcePublish',
    'OnResourceUnPublish',
];
$pdo->prepare('DELETE FROM modx_site_plugin_events WHERE pluginid=?')->execute([$id]);
$ins = $pdo->prepare(
    'INSERT INTO modx_site_plugin_events (pluginid, event, priority, propertyset) VALUES (?,?,0,0)'
);
foreach ($events as $ev) {
    $ins->execute([$id, $ev]);
    echo "event $ev\n";
}

// clear plugin cache
$cache = '/var/www/studiowest.ru/core/cache';
foreach (['includes', 'scripts', 'system_settings', 'context_settings'] as $d) {
    $p = "$cache/$d";
    if (is_dir($p)) {
        passthru('find ' . escapeshellarg($p) . ' -mindepth 1 -delete');
    }
}
echo "cache cleared\n";

// smoke: key URL
echo "key URL https://studiowest.ru/{$key}.txt\n";
