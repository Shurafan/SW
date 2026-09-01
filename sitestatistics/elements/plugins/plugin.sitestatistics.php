<?php
$path = $modx->getOption('sitestatistics_core_path', null, $modx->getOption('core_path') . 'components/sitestatistics/') . 'services/';

$userAgentsOpt = $modx->getOption('stat.not_allowed_user_agents');
$botPattern = '';
if (!empty($userAgentsOpt)) {
    $parts = explode(',', $userAgentsOpt);
    foreach ($parts as &$ua) {
        $ua = preg_quote(trim($ua));
    }
    unset($ua);
    $botPattern = implode('|', array_filter($parts));
}
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'empty';
if ($userAgent === '') {
    $userAgent = 'empty';
}
$isBot = ($botPattern !== '' && preg_match('/(' . $botPattern . ')/i', $userAgent));

switch ($modx->event->name) {
    case 'OnLoadWebDocument': {
        if (($modx->getOption('stat.enable_statistics', null, false) || $modx->getOption('stat.count_online_users', null, false)) && $modx->getOption('site_status')) {
            /** @var siteStatistics $siteStat */
            $siteStat = $modx->getService('sitestatistics', 'siteStatistics', $path);
            if (!$siteStat->checkIp()) {
                return;
            }

            // Боты: только учёт источника, без обычной статистики посетителей
            if ($isBot) {
                if ($modx->getOption('stat.enable_statistics', null, false)) {
                    $siteStat->incrementTraffic('bot');
                }
                return;
            }

            $siteStat->defineUserKey();
            if ($modx->getOption('stat.enable_statistics', null, false)) {
                $siteStat->setStatistics();
                $siteStat->incrementTraffic();
            }
            if ($modx->getOption('stat.count_online_users', null, false)) {
                $siteStat->setUserStatistics();
            }
            $siteStat->need2ClearCache = $siteStat->getMessage();
        }
        break;
    }
    case 'OnWebPageComplete': {
        if (!empty($modx->sitestatistics)) {
            $modx->sitestatistics->clearCache();
            unset($modx->sitestatistics);
        }
        break;
    }
    case 'OnDocFormPrerender':
        if ($mode == modSystemEvent::MODE_UPD && $modx->getOption('stat.show_tab_in_resource_form', null, true)) {
            /** @var siteStatistics $siteStat */
            $siteStat = $modx->getService('sitestatistics', 'siteStatistics', $path);
            $siteStat->initializeMgr(true);
        }
        break;
}
