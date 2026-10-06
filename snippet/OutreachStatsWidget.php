<?php
/**
 * OutreachStatsWidget — виджет дашборда MODX: статистика рассылки КП ЖКХ.
 *
 * Использование:
 *  [[!OutreachStatsWidget]]
 */

$cfgFile = MODX_CORE_PATH . 'components/sitestatistics/config.outreach.php';
$url = 'http://91.209.135.134:3001/api/outreach/stats';
$token = '';
if (is_file($cfgFile)) {
    $outreach = include $cfgFile;
    if (is_array($outreach)) {
        $url = (string)($outreach['stats_url'] ?? $url);
        $token = (string)($outreach['token'] ?? '');
    }
}

$sep = (strpos($url, '?') === false) ? '?' : '&';
$full = $url . $sep . 'token=' . rawurlencode($token);

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 6,
        'ignore_errors' => true,
        'header' => "Accept: application/json\r\n",
    ],
]);
$raw = @file_get_contents($full, false, $ctx);
$data = is_string($raw) ? json_decode($raw, true) : null;

if (!is_array($data) || (isset($data['statusCode']) && (int)$data['statusCode'] >= 400)) {
    $detail = is_array($data) && !empty($data['message'])
        ? ' ' . htmlspecialchars((string)$data['message'], ENT_QUOTES, 'UTF-8')
        : '';
    return '<div style="padding:12px 14px;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;color:#991b1b;font:14px/1.4 Arial,sans-serif;">'
        . 'Не удалось загрузить статистику рассылки КП.' . $detail
        . '</div>';
}

$total = (int)($data['total'] ?? 0);
$sent = (int)($data['sent'] ?? 0);
$pending = (int)($data['pending'] ?? 0);
$failed = (int)($data['failed'] ?? 0);
$today = (int)($data['sentToday'] ?? 0);
$limit = (int)($data['dailyLimit'] ?? 0);
$remain = (int)($data['remainingToday'] ?? max(0, $limit - $today));
$clicks = (int)($data['clicks'] ?? 0);
$clicksUniq = (int)($data['clicksUnique'] ?? 0);
$followup = (int)($data['followupD3Sent'] ?? 0);
$pct = (float)($data['progressPct'] ?? 0);
$bar = max(0, min(100, $pct));
$updated = htmlspecialchars((string)($data['updatedAt'] ?? '—'), ENT_QUOTES, 'UTF-8');
$last = htmlspecialchars((string)($data['lastSentAt'] ?? '—'), ENT_QUOTES, 'UTF-8');
$statUrl = htmlspecialchars('/wp-admin/?a=home&namespace=sitestatistics', ENT_QUOTES, 'UTF-8');

$card = function (string $label, $value, string $color = '#111827') {
    $v = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    $l = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    return '<div style="flex:1;min-width:110px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;">'
        . '<div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;">' . $l . '</div>'
        . '<div style="margin-top:4px;font-size:22px;font-weight:700;color:' . $color . ';">' . $v . '</div>'
        . '</div>';
};

$todayLabel = $today . ' / ' . $limit . ' (ещё ' . $remain . ')';

return <<<HTML
<section class="outreach-stats-widget" style="margin:4px 0 8px;font:14px/1.45 Arial,Helvetica,sans-serif;color:#1f2937;">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;">
    <div style="font:600 16px/1.3 Arial,sans-serif;color:#0b3d5c;">Рассылка КП «ЖКХ Ответы»</div>
    <a href="{$statUrl}" style="color:#0b3d5c;font-size:13px;">Подробнее →</a>
  </div>
  <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
    {$card('В базе', $total)}
    {$card('Отправлено', $sent, '#0b7a4b')}
    {$card('Осталось', $pending, '#0b3d5c')}
    {$card('Ошибки', $failed, '#b91c1c')}
    {$card('Сегодня', $todayLabel, '#d6452e')}
    {$card('Клики КП', $clicks . ' / ' . $clicksUniq, '#7c3aed')}
    {$card('Дожим D3', $followup, '#0b7a4b')}
  </div>
  <div style="margin-bottom:6px;font-weight:600;">Прогресс: {$bar}%</div>
  <div style="height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin-bottom:10px;">
    <div style="height:100%;width:{$bar}%;background:linear-gradient(90deg,#0b3d5c,#d6452e);"></div>
  </div>
  <div style="font-size:12px;color:#6b7280;">
    Последняя отправка: {$last}<br>
    Обновлено: {$updated}
  </div>
</section>
HTML;
