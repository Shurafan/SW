<?php

/**
 * Save Metrika dashboard settings (system settings in DB).
 */
class siteStatisticsMetrikaSaveSettingsProcessor extends modProcessor
{
    public $permission = 'list_statistics';

    public function process()
    {
        $this->modx->lexicon->load('sitestatistics:default');

        if (!$this->modx->hasPermission($this->permission)) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $field = trim((string)$this->getProperty('field', ''));
        $allowed = ['bounce_seconds', 'bots', 'exclude_users', 'exclude_ips', 'exclude'];
        if (!in_array($field, $allowed, true)) {
            return $this->failure($this->modx->lexicon('sitestatistics_metrika_settings_err'));
        }

        if ($field === 'exclude') {
            $users = $this->normalizeList($this->getProperty('exclude_users', $this->getProperty('value', '')));
            $ips = $this->normalizeList($this->getProperty('exclude_ips', ''));
            if (!$this->writeSetting('stat.exclude_users', $users, 'textfield')) {
                return $this->failure($this->modx->lexicon('sitestatistics_metrika_settings_err'));
            }
            if (!$this->writeSetting('stat.not_allowed_ip', $ips, 'textfield')) {
                return $this->failure($this->modx->lexicon('sitestatistics_metrika_settings_err'));
            }
            $this->refreshSettingsCache();

            return $this->success($this->modx->lexicon('sitestatistics_metrika_settings_saved'), [
                'field' => 'exclude',
                'exclude_users' => $users === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $users)))),
                'exclude_ips' => $ips === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $ips)))),
            ]);
        }

        $map = [
            'bounce_seconds' => 'stat.bounce_seconds',
            'bots' => 'stat.not_allowed_user_agents',
            'exclude_users' => 'stat.exclude_users',
            'exclude_ips' => 'stat.not_allowed_ip',
        ];
        $key = $map[$field];
        $xtype = $field === 'bounce_seconds' ? 'numberfield' : 'textfield';

        if ($field === 'bounce_seconds') {
            $value = (string)max(0, (int)$this->getProperty('value', 15));
        } else {
            $value = $this->normalizeList($this->getProperty('value', ''));
        }

        if (!$this->writeSetting($key, $value, $xtype)) {
            return $this->failure($this->modx->lexicon('sitestatistics_metrika_settings_err'));
        }
        $this->refreshSettingsCache();

        return $this->success($this->modx->lexicon('sitestatistics_metrika_settings_saved'), [
            'field' => $field,
            'value' => $field === 'bounce_seconds'
                ? (int)$value
                : ($value === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $value))))),
        ]);
    }

    /**
     * @param mixed $raw
     * @return string
     */
    protected function normalizeList($raw)
    {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = array_map('trim', explode(',', (string)$raw));
        }
        $items = array_values(array_unique(array_filter($items, static function ($v) {
            return $v !== '';
        })));

        return implode(',', $items);
    }

    /**
     * Persist system setting to modx_system_settings.
     *
     * @param string $key
     * @param string $value
     * @param string $xtype
     * @return bool
     */
    protected function writeSetting($key, $value, $xtype = 'textfield')
    {
        $table = $this->modx->getTableName('modSystemSetting');
        $sql = "INSERT INTO {$table} (`key`, `value`, `xtype`, `namespace`, `area`, `editedon`)
                VALUES (:k, :v, :xtype, 'sitestatistics', 'sitestatistics_main', NOW())
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `editedon` = NOW()";
        $stmt = $this->modx->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $ok = $stmt->execute([
            ':k' => $key,
            ':v' => $value,
            ':xtype' => $xtype,
        ]);
        if (!$ok) {
            return false;
        }

        $this->modx->setOption($key, $value);

        $class = class_exists('\\MODX\\Revolution\\modSystemSetting')
            ? '\\MODX\\Revolution\\modSystemSetting'
            : 'modSystemSetting';
        /** @var modSystemSetting|null $setting */
        $setting = $this->modx->getObject($class, ['key' => $key]);
        if ($setting) {
            $setting->set('value', $value);
            $setting->save();
        }

        return true;
    }

    protected function refreshSettingsCache()
    {
        $this->modx->cacheManager->refresh([
            'system_settings' => [],
        ]);
        if (method_exists($this->modx, 'getCacheManager')) {
            $this->modx->getCacheManager()->delete('config', [xPDO::OPT_CACHE_KEY => 'config']);
        }
    }
}

return 'siteStatisticsMetrikaSaveSettingsProcessor';
