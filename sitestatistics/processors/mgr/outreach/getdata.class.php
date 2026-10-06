<?php

/**
 * Fetch KP outreach mailing stats from jkh-api.
 */
class siteStatisticsOutreachGetDataProcessor extends modProcessor
{
    public $permission = 'list_statistics';

    public function process()
    {
        $this->modx->lexicon->load('sitestatistics:default');

        if (!$this->modx->hasPermission($this->permission)) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $cfgFile = $this->modx->getOption('core_path') . 'components/sitestatistics/config.outreach.php';
        $url = '';
        $token = '';
        if (is_file($cfgFile)) {
            /** @var array $outreach */
            $outreach = include $cfgFile;
            if (is_array($outreach)) {
                $url = (string)($outreach['stats_url'] ?? '');
                $token = (string)($outreach['token'] ?? '');
            }
        }
        if ($url === '') {
            $url = 'http://91.209.135.134:3001/api/outreach/stats';
        }

        $sep = (strpos($url, '?') === false) ? '?' : '&';
        $full = $url . $sep . 'token=' . rawurlencode($token);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($full, false, $ctx);
        if ($raw === false) {
            return $this->failure($this->modx->lexicon('outreach_error') . ' (network)');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $this->failure($this->modx->lexicon('outreach_error') . ' (bad json)');
        }
        if (isset($data['statusCode']) && (int)$data['statusCode'] >= 400) {
            $msg = (string)($data['message'] ?? $this->modx->lexicon('outreach_error'));
            return $this->failure($msg);
        }

        // Normalize display fields so the panel never shows >100% / negative pending.
        $total = (int)($data['total'] ?? 0);
        $sent = (int)($data['sent'] ?? 0);
        $pending = (int)($data['pending'] ?? 0);
        if ($pending < 0) {
            $pending = 0;
        }
        $pct = (float)($data['progressPct'] ?? 0);
        if ($total > 0 && $pct > 100) {
            $pct = min(100.0, round(100.0 * $sent / $total, 1));
        }
        $data['pending'] = $pending;
        $data['progressPct'] = max(0, min(100, $pct));
        if (!isset($data['remainingToday'])) {
            $data['remainingToday'] = max(0, (int)($data['dailyLimit'] ?? 0) - (int)($data['sentToday'] ?? 0));
        }
        if (!isset($data['clicks'])) {
            $data['clicks'] = 0;
        }
        if (!isset($data['clicksUnique'])) {
            $data['clicksUnique'] = 0;
        }
        if (!isset($data['followupD3Sent'])) {
            $data['followupD3Sent'] = 0;
        }
        if (!isset($data['recentClicks']) || !is_array($data['recentClicks'])) {
            $data['recentClicks'] = [];
        }

        return $this->success('', $data);
    }
}

return 'siteStatisticsOutreachGetDataProcessor';
