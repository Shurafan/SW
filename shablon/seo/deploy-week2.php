<?php
/**
 * Week 2 SEO deploy. Reads HTML from /tmp/seo-week2/.
 * DB credentials from MODX config.inc.php.
 */
declare(strict_types=1);

include '/var/www/studiowest.ru/core/config/config.inc.php';

$pdo = new PDO(
    "mysql:host={$database_server};dbname={$dbase};charset=utf8mb4",
    $database_user,
    $database_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$dir = '/tmp/seo-week2';
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

function upsertSnippet(PDO $pdo, string $name, string $code, string $desc): void
{
    if (str_starts_with($code, "<?php")) {
        $code = preg_replace('/^<\?php\s*/', '', $code) ?? $code;
    }
    $st = $pdo->prepare('SELECT id FROM modx_site_snippets WHERE name=?');
    $st->execute([$name]);
    $id = $st->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE modx_site_snippets SET snippet=?, description=? WHERE id=?')
            ->execute([$code, $desc, $id]);
        echo "snippet $name update\n";
        return;
    }
    $pdo->prepare(
        'INSERT INTO modx_site_snippets
        (source, property_preprocess, name, description, editor_type, category,
         cache_type, snippet, locked, properties, moduleguid, static, static_file)
        VALUES (1, 0, ?, ?, 0, 0, 0, ?, 0, NULL, \'\', 0, \'\')'
    )->execute([$name, $desc, $code]);
    echo "snippet $name insert\n";
}

function upsertChunk(PDO $pdo, string $name, string $html, string $desc): void
{
    $st = $pdo->prepare('SELECT id FROM modx_site_htmlsnippets WHERE name=?');
    $st->execute([$name]);
    $id = $st->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE modx_site_htmlsnippets SET snippet=?, description=? WHERE id=?')
            ->execute([$html, $desc, $id]);
        echo "chunk $name update\n";
        return;
    }
    $pdo->prepare(
        'INSERT INTO modx_site_htmlsnippets
        (source, property_preprocess, name, description, editor_type, category,
         cache_type, snippet, locked, properties, static, static_file)
        VALUES (1, 0, ?, ?, 0, 0, 0, ?, 0, NULL, 0, \'\')'
    )->execute([$name, $desc, $html]);
    echo "chunk $name insert\n";
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

$base = $pdo->query('SELECT * FROM modx_site_content WHERE id=11')->fetch(PDO::FETCH_ASSOC);
$pages = [
    [
        'alias' => 'landing',
        'uri' => 'services/landing',
        'pagetitle' => 'Лендинг',
        'menutitle' => 'Лендинг',
        'description' => 'Создание лендинга в Краснодаре от 50 000 ₽',
        'menuindex' => 7,
        'file' => 'landing.html',
        'intro' => "<h1>Создание лендинга в Краснодаре</h1>\n<h2>Посадочная страница под ключ от 50 000 ₽</h2>",
        'title' => 'Создание лендинга в Краснодаре от 50 000 ₽ — Studio West',
        'desc' => 'Посадочная страница под ключ: прототип, дизайн, формы, аналитика. Срок 2–4 недели. Веб-студия в Краснодаре, офис на Византийской, 5.',
    ],
    [
        'alias' => 'corporate',
        'uri' => 'services/corporate',
        'pagetitle' => 'Корпоративный сайт',
        'menutitle' => 'Корпоративный сайт',
        'description' => 'Корпоративный сайт в Краснодаре от 150 000 ₽',
        'menuindex' => 8,
        'file' => 'corporate.html',
        'intro' => "<h1>Корпоративный сайт в Краснодаре</h1>\n<h2>Многостраничник под ключ от 150 000 ₽</h2>",
        'title' => 'Корпоративный сайт в Краснодаре от 150 000 ₽ — Studio West',
        'desc' => 'Многостраничный сайт компании: разделы, новости, админка, интеграции. Разработка под ключ в Краснодаре и Орле.',
    ],
    [
        'alias' => 'shop',
        'uri' => 'services/shop',
        'pagetitle' => 'Интернет-магазин',
        'menutitle' => 'Интернет-магазин',
        'description' => 'Интернет-магазин под ключ от 300 000 ₽',
        'menuindex' => 9,
        'file' => 'shop.html',
        'intro' => "<h1>Интернет-магазин под ключ</h1>\n<h2>Каталог, корзина и оплата от 300 000 ₽</h2>",
        'title' => 'Интернет-магазин под ключ от 300 000 ₽ — Studio West',
        'desc' => 'Каталог, корзина, оплата и фильтры. Интернет-магазин с нуля в Краснодаре: админка, касса, цели в Метрике.',
    ],
];

$ids = [];
foreach ($pages as $p) {
    $fields = [
        'pagetitle' => $p['pagetitle'],
        'longtitle' => '',
        'description' => $p['description'],
        'alias' => $p['alias'],
        'parent' => 10,
        'isfolder' => 0,
        'introtext' => $p['intro'],
        'content' => readHtml($dir, $p['file']),
        'template' => 2,
        'menuindex' => $p['menuindex'],
        'searchable' => 1,
        'cacheable' => 1,
        'published' => 1,
        'deleted' => 0,
        'menutitle' => $p['menutitle'],
        'hidemenu' => 1,
        'class_key' => 'MODX\\Revolution\\modDocument',
        'context_key' => 'web',
        'content_type' => 1,
        'uri' => $p['uri'],
        'uri_override' => 1,
        'alias_visible' => 1,
        'richtext' => 1,
    ];
    $id = savePage($pdo, $base, $fields, $now);
    $ids[$p['alias']] = $id;
    setTv($pdo, 1, $id, $p['title']);
    setTv($pdo, 3, $id, $p['desc']);
}

$updates = [
    11 => [
        'introtext' => "<h1>Создание сайтов в Краснодаре</h1>\n<h2>Лендинг, корпоративный сайт, интернет-магазин</h2>",
        'content' => readHtml($dir, 'sites.html'),
        'pagetitle' => 'Сайты',
    ],
    9 => [
        'introtext' => "<h1>Стоимость создания сайта</h1>\n<h2>Пакеты от 50 000 ₽, смета после брифа</h2>",
        'content' => readHtml($dir, 'prices.html'),
        'pagetitle' => 'Цены',
    ],
    12 => [
        'introtext' => "<h1>Подготовка сайта к продвижению</h1>\n<h2>Техническая база для SEO. Рекламу в кабинетах не ведём.</h2>",
        'content' => readHtml($dir, 'promotion.html'),
        'pagetitle' => 'Продвижение',
    ],
];
foreach ($updates as $rid => $fields) {
    $pdo->prepare(
        'UPDATE modx_site_content SET introtext=?, content=?, pagetitle=?, editedon=? WHERE id=?'
    )->execute([$fields['introtext'], $fields['content'], $fields['pagetitle'], $now, $rid]);
    echo "resource $rid content ok\n";
}

$cases = [
    58 => [
        'Скрипт печати документов для сайта — Быстрая печать | Studio West',
        'Печать и сохранение договоров и спецификаций с сайта — удобнее стандартного диалога браузера. Кейс Studio West.',
    ],
    41 => [
        'Сайт интернет-провайдера с оплатой без кабинета — ASnet | Studio West',
        'Сайт с нуля для провайдера: тарифы из админки, быстрая оплата, заявки на подключение. Кейс Studio West.',
    ],
    40 => [
        'Личный кабинет провайдера на WordPress — Аванта Телеком | Studio West',
        'Кабинет абонента: счета, заявки, мобильная вёрстка и статистика по страницам. Кейс для avanta-telecom.ru.',
    ],
    33 => [
        'Редизайн лендингов Work Examiner под Retina | Studio West',
        'Обновление главной и посадочных Work Examiner, оптимизация под экраны Mac. Частичный редизайн без слома стиля.',
    ],
    28 => [
        'Лендинг TelegramStock: тарифы и оплата | Studio West',
        'Структура, тарифы и приём платежей для зарубежной аудитории сервиса. Кейс Studio West.',
    ],
    21 => [
        'Мультирегиональный сайт Germes Group | Studio West',
        'Зоны Москва, Севастополь, Керчь, Симферополь, Санкт-Петербург, внутренняя оптимизация и очистка от вирусов.',
    ],
    27 => [
        'Модульный шаблон и блог HOTFIX | Studio West',
        'Один шаблон вместо набора статических макетов, переменные в админке и блог бренда теплоизоляции HOTFIX.',
    ],
    26 => [
        'Блог и оплата через ЮKassa — Viaset | Studio West',
        'Бесконечная прокрутка статей, страницы заказа услуг и приём оплаты. Кейс для digital-эксперта Viaset.',
    ],
    25 => [
        'С лендинга в сайт с блогом — Монолит-групп | Studio West',
        'Бетон и ЖБИ: из одностраничника сделали сайт с блогом под продвижение в Московской области.',
    ],
    24 => [
        'Интернет-магазин снаряжения «Барракуда» | Studio West',
        'Магазин подводной охоты с правками оформления из админки и сжатым бюджетом. Кейс Studio West.',
    ],
    23 => [
        'Интернет-магазин запчастей TechnicParts | Studio West',
        'Магазин запчастей для планшетов, телефонов и ноутбуков: каталог, расширенный функционал, SEO-база.',
    ],
    22 => [
        'Редизайн и ускорение сайта сервисного центра Panda | Studio West',
        'Перенос со статичного WordPress, меньше нагрузки на сервер, свежий дизайн и заявки с сайта. Кейс Studio West.',
    ],
];
foreach ($cases as $rid => $td) {
    setTv($pdo, 1, $rid, $td[0]);
    setTv($pdo, 3, $rid, $td[1]);
}
echo "cases tvs " . count($cases) . "\n";

$contacts = $pdo->query('SELECT introtext FROM modx_site_content WHERE id=17')->fetchColumn();
if (is_string($contacts) && !str_contains($contacts, 'tel:+79606440400')) {
    $block = <<<'HTML'
          <div>
            <h2>Связь</h2>
            <p><a href="tel:+79606440400">+7 960 644-04-00</a><br>
            <a href="https://t.me/studio_west">Telegram</a><br>
            info@studiowest.ru</p>
          </div>
HTML;
    $contacts = str_replace(
        "          <div>\r\n            <h2>Реквизиты</h2>",
        $block . "\r\n          <div>\r\n            <h2>Реквизиты</h2>",
        $contacts
    );
    if (!str_contains($contacts, 'tel:+79606440400')) {
        $contacts = str_replace(
            "          <div>\n            <h2>Реквизиты</h2>",
            $block . "\n          <div>\n            <h2>Реквизиты</h2>",
            $contacts
        );
    }
    $pdo->prepare('UPDATE modx_site_content SET introtext=?, editedon=? WHERE id=17')
        ->execute([$contacts, $now]);
    echo "contacts phone " . (str_contains($contacts, 'tel:+79606440400') ? 'ok' : 'FAIL') . "\n";
} else {
    echo "contacts phone skip\n";
}

upsertChunk($pdo, 'head', readHtml($dir, 'head.html'), 'Head');
upsertSnippet(
    $pdo,
    'seoFaqJson',
    readHtml($dir, 'seoFaqJson.php'),
    'FAQPage JSON-LD для посадочных'
);

$homeTpl = file_get_contents(dirname(__DIR__) . '/Home.html');
if ($homeTpl === false) {
    $homeTpl = file_get_contents($dir . '/Home.html');
}
if (!is_string($homeTpl) || $homeTpl === '') {
    throw new RuntimeException('Home.html missing');
}
$pdo->prepare('UPDATE modx_site_templates SET content=? WHERE id=1')->execute([$homeTpl]);
$pdo->prepare('DELETE FROM modx_site_htmlsnippets WHERE name=?')->execute(['homeSeo']);
echo "template 1 inlined, chunk homeSeo removed\n";

echo "done ids " . json_encode($ids) . "\n";
