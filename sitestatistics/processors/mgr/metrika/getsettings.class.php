<?php

/**
 * Read Metrika dashboard settings from DB.
 */
class siteStatisticsMetrikaGetSettingsProcessor extends modProcessor
{
    public $permission = 'list_statistics';

    public function process()
    {
        $this->modx->lexicon->load('sitestatistics:default');

        if (!$this->modx->hasPermission($this->permission)) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $bots = $this->readSetting('stat.not_allowed_user_agents', 'bot,spider,slurp, empty');
        $exclude = $this->readSetting('stat.exclude_users', '');
        $excludeIps = $this->readSetting('stat.not_allowed_ip', '');
        $bounce = (int)$this->readSetting('stat.bounce_seconds', '15');
        if ($bounce < 0) {
            $bounce = 0;
        }

        return $this->success('', [
            'bounce_seconds' => $bounce,
            'bots' => $this->parseList($bots),
            'exclude_users' => $this->parseList($exclude),
            'exclude_ips' => $this->parseList($excludeIps),
        ]);
    }

    /**
     * @param string $key
     * @param string $default
     * @return string
     */
    protected function readSetting($key, $default = '')
    {
        $table = $this->modx->getTableName('modSystemSetting');
        $stmt = $this->modx->prepare("SELECT `value` FROM {$table} WHERE `key` = :k LIMIT 1");
        if ($stmt && $stmt->execute([':k' => $key])) {
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null) {
                return (string)$val;
            }
        }

        return (string)$this->modx->getOption($key, null, $default);
    }

    /**
     * @param string $raw
     * @return array
     */
    protected function parseList($raw)
    {
        $parts = array_map('trim', explode(',', (string)$raw));

        return array_values(array_filter($parts, static function ($v) {
            return $v !== '';
        }));
    }
}

return 'siteStatisticsMetrikaGetSettingsProcessor';
