<?php
/**
 * Week 3 SEO deploy: journal article, telecom landing, fon/TV, linking.
 * Reads HTML from /tmp/seo-week3/. Credentials from MODX config.
 */
declare(strict_types=1);

include '/var/www/studiowest.ru/core/config/config.inc.php';

$pdo = new PDO(
    "mysql:host={$database_server};dbname={$dbase};charset=utf8mb4",
    $database_user,
    $database_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$dir = '/tmp/seo-week3';
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

// --- journal: landing vs corporate ---
$jBase = $pdo->query('SELECT * FROM modx_site_content WHERE id=85')->fetch(PDO::FETCH_ASSOC);
$jFields = [
    'pagetitle' => 'Лендинг или корпоративный сайт',
    'longtitle' => '',
    'description' => 'Лендинг или корпоративный сайт для B2B — как выбрать',
    'alias' => 'landing-ili-korporativnyj',
    'parent' => 18,
    'isfolder' => 0,
    'introtext' => "<h1>Лендинг или корпоративный сайт</h1>\n<h3>Как выбрать формат B2B-компании: один оффер или меню услуг</h3>",
    'content' => readHtml($dir, 'journal-landing-ili-korporativnyj.html'),
    'template' => 2,
    'menuindex' => 0,
    'searchable' => 1,
    'cacheable' => 1,
    'published' => 1,
    'deleted' => 0,
    'menutitle' => 'Лендинг или корпоративный',
    'hidemenu' => 0,
    'class_key' => 'MODX\\Revolution\\modDocument',
    'context_key' => 'web',
    'content_type' => 1,
    'uri' => 'journal/landing-ili-korporativnyj',
    'uri_override' => 1,
    'alias_visible' => 1,
    'richtext' => 1,
];
$jId = savePage($pdo, $jBase, $jFields, $now);
setTv($pdo, 1, $jId, 'Лендинг или корпоративный сайт для B2B — как выбрать | Studio West');
setTv($pdo, 3, $jId, 'Один оффер или меню услуг: когда B2B-компании нужен лендинг, а когда корпоративный сайт. Пороги и ссылки на пакеты Studio West.');
setTv($pdo, 4, $jId, 'pic/landing-ili-korporativnyj-fon.jpg');
setTv($pdo, 25, $jId, 'pic/landing-ili-korporativnyj-pl.jpg');

// bump stoimost menuindex so new article can stay near top by publishedon; set menuindex
$pdo->prepare('UPDATE modx_site_content SET menuindex=1, editedon=? WHERE id=85')->execute([$now]);

// --- telecom landing ---
$sBase = $pdo->query('SELECT * FROM modx_site_content WHERE id=13')->fetch(PDO::FETCH_ASSOC);
$tFields = [
    'pagetitle' => 'Сайты для telecom',
    'longtitle' => '',
    'description' => 'Сайт и кабинет для провайдеров связи',
    'alias' => 'telecom',
    'parent' => 10,
    'isfolder' => 0,
    'introtext' => "<h1>Сайты и кабинеты для telecom</h1>\n<h2>Тарифы, оплата, заявки и ЛК абонента</h2>",
    'content' => readHtml($dir, 'telecom.html'),
    'template' => 2,
    'menuindex' => 10,
    'searchable' => 1,
    'cacheable' => 1,
    'published' => 1,
    'deleted' => 0,
    'menutitle' => 'Telecom',
    'hidemenu' => 1,
    'class_key' => 'MODX\\Revolution\\modDocument',
    'context_key' => 'web',
    'content_type' => 1,
    'uri' => 'services/telecom',
    'uri_override' => 1,
    'alias_visible' => 1,
    'richtext' => 1,
];
$tId = savePage($pdo, $sBase, $tFields, $now);
setTv($pdo, 1, $tId, 'Сайт и кабинет для telecom / провайдеров — Studio West');
setTv($pdo, 3, $tId, 'Витрина тарифов, быстрая оплата, заявки и личный кабинет абонента. Кейсы ASnet и Аванта. Веб-студия в Краснодаре.');
setTv($pdo, 4, $tId, 'pic/telecom-fon.jpg');
setTv($pdo, 25, $tId, 'pic/telecom-pl.jpg');

// --- update linked hub pages ---
$pdo->prepare('UPDATE modx_site_content SET content=?, editedon=? WHERE id=13')
    ->execute([readHtml($dir, 'apps.html'), $now]);
echo "apps content ok\n";
$pdo->prepare('UPDATE modx_site_content SET content=?, editedon=? WHERE id=11')
    ->execute([readHtml($dir, 'sites.html'), $now]);
echo "sites content ok\n";
$pdo->prepare('UPDATE modx_site_content SET content=?, editedon=? WHERE id=85')
    ->execute([readHtml($dir, 'journal-stoimost-sajta-krasnodar.html'), $now]);
echo "stoimost content ok\n";

// --- portfolio case linking ---
$caseBlurb41 = "\n<p>Нужен похожий контур для провайдера — смотрите посадочную <a href=\"/services/telecom\">сайты для telecom</a>, пакеты <a href=\"/services/sites\">сайтов</a> и <a href=\"/services/apps\">приложений</a>.</p>\n";
$caseBlurb40 = "\n<p>Кабинеты и Service Desk — линия <a href=\"/services/apps\">приложений</a>; витрина тарифов и оплата — <a href=\"/services/telecom\">telecom</a>.</p>\n";
foreach ([41 => $caseBlurb41, 40 => $caseBlurb40] as $rid => $blurb) {
    $content = $pdo->query("SELECT content FROM modx_site_content WHERE id=$rid")->fetchColumn();
    if (!is_string($content)) {
        continue;
    }
    if (str_contains($content, 'services/telecom')) {
        echo "case $rid link skip\n";
        continue;
    }
    $pdo->prepare('UPDATE modx_site_content SET content=?, editedon=? WHERE id=?')
        ->execute([$content . $blurb, $now, $rid]);
    echo "case $rid link ok\n";
}

// --- contacts + philosophy fon ---
setTv($pdo, 4, 17, 'pic/contacts-fon.jpg');
setTv($pdo, 4, 15, 'pic/philosophy-fon.jpg');
$contacts = $pdo->query('SELECT introtext FROM modx_site_content WHERE id=17')->fetchColumn();
if (is_string($contacts) && str_contains($contacts, 'pic/krasnodar.jpg')) {
    $contacts = str_replace('pic/krasnodar.jpg', 'pic/contacts-fon.jpg', $contacts);
    $pdo->prepare('UPDATE modx_site_content SET introtext=?, editedon=? WHERE id=17')
        ->execute([$contacts, $now]);
    echo "contacts intro fon ok\n";
} else {
    echo "contacts intro skip\n";
}

echo "done journal=$jId telecom=$tId\n";
