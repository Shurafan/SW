<?php
/**
 * Component SW 1.0.4 docs pages:
 * Breadcrumb, Empty, Progress, Rate, Segmented
 * + menuindex reorder + hub "Что внутри" list.
 */
declare(strict_types=1);

include '/var/www/studiowest.ru/core/config/config.inc.php';

$pdo = new PDO(
    "mysql:host={$database_server};dbname={$dbase};charset=utf8mb4",
    $database_user,
    $database_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$now = time();
$parentId = 45;
$template = 8;
$tvFonSite = 4;
$tvFonPlitka = 25;
$fonSite = 'pic/documentation-fon.jpg';
$fonPlitka = 'pic/documentation_pl.png';

function findByAlias(PDO $pdo, int $parent, string $alias): ?array
{
    $st = $pdo->prepare(
        'SELECT * FROM modx_site_content WHERE parent=? AND alias=? AND deleted=0 LIMIT 1'
    );
    $st->execute([$parent, $alias]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

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

function upsertPage(PDO $pdo, int $parentId, array $fields, int $now, array $templateRow): int
{
    $existing = findByAlias($pdo, $parentId, $fields['alias']);
    if ($existing) {
        $id = (int) $existing['id'];
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            $sets[] = "`$k`=?";
            $vals[] = $v;
        }
        $sets[] = 'editedon=?';
        $vals[] = $now;
        $sets[] = 'editedby=?';
        $vals[] = 1;
        $vals[] = $id;
        $pdo->prepare('UPDATE modx_site_content SET ' . implode(',', $sets) . ' WHERE id=?')
            ->execute($vals);
        echo "page {$fields['alias']} update id=$id\n";
        return $id;
    }

    $row = $templateRow;
    unset($row['id']);
    foreach ($fields as $k => $v) {
        $row[$k] = $v;
    }
    $row['parent'] = $parentId;
    $row['createdon'] = $now;
    $row['editedon'] = $now;
    $row['publishedon'] = $now;
    $row['createdby'] = 1;
    $row['editedby'] = 1;
    $row['publishedby'] = 1;
    $row['deleted'] = 0;
    $row['deletedon'] = 0;
    $row['deletedby'] = 0;
    $row['uri'] = 'component-sw/' . $fields['alias'];
    $row['uri_override'] = 0;
    $row['published'] = 1;
    $row['hidemenu'] = 0;
    $row['show_in_tree'] = 1;

    $cols = array_keys($row);
    $place = implode(',', array_fill(0, count($cols), '?'));
    $quoted = '`' . implode('`,`', $cols) . '`';
    $pdo->prepare("INSERT INTO modx_site_content ($quoted) VALUES ($place)")
        ->execute(array_values($row));
    $id = (int) $pdo->lastInsertId();
    echo "page {$fields['alias']} insert id=$id\n";
    return $id;
}

$pages = [
    [
        'pagetitle' => 'Breadcrumb',
        'alias' => 'sw-breadcrumb',
        'menutitle' => '',
        'menuindex' => 4,
        'introtext' => '<h1>SwBreadcrumb</h1><h3>Крошки навигации по массиву items. Последний пункт — текущая страница.</h3>',
        'content' => '[[!NpmReadmeSection?&package=`@studio-west/component-sw`&anchor=`swbreadcrumb`&cache_ttl=`7200`]]',
        'template' => $template,
    ],
    [
        'pagetitle' => 'Empty',
        'alias' => 'sw-empty',
        'menutitle' => '',
        'menuindex' => 13,
        'introtext' => '<h1>SwEmpty</h1><h3>Пустое состояние списков и таблиц: иконка, описание и слот действий.</h3>',
        'content' => '[[!NpmReadmeSection?&package=`@studio-west/component-sw`&anchor=`swempty`&cache_ttl=`7200`]]',
        'template' => $template,
    ],
    [
        'pagetitle' => 'Progress',
        'alias' => 'sw-progress',
        'menutitle' => '',
        'menuindex' => 22,
        'introtext' => '<h1>SwProgress</h1><h3>Полоса или круг прогресса (не Slider): percentage, circle, indeterminate.</h3>',
        'content' => '[[!NpmReadmeSection?&package=`@studio-west/component-sw`&anchor=`swprogress`&cache_ttl=`7200`]]',
        'template' => $template,
    ],
    [
        'pagetitle' => 'Rate',
        'alias' => 'sw-rate',
        'menutitle' => '',
        'menuindex' => 23,
        'introtext' => '<h1>SwRate</h1><h3>Оценка звёздами с allowHalf, clearable и showScore.</h3>',
        'content' => '[[!NpmReadmeSection?&package=`@studio-west/component-sw`&anchor=`swrate`&cache_ttl=`7200`]]',
        'template' => $template,
    ],
    [
        'pagetitle' => 'Segmented',
        'alias' => 'sw-segmented',
        'menutitle' => '',
        'menuindex' => 25,
        'introtext' => '<h1>SwSegmented</h1><h3>Сегментированный переключатель — когда ButtonGroup radio мало.</h3>',
        'content' => '[[!NpmReadmeSection?&package=`@studio-west/component-sw`&anchor=`swsegmented`&cache_ttl=`7200`]]',
        'template' => $template,
    ],
];

$templateRow = $pdo->query('SELECT * FROM modx_site_content WHERE alias="sw-slider" AND deleted=0 LIMIT 1')
    ->fetch(PDO::FETCH_ASSOC);
if (!$templateRow) {
    throw new RuntimeException('missing template page sw-slider');
}

foreach ($pages as $fields) {
    $id = upsertPage($pdo, $parentId, $fields, $now, $templateRow);
    setTv($pdo, $tvFonSite, $id, $fonSite);
    setTv($pdo, $tvFonPlitka, $id, $fonPlitka);
}

// Alphabetical menuindex for all children of component-sw
$order = [
    'sw-affix',
    'sw-alert',
    'sw-backtop',
    'sw-badge',
    'sw-breadcrumb',
    'sw-button',
    'sw-button-group',
    'sw-collapse',
    'sw-dadata',
    'sw-date-picker',
    'sw-drawer',
    'sw-dropdown',
    'sw-dropdownitem',
    'sw-empty',
    'sw-file',
    'sw-form-item',
    'sw-guide',
    'sw-icon',
    'sw-input',
    'sw-menu',
    'sw-message',
    'sw-pagination',
    'sw-progress',
    'sw-rate',
    'sw-section',
    'sw-segmented',
    'sw-select',
    'sw-skeleton',
    'sw-skeleton-item',
    'sw-slider',
    'sw-switch',
    'sw-table',
    'sw-tabs',
    'tabspane',
    'sw-tag',
];

$st = $pdo->prepare('UPDATE modx_site_content SET menuindex=?, editedon=? WHERE parent=? AND alias=? AND deleted=0');
foreach ($order as $i => $alias) {
    $st->execute([$i, $now, $parentId, $alias]);
}
echo "menuindex reordered (" . count($order) . ")\n";

$hubList = 'Affix, Alert, BackTop, Badge, Breadcrumb, Button, ButtonGroup, Collapse, Dadata, DatePicker, Drawer, Dropdown, Empty, File, FormItem, Guide, Icon, Input, Menu, Message, Pagination, Progress, Rate, Section, Segmented, Select, Skeleton, Slider, Switch, Table, Tabs, Tag и связанные части';

$hub = $pdo->query('SELECT content FROM modx_site_content WHERE id=45')->fetchColumn();
if (!is_string($hub) || $hub === '') {
    throw new RuntimeException('missing hub content id=45');
}

$updated = preg_replace(
    '#(<h2>Что внутри</h2>\s*<p>В меню раздела: )(.*?)( Состав растёт)#su',
    '$1' . $hubList . '.$3',
    $hub,
    1,
    $count
);
if ($count !== 1) {
    throw new RuntimeException('hub "Что внутри" paragraph not matched');
}

$pdo->prepare('UPDATE modx_site_content SET content=?, editedon=?, editedby=1 WHERE id=45')
    ->execute([$updated, $now]);
echo "hub content updated\n";

// Clear MODX cache
$cacheDir = '/var/www/studiowest.ru/core/cache';
if (is_dir($cacheDir)) {
    passthru('rm -rf ' . escapeshellarg($cacheDir) . '/resource ' . escapeshellarg($cacheDir) . '/context_settings ' . escapeshellarg($cacheDir) . '/scripts 2>/dev/null');
    passthru('mkdir -p ' . escapeshellarg($cacheDir) . '/resource');
    passthru('chown -R www-data:www-data ' . escapeshellarg($cacheDir));
    echo "cache cleared\n";
}

echo "done\n";
