#!/usr/bin/env php
<?php
/**
 * Три статьи журнала кластера ЖКХ Ответы:
 * shtrafy-gis-zhkh-uk, domovye-chaty-gis-zhkh, chernoviki-otvetov-uk-gis
 *
 * На сервере: php /tmp/deploy-jkh-journal3.php
 * HTML и seoFaqJson — из /tmp/seo-jkh-journal3/
 */
declare(strict_types=1);

include '/var/www/studiowest.ru/core/config/config.inc.php';

$pdo = new PDO(
    "mysql:host={$database_server};dbname={$dbase};charset=utf8mb4",
    $database_user,
    $database_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$dir = '/tmp/seo-jkh-journal3';
$now = time();

function readHtml(string $dir, string $name): string
{
    $p = $dir . '/' . $name;
    $s = file_get_contents($p);
    if ($s === false) {
        throw new RuntimeException('missing ' . $p);
    }
    // strip leading HTML comment meta block
    $s = preg_replace('/^\s*<!--.*?-->\s*/s', '', $s) ?? $s;
    return $s;
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

function findByAlias(PDO $pdo, int $parent, string $alias): ?array
{
    $st = $pdo->prepare(
        'SELECT * FROM modx_site_content WHERE parent=? AND alias=? AND deleted=0 LIMIT 1'
    );
    $st->execute([$parent, $alias]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function savePage(PDO $pdo, array $base, array $fields, int $now): int
{
    unset($base['id']);
    foreach ($fields as $k => $v) {
        $base[$k] = $v;
    }
    $base['editedon'] = $now;
    if (empty($base['createdon'])) {
        $base['createdon'] = $now;
    }
    if (empty($base['publishedon'])) {
        $base['publishedon'] = $now;
        $base['publishedby'] = 1;
    }
    $existing = findByAlias($pdo, (int) $base['parent'], (string) $base['alias']);
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
        $vals[] = $id;
        $pdo->prepare('UPDATE modx_site_content SET ' . implode(',', $sets) . ' WHERE id=?')
            ->execute($vals);
        echo "page {$base['alias']} update id=$id\n";
        return $id;
    }
    $cols = array_keys($base);
    $place = implode(',', array_fill(0, count($cols), '?'));
    $quoted = '`' . implode('`,`', $cols) . '`';
    $pdo->prepare("INSERT INTO modx_site_content ($quoted) VALUES ($place)")
        ->execute(array_values($base));
    $id = (int) $pdo->lastInsertId();
    echo "page {$base['alias']} insert id=$id\n";
    return $id;
}

$jBase = $pdo->query('SELECT * FROM modx_site_content WHERE id=93')->fetch(PDO::FETCH_ASSOC);
if (!$jBase) {
    throw new RuntimeException('missing template resource 93');
}

// bump existing JKH articles near top of journal
$pdo->prepare('UPDATE modx_site_content SET menuindex=?, editedon=? WHERE id=92')->execute([0, $now]);
$pdo->prepare('UPDATE modx_site_content SET menuindex=?, editedon=? WHERE id=93')->execute([1, $now]);

$articles = [
    [
        'file' => 'journal-shtrafy-gis-zhkh-uk.html',
        'alias' => 'shtrafy-gis-zhkh-uk',
        'pagetitle' => 'Штрафы УК за просрочку ответа в ГИС ЖКХ',
        'menutitle' => 'Штрафы за просрочку ГИС',
        'description' => 'Штрафы и риски УК при срыве срока ответа в ГИС ЖКХ',
        'longtitle' => 'Штрафы УК за просрочку ответа в ГИС ЖКХ',
        'introtext' => "<h1>Штрафы УК за просрочку ответа в ГИС ЖКХ</h1>\n<h3>КоАП, лицензия и почему «потерялось в Excel» дорого</h3>",
        'seoTitle' => 'Штрафы УК за просрочку ответа в ГИС ЖКХ | Studio West',
        'seoDesc' => 'Какие штрафы грозят УК за срыв срока ответа жителю в ГИС ЖКХ: КоАП, лицензия, должностные лица. Как очередь и черновики снижают риск — ЖКХ Ответы.',
        'menuindex' => 2,
    ],
    [
        'file' => 'journal-domovye-chaty-gis-zhkh.html',
        'alias' => 'domovye-chaty-gis-zhkh',
        'pagetitle' => 'Домовые чаты и ГИС ЖКХ',
        'menutitle' => 'Домовые чаты и ГИС',
        'description' => 'Одна очередь обращений: чаты домов и ГИС ЖКХ',
        'longtitle' => 'Домовые чаты и ГИС ЖКХ: одна очередь обращений',
        'introtext' => "<h1>Домовые чаты и ГИС ЖКХ</h1>\n<h3>Одна очередь вместо копипаста из мессенджеров</h3>",
        'seoTitle' => 'Домовые чаты и ГИС ЖКХ: одна очередь обращений | Studio West',
        'seoDesc' => 'MAX, WhatsApp, «Госуслуги Дом» и ГИС ЖКХ — как УК не плодить дубли и не терять срок официального ответа. Одна очередь и массовые посты — ЖКХ Ответы.',
        'menuindex' => 3,
    ],
    [
        'file' => 'journal-chernoviki-otvetov-uk-gis.html',
        'alias' => 'chernoviki-otvetov-uk-gis',
        'pagetitle' => 'Черновики ответов УК в ГИС ЖКХ',
        'menutitle' => 'Черновики ответов УК',
        'description' => 'Черновики ответов УК: регламент, данные дома и правка человеком',
        'longtitle' => 'Черновики ответов УК в ГИС ЖКХ: регламент и ИИ',
        'introtext' => "<h1>Черновики ответов УК в ГИС ЖКХ</h1>\n<h3>Регламент, данные дома и правка человеком — не чат-бот «сам отправил»</h3>",
        'seoTitle' => 'Черновики ответов УК в ГИС ЖКХ: регламент и ИИ | Studio West',
        'seoDesc' => 'Как УК готовит ответ жителю в ГИС быстрее: шаблоны, регламенты дома и черновик с проверкой человеком. Без автоотправки «в никуда» — ЖКХ Ответы.',
        'menuindex' => 4,
    ],
];

$ids = [];
foreach ($articles as $a) {
    $fields = [
        'pagetitle' => $a['pagetitle'],
        'longtitle' => $a['longtitle'],
        'description' => $a['description'],
        'alias' => $a['alias'],
        'parent' => 18,
        'isfolder' => 0,
        'introtext' => $a['introtext'],
        'content' => readHtml($dir, $a['file']),
        'template' => 2,
        'menuindex' => $a['menuindex'],
        'searchable' => 1,
        'cacheable' => 1,
        'published' => 1,
        'deleted' => 0,
        'menutitle' => $a['menutitle'],
        'hidemenu' => 0,
        'class_key' => $jBase['class_key'],
        'context_key' => 'web',
        'content_type' => 1,
        'uri' => 'journal/' . $a['alias'],
        'uri_override' => 1,
        'alias_visible' => 1,
        'richtext' => 1,
        'show_in_tree' => 1,
    ];
    $id = savePage($pdo, $jBase, $fields, $now);
    $ids[$a['alias']] = $id;
    setTv($pdo, 1, $id, $a['seoTitle']);
    setTv($pdo, 3, $id, $a['seoDesc']);
    setTv($pdo, 4, $id, 'pic/jkh-otvety-fon.jpg');
    setTv($pdo, 25, $id, 'pic/jkh-otvety-pl.jpg');
}

// seoFaqJson snippet
$faqFile = $dir . '/seoFaqJson.php';
if (is_file($faqFile)) {
    $code = file_get_contents($faqFile);
    $code = preg_replace('/^<\?php\s*/', '', $code) ?? $code;
    $pdo->prepare('UPDATE modx_site_snippets SET snippet=? WHERE name=?')
        ->execute([$code, 'seoFaqJson']);
    echo "seoFaqJson updated\n";
}

// Сброс кэша + sitemap только от www-data (см. tools/sw-cache-safe.php).
// Root-CLI + $modx->runSnippet → includes/pdotools от root → пустое боковое меню/листинг.
$safe = '/tmp/sw-cache-safe.php';
if (!is_file($safe)) {
    $safe = dirname(__DIR__, 2) . '/tools/sw-cache-safe.php';
}
require $safe;
sw_cache_reset();
sw_rebuild_sitemap_as_www();

$sitemap = '/var/www/studiowest.ru/public/sitemap.xml';
$xml = is_file($sitemap) ? (string) file_get_contents($sitemap) : '';
foreach ($ids as $alias => $rid) {
    $ok = $xml !== '' && strpos($xml, '/' . $alias) !== false;
    echo 'sitemap ' . $alias . ($ok ? " OK\n" : " MISSING\n");
}

echo "done ids=" . json_encode($ids, JSON_UNESCAPED_UNICODE) . "\n";
