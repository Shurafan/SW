<?php
/** ЖКХ Ответы: кабинет → платформа (content 87/92 + meta 87 + seoDesc 92). */
declare(strict_types=1);

include '/var/www/studiowest.ru/core/config/config.inc.php';

$pdo = new PDO(
    "mysql:host={$database_server};dbname={$dbase};charset=utf8mb4",
    $database_user,
    $database_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$base = '/tmp/studiowest-deploy';
$now = time();

function setTv(PDO $pdo, int $tv, int $rid, string $val): void
{
    $st = $pdo->prepare(
        'SELECT id FROM modx_site_tmplvar_contentvalues WHERE tmplvarid=? AND contentid=?'
    );
    $st->execute([$tv, $rid]);
    $id = $st->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE modx_site_tmplvar_contentvalues SET value=? WHERE id=?')
            ->execute([$val, $id]);
        return;
    }
    $pdo->prepare(
        'INSERT INTO modx_site_tmplvar_contentvalues (tmplvarid, contentid, value) VALUES (?,?,?)'
    )->execute([$tv, $rid, $val]);
}

$case87 = [
    'intro' => "<h1>ЖКХ Ответы</h1>\n<h3>Платформа для УК: обращения<br> из ГИС ЖКХ без просрочек</h3>",
    'description' => 'Платформа для управляющей организации: обращения из ГИС ЖКХ, черновики ответов и контроль сроков.',
    'seoTitle' => 'ЖКХ Ответы — платформа для УК и ГИС ЖКХ — Studio West',
    'seoDesc' => 'Платформа для управляющей организации: обращения из ГИС ЖКХ, готовые ответы, контроль сроков и штрафов. Продукт Studio West.',
];

$content87 = file_get_contents($base . '/portfolio-jkh-otvety.html');
$content92 = file_get_contents($base . '/seo/journal-gis-zhkh-bez-prosrochek.html');

$pdo->prepare(
    'UPDATE modx_site_content SET content=?, editedon=? WHERE id=87'
)->execute([$content87, $now]);

$pdo->prepare(
    'UPDATE modx_site_content SET introtext=?, description=?, editedon=? WHERE id=87'
)->execute([$case87['intro'], $case87['description'], $now]);

setTv($pdo, 1, 87, $case87['seoTitle']);
setTv($pdo, 3, 87, $case87['seoDesc']);

$pdo->prepare(
    'UPDATE modx_site_content SET content=?, editedon=? WHERE id=92'
)->execute([$content92, $now]);

setTv(
    $pdo,
    3,
    92,
    'Как управляющей компании не терять обращения жителей из ГИС ЖКХ и отвечать в срок. Платформа ЖКХ Ответы от Studio West.'
);

echo "updated 87, 92\n";

$resourceCache = '/var/www/studiowest.ru/core/cache/resource';
if (is_dir($resourceCache)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resourceCache, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    echo "resource cache cleared\n";
}

echo "done\n";
