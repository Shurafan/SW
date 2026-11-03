<?php
/**
 * swIndexNow — переобход URL через IndexNow при публикации / правке / удалении.
 *
 * События: OnDocFormSave, OnResourceDelete, OnResourceUndelete,
 *          OnResourcePublish, OnResourceUnPublish
 *
 * Также пересобирает статичный /sitemap.xml (nginx отдаёт его без PHP).
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

$key = (string) $modx->getOption('sw_indexnow_key', null, '');
$host = 'studiowest.ru';
$siteUrl = 'https://' . $host;
$keyLocation = $siteUrl . '/' . $key . '.txt';

if ($key === '') {
    return;
}

$event = $modx->event->name ?? '';
$skipIds = [20, 31]; // ajaxress, sitemap

/** Абсолютный URL ресурса. */
$resourceUrl = static function ($modx, $res, string $siteUrl): string {
    if ((int) $res->get('id') === (int) $modx->getOption('site_start')) {
        return rtrim($siteUrl, '/') . '/';
    }
    $uri = trim((string) $res->get('uri'), '/');
    if ($uri === '') {
        $uri = trim((string) $res->get('alias'), '/');
    }
    if ($uri === '') {
        return '';
    }
    return rtrim($siteUrl, '/') . '/' . $uri;
};

/**
 * Отправка списка URL в IndexNow (Bing/Yandex и др.).
 */
$sendIndexNow = static function (array $urls, string $host, string $key, string $keyLocation) use ($modx): void {
    $urls = array_values(array_unique(array_filter($urls)));
    if (!$urls) {
        return;
    }

    $payload = json_encode([
        'host' => $host,
        'key' => $key,
        'keyLocation' => $keyLocation,
        'urlList' => $urls,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Bing принимает стабильно. Яндекс с этого VPS рвёт соединение (Empty reply / HTTP2) —
    // всё равно пробуем yandex.ru коротко, без блокировки сохранения.
    $endpoints = [
        'https://api.indexnow.org/indexnow',
        'https://www.bing.com/indexnow',
        'https://yandex.ru/indexnow',
    ];

    $log = [];
    $ok = 0;
    foreach ($endpoints as $endpoint) {
        $isYandex = str_contains($endpoint, 'yandex.');
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($payload),
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $isYandex ? 3 : 8,
            CURLOPT_CONNECTTIMEOUT => $isYandex ? 2 : 4,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'StudioWest-IndexNow/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            $ok++;
        }
        $log[] = $endpoint . ' => HTTP ' . $code . ($err ? " ($err)" : '') . ' ' . substr((string) $body, 0, 120);
    }

    // log_level на проде = ERROR(1), INFO не пишется — логируем как ERROR.
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        '[swIndexNow] ' . ($ok > 0 ? 'ok' : 'FAIL') . ' ' . implode('; ', $urls) . ' | ' . implode(' | ', $log),
        ['target' => 'FILE']
    );
};

/**
 * Пересборка статичного sitemap.xml.
 * Не перезаписывает файл пустым urlset (иначе IndexNow затирает карту при сбое pdoSitemap).
 */
$rebuildSitemap = static function () use ($modx): void {
    $xml = $modx->runSnippet('swSitemap');
    if (!is_string($xml) || !str_contains($xml, '<urlset')) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[swIndexNow] sitemap rebuild skipped: empty/invalid swSitemap', ['target' => 'FILE']);
        return;
    }
    if (substr_count($xml, '<loc>') < 1) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[swIndexNow] sitemap rebuild skipped: urlset without <loc>', ['target' => 'FILE']);
        return;
    }
    $path = MODX_BASE_PATH . 'sitemap.xml';
    if (@file_put_contents($path, $xml) !== false) {
        @chmod($path, 0644);
    }
};

/** @var object|null $resource — из события OnDocFormSave / OnResource* */
if (!isset($resource) || !is_object($resource) || !method_exists($resource, 'get')) {
    if (!empty($scriptProperties['id'])) {
        $resource = $modx->getObject('modResource', (int) $scriptProperties['id']);
    } else {
        $resource = null;
    }
}

$urls = [];
$doSitemap = false;

switch ($event) {
    case 'OnDocFormSave':
    case 'OnResourcePublish':
    case 'OnResourceUnPublish':
    case 'OnResourceUndelete':
    case 'OnResourceDelete':
        if (!$resource) {
            return;
        }
        $id = (int) $resource->get('id');
        if (in_array($id, $skipIds, true)) {
            $doSitemap = true;
            break;
        }
        $url = $resourceUrl($modx, $resource, $siteUrl);
        if ($url === '') {
            $doSitemap = true;
            break;
        }

        // noindex-черновики не шлём при сохранении; удаление/снятие с публикации — шлём.
        $searchable = (int) $resource->get('searchable') === 1;
        $published = (int) $resource->get('published') === 1;
        $deleted = (int) $resource->get('deleted') === 1;

        if ($event === 'OnResourceDelete' || $event === 'OnResourceUnPublish' || $deleted || !$published) {
            $urls[] = $url;
        } elseif ($searchable && $published) {
            $urls[] = $url;
        }

        $cacheKey = 'sw_indexnow_' . md5($url);
        if ($urls && $modx->cacheManager->get($cacheKey)) {
            $urls = [];
        } elseif ($urls) {
            // PHP 8+: set() принимает $var по ссылке — литерал нельзя
            $cacheFlag = 1;
            $modx->cacheManager->set($cacheKey, $cacheFlag, 60);
        }

        $doSitemap = true;
        break;

    default:
        return;
}

if ($urls) {
    $sendIndexNow($urls, $host, $key, $keyLocation);
}

if ($doSitemap) {
    $rebuildSitemap();
}
