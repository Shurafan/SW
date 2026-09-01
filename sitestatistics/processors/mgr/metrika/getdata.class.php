<?php

/**
 * Dashboard charts from local siteStatistics tables (Metrika-like UI).
 */
class siteStatisticsMetrikaGetDataProcessor extends modProcessor
{
    public $permission = 'list_statistics';

    public function process()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $days = (int)$this->getProperty('days', 30);
        if (!in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }

        $dateTo = date('Y-m-d');
        $dateFrom = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        /** @var siteStatistics $svc */
        $svc = $this->modx->getService(
            'sitestatistics',
            'siteStatistics',
            $this->modx->getOption('sitestatistics_core_path', null, $this->modx->getOption('core_path') . 'components/sitestatistics/') . 'services/'
        );
        if ($svc) {
            $svc->ensureTrafficTable();
            $svc->ensureVisitTable();
        }

        $table = $this->modx->getTableName('PageStatistics');
        $trafficTable = $this->modx->escape($this->modx->getOption('table_prefix') . 'stat_traffic_daily');
        $visitTable = $this->modx->escape($this->modx->getOption('table_prefix') . 'stat_visit_daily');
        $bounceSeconds = $svc ? $svc->getBounceSeconds() : 15;
        $exclude = $svc ? $svc->getExcludeUserKeys() : [];
        if ($svc) {
            $exclude = array_values(array_unique(array_merge($exclude, $svc->getExcludeUserKeysByIp())));
        }
        $excludeIps = $svc ? $svc->getExcludeIps() : [];
        $excludeSql = '';
        $excludeParams = [];
        if ($exclude) {
            $placeholders = [];
            foreach ($exclude as $i => $key) {
                $ph = ':ex' . $i;
                $placeholders[] = $ph;
                $excludeParams[$ph] = $key;
            }
            $excludeSql = ' AND user_key NOT IN (' . implode(',', $placeholders) . ')';
        }

        $sql = "SELECT `date`,
                       COUNT(DISTINCT user_key) AS users,
                       SUM(views) AS views
                FROM {$table}
                WHERE `date` BETWEEN :d1 AND :d2
                {$excludeSql}
                GROUP BY `date`
                ORDER BY `date` ASC";

