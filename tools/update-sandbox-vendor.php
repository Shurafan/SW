<?php
/**
 * Ежедневная проверка новых Vue / @vue/compiler-sfc / es-module-shims
 * и обновление локальных файлов в assets/teme_sw/vendor/.
 *
 * Cron (раз в сутки, 04:15):
 *   15 4 * * * /usr/bin/php /var/www/studiowest.ru/tools/update-sandbox-vendor.php >> /var/log/sw-vendor-update.log 2>&1
 */
declare(strict_types=1);

$vendorDir = getenv('SW_VENDOR_DIR')
    ?: '/var/www/studiowest.ru/public/assets/teme_sw/vendor';
$versionsFile = $vendorDir . '/versions.json';
$configFile = '/var/www/studiowest.ru/core/config/config.inc.php';
$log = static function (string $msg): void {
    echo '[' . date('c') . '] ' . $msg . PHP_EOL;
};

$packages = [
    'vue' => [
        'npm' => 'vue',
        'files' => [
            'vue/vue.global.prod.js' => 'dist/vue.global.prod.js',
            'vue/vue.esm-browser.prod.js' => 'dist/vue.esm-browser.prod.js',
        ],
    ],
    'compilerSfc' => [
        'npm' => '@vue/compiler-sfc',
        'files' => [
            'vue-compiler-sfc/compiler-sfc.esm-browser.js' => 'dist/compiler-sfc.esm-browser.js',
        ],
    ],
    'esModuleShims' => [
        'npm' => 'es-module-shims',
        'files' => [
            'es-module-shims/es-module-shims.js' => 'dist/es-module-shims.js',
        ],
    ],
];

function httpGet(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'StudioWest-vendor-updater/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code !== 200) {
            throw new RuntimeException("GET $url failed HTTP $code $err");
        }
        return $body;
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 60,
            'header' => "User-Agent: StudioWest-vendor-updater/1.0\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        throw new RuntimeException("GET $url failed");
    }
    return $body;
}

function npmLatest(string $name): string
{
    $url = 'https://registry.npmjs.org/' . str_replace('/', '%2F', $name) . '/latest';
    $json = json_decode(httpGet($url), true);
    $ver = is_array($json) ? (string) ($json['version'] ?? '') : '';
    if ($ver === '' || !preg_match('/^\d+\.\d+\.\d+/', $ver)) {
        throw new RuntimeException("bad version for $name");
    }
    return $ver;
}

function npmFile(string $name, string $version, string $path): string
{
    // unpkg mirrors npm package files by version
    $url = 'https://unpkg.com/' . $name . '@' . $version . '/' . ltrim($path, '/');
    return httpGet($url);
}

if (!is_dir($vendorDir)) {
    if (!mkdir($vendorDir, 0755, true) && !is_dir($vendorDir)) {
        throw new RuntimeException("cannot create $vendorDir");
    }
}

$current = [
    'vue' => '0.0.0',
    'compilerSfc' => '0.0.0',
    'esModuleShims' => '0.0.0',
    'cache' => '',
    'updatedAt' => '',
];
if (is_readable($versionsFile)) {
    $decoded = json_decode((string) file_get_contents($versionsFile), true);
    if (is_array($decoded)) {
        $current = array_merge($current, $decoded);
    }
}

$changed = false;
$next = $current;

foreach ($packages as $key => $meta) {
    try {
        $latest = npmLatest($meta['npm']);
    } catch (Throwable $e) {
        $log('check fail ' . $meta['npm'] . ': ' . $e->getMessage());
        continue;
    }
    $have = (string) ($current[$key] ?? '0.0.0');
    if (version_compare($latest, $have, '<=')) {
        $log($meta['npm'] . " ok ($have)");
        continue;
    }
    $log($meta['npm'] . " $have → $latest");
    foreach ($meta['files'] as $localRel => $pkgPath) {
        $dest = $vendorDir . '/' . $localRel;
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("cannot create $dir");
        }
        $body = npmFile($meta['npm'], $latest, $pkgPath);
        if (strlen($body) < 100) {
            throw new RuntimeException("too small: $localRel");
        }
        $tmp = $dest . '.tmp';
        if (file_put_contents($tmp, $body) === false) {
            throw new RuntimeException("write fail $tmp");
        }
        rename($tmp, $dest);
        @chmod($dest, 0644);
        $log("wrote $localRel (" . strlen($body) . ' B)');
    }
    $next[$key] = $latest;
    $changed = true;
}

if (!$changed) {
    $log('no updates');
    exit(0);
}

$next['cache'] = $next['vue'] . '-' . $next['esModuleShims'];
$next['updatedAt'] = date('c');
file_put_contents(
    $versionsFile,
    json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);
$log('versions.json → ' . $next['cache']);

// Patch Sandbox template cache busters + local vue script if present
if (is_readable($configFile)) {
    include $configFile;
    $pdo = new PDO(
        "mysql:host={$database_server};dbname={$dbase};charset=utf8mb4",
        $database_user,
        $database_password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $row = $pdo->query('SELECT id, content FROM modx_site_templates WHERE id=8')->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $c = (string) $row['content'];
        $cache = preg_quote($next['cache'], '/');
        // vendorCache in SwCodeSandboxConfig
        if (preg_match('/vendorCache\s*:\s*[\'"][^\'"]*[\'"]/', $c)) {
            $c = preg_replace('/vendorCache\s*:\s*[\'"][^\'"]*[\'"]/', "vendorCache: '{$next['cache']}'", $c, 1);
        } elseif (preg_match('/SwCodeSandboxConfig\s*=\s*\{/', $c)) {
            $c = preg_replace(
                '/(SwCodeSandboxConfig\s*=\s*\{)/',
                "$1\n    vendorCache: '{$next['cache']}',",
                $c,
                1
            );
        }

        // sandboxBootUrl
        $c = preg_replace(
            "/sandboxBootUrl\s*:\s*['\"][^'\"]*['\"]/",
            "sandboxBootUrl: 'assets/teme_sw/js/sandbox-boot.js?v=" . $next['cache'] . "'",
            $c,
            1
        );

        // vue.global.prod.js?v=...
        $c = preg_replace(
            '#(vendor/vue/vue\.global\.prod\.js)(\?v=[^\'\"]+)?#',
            '$1?v=' . $next['cache'],
            $c
        );
        // code-sandbox.js?v=...
        $c = preg_replace(
            '#(js/code-sandbox\.js)(\?v=[^\'\"]+)?#',
            '$1?v=' . $next['cache'],
            $c
        );
        // remove leftover unpkg vue if still there
        $c = preg_replace(
            '#https://unpkg\.com/vue@[^"\']+/dist/vue\.global\.prod\.js#',
            'assets/teme_sw/vendor/vue/vue.global.prod.js?v=' . $next['cache'],
            $c
        );
        $pdo->prepare('UPDATE modx_site_templates SET content=? WHERE id=?')->execute([$c, (int) $row['id']]);
        $log('template 8 cache bumped');
    }
    $cacheDir = '/var/www/studiowest.ru/core/cache';
    if (is_dir($cacheDir)) {
        passthru('rm -rf ' . escapeshellarg($cacheDir . '/resource') . ' ' . escapeshellarg($cacheDir . '/scripts'));
        passthru('mkdir -p ' . escapeshellarg($cacheDir . '/resource'));
        passthru('chown -R www-data:www-data ' . escapeshellarg($cacheDir));
        $log('modx cache cleared');
    }
}

$log('done');
