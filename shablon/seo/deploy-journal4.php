<?php
/**
 * Journal SEO articles #3–4:
 * - почему магазин от 300 000
 * - как принимаем проект
 * Also refresh linked journal/shop HTML + TV images.
 */
declare(strict_types=1);

include '/var/www/studiowest.ru/core/config/config.inc.php';

$pdo = new PDO(
    "mysql:host={$database_server};dbname={$dbase};charset=utf8mb4",
    $database_user,
    $database_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$dir = '/tmp/seo-journal4';
$now = time();

function readHtml(string $dir, string $name): string
{
    $p = $dir . '/' . $name;
    $s = file_get_contents($p);
    if ($s === false) {
        throw new RuntimeException('missing ' . $p);
    }
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

$jBase = $pdo->query('SELECT * FROM modx_site_content WHERE id=85')->fetch(PDO::FETCH_ASSOC);
if (!$jBase) {
    throw new RuntimeException('missing template resource 85');
}

// keep SEO articles near top of journal listing
$pdo->prepare('UPDATE modx_site_content SET menuindex=?, editedon=? WHERE id=88')->execute([2, $now]); // landing vs corp
$pdo->prepare('UPDATE modx_site_content SET menuindex=?, editedon=? WHERE id=85')->execute([3, $now]); // stoimost

// --- article: why shop from 300k ---
$a1 = [
    'pagetitle' => 'Почему интернет-магазин от 300 000 ₽',
    'longtitle' => '',
    'description' => 'Почему магазин от 300 000 ₽, а не от 20 тысяч',
    'alias' => 'pochemu-magazin-ot-300',
    'parent' => 18,
    'isfolder' => 0,
    'introtext' => "<h1>Почему интернет-магазин от 300 000 ₽</h1>\n<h3>Чем касса и каталог отличаются от «кнопки купить» за 20 тысяч</h3>",
    'content' => readHtml($dir, 'journal-pochemu-magazin-ot-300.html'),
    'template' => 2,
    'menuindex' => 0,
    'searchable' => 1,
    'cacheable' => 1,
    'published' => 1,
    'deleted' => 0,
    'menutitle' => 'Почему магазин от 300 000',
    'hidemenu' => 0,
    'class_key' => 'MODX\\Revolution\\modDocument',
    'context_key' => 'web',
    'content_type' => 1,
    'uri' => 'journal/pochemu-magazin-ot-300',
    'uri_override' => 1,
    'alias_visible' => 1,
    'richtext' => 1,
];
$id1 = savePage($pdo, $jBase, $a1, $now);
setTv($pdo, 1, $id1, 'Почему интернет-магазин от 300 000 ₽, а не от 20 тысяч | Studio West');
setTv($pdo, 3, $id1, 'Из чего складывается порог магазина: каталог, корзина, оплата, админка. Чем отличаются конструктор и лендинг с кнопкой «купить». Студия в Краснодаре.');
setTv($pdo, 4, $id1, 'pic/pochemu-magazin-ot-300-fon.jpg');
setTv($pdo, 25, $id1, 'pic/pochemu-magazin-ot-300-pl.jpg');

// --- article: how we accept a project ---
$a2 = [
    'pagetitle' => 'Как принимаем проект',
    'longtitle' => '',
    'description' => 'Бриф, этапы, договор — как Studio West принимает проект',
    'alias' => 'kak-prinimaem-proekt',
    'parent' => 18,
    'isfolder' => 0,
    'introtext' => "<h1>Как принимаем проект</h1>\n<h3>Бриф, смета, договор и этапы до первого макета</h3>",
    'content' => readHtml($dir, 'journal-kak-prinimaem-proekt.html'),
    'template' => 2,
    'menuindex' => 1,
    'searchable' => 1,
    'cacheable' => 1,
    'published' => 1,
    'deleted' => 0,
    'menutitle' => 'Как принимаем проект',
    'hidemenu' => 0,
    'class_key' => 'MODX\\Revolution\\modDocument',
    'context_key' => 'web',
    'content_type' => 1,
    'uri' => 'journal/kak-prinimaem-proekt',
    'uri_override' => 1,
    'alias_visible' => 1,
    'richtext' => 1,
];
$id2 = savePage($pdo, $jBase, $a2, $now);
setTv($pdo, 1, $id2, 'Как принимаем проект: бриф, этапы, договор | Studio West');
setTv($pdo, 3, $id2, 'Что нужно на старте: бриф, оценка, договор и этапы до макета. Как Studio West фиксирует состав и оплату.');
setTv($pdo, 4, $id2, 'pic/kak-prinimaem-proekt-fon.jpg');
setTv($pdo, 25, $id2, 'pic/kak-prinimaem-proekt-pl.jpg');

// refresh linked content
$pdo->prepare('UPDATE modx_site_content SET content=?, editedon=? WHERE id=85')
    ->execute([readHtml($dir, 'journal-stoimost-sajta-krasnodar.html'), $now]);
echo "stoimost links ok\n";
$pdo->prepare('UPDATE modx_site_content SET content=?, editedon=? WHERE id=88')
    ->execute([readHtml($dir, 'journal-landing-ili-korporativnyj.html'), $now]);
echo "landing-ili links ok\n";

$shop = findByAlias($pdo, 10, 'shop');
if ($shop) {
    $pdo->prepare('UPDATE modx_site_content SET content=?, editedon=? WHERE id=?')
        ->execute([readHtml($dir, 'shop.html'), $now, (int) $shop['id']]);
    echo "shop links ok id={$shop['id']}\n";
} else {
    echo "shop skip\n";
}

// clear cache
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('/var/www/studiowest.ru/core/cache', FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $f) {
    if ($f->isDir()) {
        @rmdir($f->getPathname());
    } else {
        @unlink($f->getPathname());
    }
}
echo "cache ok\n";
echo "done magazin=$id1 prinimaem=$id2\n";
