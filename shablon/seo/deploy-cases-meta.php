<?php
/**
 * Portfolio cases: introtext (listing blurbs) + seoTitle/seoDesc polish.
 * Does not touch images (fonSite / fonPlitka).
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

/**
 * @var array<int, array{
 *   pagetitle?: string,
 *   menutitle?: string,
 *   intro: string,
 *   description: string,
 *   seoTitle: string,
 *   seoDesc: string
 * }>
 */
$cases = [
    87 => [
        'pagetitle' => 'ЖКХ Ответы',
        'menutitle' => 'ЖКХ Ответы',
        'intro' => "<h1>ЖКХ Ответы</h1>\n<h3>Платформа для УК: обращения<br> из ГИС ЖКХ без просрочек</h3>\n[[\$switch]]",
        'description' => 'Платформа для управляющей организации: обращения из ГИС ЖКХ, черновики ответов и контроль сроков.',
        'seoTitle' => 'ЖКХ Ответы — платформа для УК и ГИС ЖКХ — Studio West',
        'seoDesc' => 'Платформа для управляющей организации: обращения из ГИС ЖКХ, готовые ответы, контроль сроков и штрафов. Продукт Studio West.',
    ],
    71 => [
        'pagetitle' => 'Рабочий календарь',
        'menutitle' => 'Рабочий календарь',
        'intro' => "<h1>Рабочий календарь</h1>\n<h3>API производственного календаря<br> для проектов и бизнеса</h3>",
        'description' => 'API производственного календаря РФ: рабочие дни, праздники, регионы, архив и прогнозы.',
        'seoTitle' => 'Рабочий календарь — API производственного календаря | Studio West',
        'seoDesc' => 'API производственного календаря для проектов и бизнеса: рабочие дни, праздники, регионы РФ, архив и прогнозы.',
    ],
    58 => [
        'pagetitle' => 'Быстрая печать',
        'menutitle' => 'Быстрая печать',
        'intro' => "<h1>Быстрая печать</h1>\n<h3>Печать и сохранение документов<br> с сайта без диалога браузера</h3>\n[[\$switch]]",
        'description' => 'Скрипт печати и сохранения договоров и спецификаций прямо со страницы сайта.',
        'seoTitle' => 'Скрипт печати документов для сайта — Быстрая печать | Studio West',
        'seoDesc' => 'Печать и сохранение договоров и спецификаций с сайта — удобнее стандартного диалога браузера.',
    ],
    41 => [
        'pagetitle' => 'ASnet',
        'menutitle' => 'ASnet',
        'intro' => "<h1>ASnet</h1>\n<h3>Сайт провайдера: тарифы из админки,<br> оплата без кабинета, заявки</h3>",
        'description' => 'Сайт интернет-провайдера с нуля: тарифы, быстрая оплата и заявки на подключение.',
        'seoTitle' => 'Сайт интернет-провайдера с оплатой без кабинета — ASnet | Studio West',
        'seoDesc' => 'Сайт с нуля для провайдера: тарифы из админки, быстрая оплата, заявки на подключение.',
    ],
    40 => [
        'pagetitle' => 'ЛК Аванта',
        'menutitle' => 'ЛК Аванта',
        'intro' => "<h1>ЛК Аванта</h1>\n<h3>Кабинет абонента: счета, заявки<br> и мобильная вёрстка</h3>",
        'description' => 'Личный кабинет провайдера на WordPress: счета, заявки и мобильная вёрстка.',
        'seoTitle' => 'Личный кабинет провайдера на WordPress — Аванта Телеком | Studio West',
        'seoDesc' => 'Кабинет абонента: счета, заявки, мобильная вёрстка и статистика по страницам. Кейс для avanta-telecom.ru.',
    ],
    33 => [
        'pagetitle' => 'Work Examiner',
        'menutitle' => 'Work Examiner',
        'intro' => "<h1>Work Examiner</h1>\n<h3>Редизайн лендингов под Retina<br> без слома фирменного стиля</h3>",
        'description' => 'Частичный редизайн главной и посадочных Work Examiner под экраны Retina.',
        'seoTitle' => 'Редизайн лендингов Work Examiner под Retina | Studio West',
        'seoDesc' => 'Обновление главной и посадочных Work Examiner под экраны Mac. Частичный редизайн без слома стиля.',
    ],
    28 => [
        'pagetitle' => 'TelegramStock',
        'menutitle' => 'TelegramStock',
        'intro' => "<h1>TelegramStock</h1>\n<h3>Лендинг сервиса: тарифы<br> и приём платежей</h3>",
        'description' => 'Лендинг сервиса с тарифами и оплатой для зарубежной аудитории.',
        'seoTitle' => 'Лендинг TelegramStock: тарифы и оплата | Studio West',
        'seoDesc' => 'Структура, тарифы и приём платежей для зарубежной аудитории сервиса.',
    ],
    21 => [
        'pagetitle' => 'Germes Group',
        'menutitle' => 'Germes Group',
        'intro' => "<h1>Germes Group</h1>\n<h3>Мультирегиональный сайт<br> и внутренняя оптимизация</h3>",
        'description' => 'Мультирегиональный сайт производителя: зоны городов, SEO-база и очистка от вирусов.',
        'seoTitle' => 'Мультирегиональный сайт Germes Group | Studio West',
        'seoDesc' => 'Зоны Москва, Севастополь, Керчь, Симферополь, Санкт-Петербург; внутренняя оптимизация и очистка от вирусов.',
    ],
    27 => [
        'pagetitle' => 'HOTFIX',
        'menutitle' => 'HOTFIX',
        'intro' => "<h1>HOTFIX</h1>\n<h3>Единый шаблон и блог<br> вместо набора статических макетов</h3>",
        'description' => 'Модульный шаблон с переменными в админке и блог бренда теплоизоляции HOTFIX.',
        'seoTitle' => 'Модульный шаблон и блог HOTFIX | Studio West',
        'seoDesc' => 'Один шаблон вместо набора статических макетов, переменные в админке и блог бренда теплоизоляции HOTFIX.',
    ],
    26 => [
        'pagetitle' => 'Viaset',
        'menutitle' => 'Viaset',
        'intro' => "<h1>Viaset</h1>\n<h3>Блог с бесконечной лентой<br> и оплата через ЮKassa</h3>",
        'description' => 'Блог с бесконечной прокруткой и страницы заказа услуг с оплатой ЮKassa.',
        'seoTitle' => 'Блог и оплата через ЮKassa — Viaset | Studio West',
        'seoDesc' => 'Бесконечная прокрутка статей, страницы заказа услуг и приём оплаты. Кейс для digital-эксперта Viaset.',
    ],
    25 => [
        'pagetitle' => 'Монолит-групп',
        'menutitle' => 'Монолит',
        'intro' => "<h1>Монолит-групп</h1>\n<h3>Из лендинга — сайт с блогом<br> под продвижение</h3>",
        'description' => 'Бетон и ЖБИ: из одностраничника — сайт с блогом под продвижение в Московской области.',
        'seoTitle' => 'С лендинга в сайт с блогом — Монолит-групп | Studio West',
        'seoDesc' => 'Бетон и ЖБИ: из одностраничника сделали сайт с блогом под продвижение в Московской области.',
    ],
    24 => [
        'pagetitle' => 'Барракуда',
        'menutitle' => 'Барракуда',
        'intro' => "<h1>Барракуда</h1>\n<h3>Интернет-магазин снаряжения<br> с правками из админки</h3>",
        'description' => 'Магазин подводной охоты: каталог и правки оформления из админки.',
        'seoTitle' => 'Интернет-магазин снаряжения «Барракуда» | Studio West',
        'seoDesc' => 'Магазин подводной охоты с правками оформления из админки и сжатым бюджетом.',
    ],
    23 => [
        'pagetitle' => 'TechnicParts',
        'menutitle' => 'TechnicParts',
        'intro' => "<h1>TechnicParts</h1>\n<h3>Магазин запчастей: каталог,<br> фильтры и SEO-база</h3>",
        'description' => 'Интернет-магазин запчастей для планшетов, телефонов и ноутбуков.',
        'seoTitle' => 'Интернет-магазин запчастей TechnicParts | Studio West',
        'seoDesc' => 'Магазин запчастей для планшетов, телефонов и ноутбуков: каталог, расширенный функционал, SEO-база.',
    ],
    22 => [
        'pagetitle' => 'Panda',
        'menutitle' => 'Panda',
        'intro' => "<h1>Panda</h1>\n<h3>Редизайн и ускорение сайта<br> сервисного центра</h3>\n[[\$switch]]",
        'description' => 'Перенос со статичного WordPress, меньше нагрузки на сервер и заявки с сайта.',
        'seoTitle' => 'Редизайн и ускорение сайта сервисного центра Panda | Studio West',
        'seoDesc' => 'Перенос со статичного WordPress, меньше нагрузки на сервер, свежий дизайн и заявки с сайта.',
    ],
];

$upd = $pdo->prepare(
    'UPDATE modx_site_content
     SET pagetitle=?, menutitle=?, introtext=?, description=?, editedon=?
     WHERE id=?'
);

foreach ($cases as $id => $c) {
    $upd->execute([
        $c['pagetitle'],
        $c['menutitle'],
        $c['intro'],
        $c['description'],
        $now,
        $id,
    ]);
    setTv($pdo, 1, $id, $c['seoTitle']);
    setTv($pdo, 3, $id, $c['seoDesc']);
    echo "case $id {$c['pagetitle']} ok\n";
}

// clear MODX cache
$cache = '/var/www/studiowest.ru/core/cache';
if (is_dir($cache)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    echo "cache cleared\n";
}

echo 'done ' . count($cases) . " cases\n";
