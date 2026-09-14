<?php
/**
 * Карта сайта с приоритетами под коммерцию, не под дату правок.
 * Вызов: [[pdoSitemap]] заменяется на [[!swSitemap]]
 */
$exclude = [-20, -29, -32, -37, -39];
foreach ($modx->getChildIds(45, 10) as $id) {
    $exclude[] = -((int) $id);
}

$xml = $modx->runSnippet('pdoSitemap', [
    'showHidden' => 1,
    'resources' => implode(',', $exclude),
    'forceXML' => false,
]);
if (!is_string($xml) || $xml === '') {
    return $xml;
}

$priorityFor = static function (string $url): array {
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    if ($path === '/') {
        return ['1.0', 'weekly'];
    }
    if (str_starts_with($path, '/services') || $path === '/process/prices') {
        return ['0.8', 'monthly'];
    }
    if (str_starts_with($path, '/portfolio') || $path === '/contacts') {
        return ['0.6', 'monthly'];
    }
    if (str_starts_with($path, '/process') || str_starts_with($path, '/journal')) {
        return ['0.5', 'monthly'];
    }
    # Хаб библиотеки — витрина продукта (0.5). Дочерние доки исключены выше и noindex.
    if ($path === '/component-sw' || $path === '/component-sw/') {
        return ['0.5', 'weekly'];
    }
    if (str_starts_with($path, '/component-sw')) {
        return ['0.2', 'monthly'];
    }
    return ['0.4', 'monthly'];
};

return preg_replace_callback(
    '#(<loc>)(https://studiowest\.ru[^<]*)(</loc>\s*<lastmod>[^<]+</lastmod>\s*<changefreq>)[^<]+(</changefreq>\s*<priority>)[^<]+(</priority>)#',
    static function (array $m) use ($priorityFor): string {
        [$pri, $freq] = $priorityFor($m[2]);
        return $m[1] . $m[2] . $m[3] . $freq . $m[4] . $pri . $m[5];
    },
    $xml
);
