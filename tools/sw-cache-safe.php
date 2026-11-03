<?php
/**
 * Безопасный сброс MODX/pdoTools кэша для CLI-деплоев от root.
 *
 * Проблема: php от root пишет includes/scripts/pdotools → owner root,
 * php-fpm (www-data) не может туда писать → пустые Wayfinder/листинги.
 *
 * Использование:
 *   require '/path/to/sw-cache-safe.php';
 *   sw_cache_reset();                 // сброс + mkdir + chown
 *   sw_rebuild_sitemap_as_www();      // карта сайта от www-data
 *   sw_cache_chown();                 // после любого CLI MODX
 */
declare(strict_types=1);

const SW_CACHE_ROOT = '/var/www/studiowest.ru/core/cache';
const SW_CACHE_OWNER = 'www-data';
const SW_CACHE_GROUP = 'www-data';

/**
 * Каталоги, которые обычно нужно сбросить после правок ресурсов/сниппетов.
 *
 * @return list<string>
 */
function sw_cache_default_dirs(): array
{
    return [
        'resource',
        'includes',
        'scripts',
        'context_settings',
        'pdotools', // file + sitemap; иначе pdoSitemap отдаёт старый urlset
        'default',
    ];
}

/** @param list<string>|null $dirs относительно SW_CACHE_ROOT */
function sw_cache_reset(?array $dirs = null): void
{
    $dirs = $dirs ?? sw_cache_default_dirs();
    $root = SW_CACHE_ROOT;
    foreach ($dirs as $d) {
        $p = $root . '/' . $d;
        if (is_dir($p)) {
            passthru('find ' . escapeshellarg($p) . ' -mindepth 1 -delete');
        }
    }
    foreach ([
        'resource/web/resources',
        'includes',
        'scripts',
        'context_settings/web',
        'pdotools/file',
        'pdotools/sitemap',
        'default',
    ] as $d) {
        if (!is_dir("$root/$d")) {
            mkdir("$root/$d", 0775, true);
        }
    }
    sw_cache_chown();
    echo "cache reset + chown " . SW_CACHE_OWNER . "\n";
}

function sw_cache_chown(): void
{
    $root = SW_CACHE_ROOT;
    if (!is_dir($root)) {
        return;
    }
    // убрать хвосты от root-CLI (на случай mkdir до chown)
    passthru(
        'find ' . escapeshellarg($root)
        . ' \\( -user root -o -group root \\) -exec chown '
        . escapeshellarg(SW_CACHE_OWNER . ':' . SW_CACHE_GROUP)
        . ' {} +'
    );
    passthru(
        'chown -R ' . escapeshellarg(SW_CACHE_OWNER . ':' . SW_CACHE_GROUP)
        . ' ' . escapeshellarg($root)
    );
    passthru('chmod -R ug+rwX ' . escapeshellarg($root));
}

/**
 * Пересборка public/sitemap.xml от www-data.
 * Не вызывать $modx->runSnippet из root-CLI — иначе снова root-owned includes.
 */
function sw_rebuild_sitemap_as_www(): void
{
    $script = sys_get_temp_dir() . '/sw-rebuild-sitemap-' . getmypid() . '.php';
    $php = <<<'PHP'
<?php
define('MODX_API_MODE', true);
require '/var/www/studiowest.ru/public/index.php';
$modx->initialize('web');
$xml = $modx->runSnippet('swSitemap');
if (!is_string($xml) || substr_count($xml, '<loc>') < 1) {
    fwrite(STDERR, "sitemap EMPTY\n");
    exit(1);
}
$path = MODX_BASE_PATH . 'sitemap.xml';
file_put_contents($path, $xml);
@chmod($path, 0644);
echo 'sitemap urls=' . substr_count($xml, '<loc>') . "\n";
PHP;
    file_put_contents($script, $php);
    $user = SW_CACHE_OWNER;
    $cmd = 'runuser -u ' . escapeshellarg($user) . ' -- php ' . escapeshellarg($script);
    passthru($cmd, $code);
    @unlink($script);
    sw_cache_chown();
    if ($code !== 0) {
        throw new RuntimeException('sw_rebuild_sitemap_as_www failed, exit=' . $code);
    }
}
