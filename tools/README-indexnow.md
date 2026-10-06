# swIndexNow

Плагин MODX: при сохранении / публикации / снятии / удалении ресурса шлёт URL в IndexNow
(`api.indexnow.org` + `yandex.com/indexnow`) и пересобирает статичный `/sitemap.xml`.

- Ключ: системная настройка `sw_indexnow_key`
- Файл ключа: `https://studiowest.ru/{key}.txt`
- Код: `snippet/plugin.swIndexNow.php`
- Деплой: `php tools/deploy-sw-indexnow.php` (на сервере из `/tmp`)

Логи: `core/cache/logs/` (записи `[swIndexNow]`).