        $stmt = $this->modx->prepare($sql);
        $stmt->bindValue(':d1', $dateFrom);
        $stmt->bindValue(':d2', $dateTo);
        foreach ($excludeParams as $ph => $val) {
            $stmt->bindValue($ph, $val);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Отказ: 1 просмотр и длительность < порога (для одного хита duration=0)
        $bounceExcludeSql = $exclude
            ? ' AND v.user_key NOT IN (' . implode(',', array_keys($excludeParams)) . ')'
            : '';
        $bounceSql = "SELECT v.`date`, COUNT(*) AS bounced
                      FROM {$visitTable} v
                      WHERE v.`date` BETWEEN :d1 AND :d2
                        AND v.views = 1
                        AND TIMESTAMPDIFF(SECOND, v.first_hit, v.last_hit) < :thr
                        {$bounceExcludeSql}
                      GROUP BY v.`date`";
        $bStmt = $this->modx->prepare($bounceSql);
        $bStmt->bindValue(':d1', $dateFrom);
        $bStmt->bindValue(':d2', $dateTo);
        $bStmt->bindValue(':thr', $bounceSeconds, PDO::PARAM_INT);
        foreach ($excludeParams as $ph => $val) {
            $bStmt->bindValue($ph, $val);
        }
        $bStmt->execute();
        $bounceMap = [];
        while ($b = $bStmt->fetch(PDO::FETCH_ASSOC)) {
            $bounceMap[$b['date']] = (int)$b['bounced'];
        }

        // Fallback for days without visit_daily rows: old 1-view logic
        $bounceFallbackSql = "SELECT `date`, COUNT(*) AS bounced
                      FROM (
                          SELECT `date`, user_key, SUM(views) AS v
                          FROM {$table}
                          WHERE `date` BETWEEN :d1 AND :d2
                          {$excludeSql}
                          GROUP BY `date`, user_key
                          HAVING v = 1
                      ) t
                      GROUP BY `date`";
        $bf = $this->modx->prepare($bounceFallbackSql);
        $bf->bindValue(':d1', $dateFrom);
        $bf->bindValue(':d2', $dateTo);
        foreach ($excludeParams as $ph => $val) {
            $bf->bindValue($ph, $val);
        }
        $bf->execute();
        $bounceFallback = [];
        while ($b = $bf->fetch(PDO::FETCH_ASSOC)) {
            $bounceFallback[$b['date']] = (int)$b['bounced'];
        }

        $tSql = "SELECT `date`, `source`, `hits` FROM {$trafficTable}
                 WHERE `date` BETWEEN :d1 AND :d2";
        $tStmt = $this->modx->prepare($tSql);
        $tStmt->bindValue(':d1', $dateFrom);
        $tStmt->bindValue(':d2', $dateTo);
        $tStmt->execute();
        $trafficMap = [];
        while ($t = $tStmt->fetch(PDO::FETCH_ASSOC)) {
            $trafficMap[$t['date']][$t['source']] = (int)$t['hits'];
        }

        $retExclude = $exclude
            ? ' AND cur.user_key NOT IN (' . implode(',', array_keys($excludeParams)) . ')'
            : '';
        $retSql = "SELECT cur.`date`, COUNT(DISTINCT cur.user_key) AS returning_users
                   FROM (
                       SELECT DISTINCT `date`, user_key
                       FROM {$table}
                       WHERE `date` BETWEEN :d1 AND :d2
                       {$excludeSql}
                   ) cur
                   INNER JOIN (
                       SELECT user_key, MIN(`date`) AS first_seen
                       FROM {$table}
                       GROUP BY user_key
                   ) first ON first.user_key = cur.user_key AND first.first_seen < cur.`date`
                   WHERE 1=1 {$retExclude}
                   GROUP BY cur.`date`";
        // simplify returning query - exclude already in subquery
        $retSql = "SELECT cur.`date`, COUNT(DISTINCT cur.user_key) AS returning_users
                   FROM (
                       SELECT DISTINCT `date`, user_key
                       FROM {$table}
                       WHERE `date` BETWEEN :d1 AND :d2
                       {$excludeSql}
                   ) cur
                   INNER JOIN (
                       SELECT user_key, MIN(`date`) AS first_seen
                       FROM {$table}
                       GROUP BY user_key
                   ) first ON first.user_key = cur.user_key AND first.first_seen < cur.`date`
                   GROUP BY cur.`date`";
        $rStmt = $this->modx->prepare($retSql);
        $rStmt->bindValue(':d1', $dateFrom);
        $rStmt->bindValue(':d2', $dateTo);
        foreach ($excludeParams as $ph => $val) {
            $rStmt->bindValue($ph, $val);
        }
        $rStmt->execute();
        $returningMap = [];
        while ($r = $rStmt->fetch(PDO::FETCH_ASSOC)) {
            $returningMap[$r['date']] = (int)$r['returning_users'];
        }

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row['date']] = $row;
        }

        $labels = [];
        $users = [];
        $returning = [];
        $views = [];
        $external = [];
        $direct = [];
        $internal = [];
        $bots = [];
        $depth = [];
        $bounceRate = [];

        $sumExternal = $sumDirect = $sumInternal = $sumBots = 0;

        $cursor = strtotime($dateFrom);
        $end = strtotime($dateTo);
        while ($cursor <= $end) {
            $d = date('Y-m-d', $cursor);
            $labels[] = date('d.m', $cursor);

            $row = $byDate[$d] ?? null;
            $u = $row ? (int)$row['users'] : 0;
            $v = $row ? (int)$row['views'] : 0;
            $bounced = $bounceMap[$d] ?? ($bounceFallback[$d] ?? 0);
            $tr = $trafficMap[$d] ?? [];

            $users[] = $u;
            $returning[] = (int)($returningMap[$d] ?? 0);
            $views[] = $v;
            $ex = (int)($tr['external'] ?? 0);
            $di = (int)($tr['direct'] ?? 0);
            $in = (int)($tr['internal'] ?? 0);
            $bo = (int)($tr['bot'] ?? 0);
            $external[] = $ex;
            $direct[] = $di;
            $internal[] = $in;
            $bots[] = $bo;
            $sumExternal += $ex;
            $sumDirect += $di;
            $sumInternal += $in;
            $sumBots += $bo;

            $depth[] = $u > 0 ? round($v / $u, 2) : 0;
            $bounceRate[] = $u > 0 ? round($bounced * 100 / $u, 2) : 0;

            $cursor = strtotime('+1 day', $cursor);
        }

        $uniqSql = "SELECT COUNT(DISTINCT user_key) FROM {$table} WHERE `date` BETWEEN :d1 AND :d2 {$excludeSql}";
        $uStmt = $this->modx->prepare($uniqSql);
        $uStmt->bindValue(':d1', $dateFrom);
        $uStmt->bindValue(':d2', $dateTo);
        foreach ($excludeParams as $ph => $val) {
            $uStmt->bindValue($ph, $val);
        }
        $uStmt->execute();
        $uniqueUsers = (int)$uStmt->fetchColumn();

        $retPeriodSql = "SELECT COUNT(DISTINCT p.user_key)
                         FROM {$table} p
                         INNER JOIN (
                             SELECT user_key, MIN(`date`) AS first_seen
                             FROM {$table}
                             GROUP BY user_key
                         ) first ON first.user_key = p.user_key AND first.first_seen < :d1
                         WHERE p.`date` BETWEEN :d1b AND :d2";
        if ($exclude) {
            $retPeriodSql .= ' AND p.user_key NOT IN (' . implode(',', array_keys($excludeParams)) . ')';
        }
        $rp = $this->modx->prepare($retPeriodSql);
        $rp->bindValue(':d1', $dateFrom);
        $rp->bindValue(':d1b', $dateFrom);
        $rp->bindValue(':d2', $dateTo);
        foreach ($excludeParams as $ph => $val) {
            $rp->bindValue($ph, $val);
        }
        $rp->execute();
        $returningPeriod = (int)$rp->fetchColumn();

        $totalViews = array_sum($views);
        $avgDepth = $uniqueUsers > 0 ? round($totalViews / $uniqueUsers, 2) : 0;

        $periodBounced = 0;
        $bpSql = "SELECT COUNT(*) FROM {$visitTable} v
                  WHERE v.`date` BETWEEN :d1 AND :d2
                    AND v.views = 1
                    AND TIMESTAMPDIFF(SECOND, v.first_hit, v.last_hit) < :thr";
        if ($exclude) {
            $bpSql .= ' AND v.user_key NOT IN (' . implode(',', array_keys($excludeParams)) . ')';
        }
        $bp = $this->modx->prepare($bpSql);
        $bp->bindValue(':d1', $dateFrom);
        $bp->bindValue(':d2', $dateTo);
        $bp->bindValue(':thr', $bounceSeconds, PDO::PARAM_INT);
        foreach ($excludeParams as $ph => $val) {
            $bp->bindValue($ph, $val);
        }
        $bp->execute();
        $periodBounced = (int)$bp->fetchColumn();
        if ($periodBounced === 0 && array_sum($bounceFallback) > 0) {
            // approximate from fallback unique single-view users in period
            $bp2 = "SELECT COUNT(*) FROM (
                SELECT user_key, SUM(views) AS v FROM {$table}
                WHERE `date` BETWEEN :d1 AND :d2 {$excludeSql}
                GROUP BY user_key HAVING v = 1
            ) t";
            $bps = $this->modx->prepare($bp2);
            $bps->bindValue(':d1', $dateFrom);
            $bps->bindValue(':d2', $dateTo);
            foreach ($excludeParams as $ph => $val) {
                $bps->bindValue($ph, $val);
            }
            $bps->execute();
            $periodBounced = (int)$bps->fetchColumn();
        }
        $avgBounce = $uniqueUsers > 0 ? round($periodBounced * 100 / $uniqueUsers, 1) : 0;

        return $this->success('', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'labels' => $labels,
            'settings' => [
                'bounce_seconds' => $bounceSeconds,
                'bots' => $svc ? array_values(array_filter(array_map('trim', explode(',', (string)$this->modx->getOption('stat.not_allowed_user_agents', null, ''))))) : [],
                'exclude_users' => $svc ? $svc->getExcludeUserKeys() : [],
                'exclude_ips' => $excludeIps,
            ],
            'summary' => [
                'users' => $uniqueUsers,
                'returning' => $returningPeriod,
                'views' => $totalViews,
                'depth' => $avgDepth,
                'bounceRate' => $avgBounce,
                'external' => $sumExternal,
                'direct' => $sumDirect,
                'internal' => $sumInternal,
                'bots' => $sumBots,
                'days' => $days,
            ],
            'general' => [
                'users' => $users,
                'returning' => $returning,
                'views' => $views,
            ],
            'traffic' => [
                'external' => $external,
                'direct' => $direct,
                'internal' => $internal,
                'bots' => $bots,
            ],
            'behavior' => [
                'bounceRate' => $bounceRate,
                'pageDepth' => $depth,
            ],
        ]);
    }
}

return 'siteStatisticsMetrikaGetDataProcessor';
