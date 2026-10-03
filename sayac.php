<?php
// Ziyaretçi sayacı uç noktası. assets/sayac.js sayfa açılınca "hit",
// sayfadan çıkarken ya da sekme gizlenince "leave" (görünür kalınan süre) gönderir.
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/stats.php';
require_once __DIR__ . '/inc/geo.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
function bitti(): never { http_response_code(204); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bitti();
$host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
if ($origin === '' || strtolower((string) parse_url($origin, PHP_URL_HOST)) !== $host) bitti();

$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if (stats_is_bot($ua) || !empty($_COOKIE[STATS_SKIP_COOKIE])) bitti();

$in = json_decode((string) file_get_contents('php://input', false, null, 0, 4096), true);
if (!is_array($in)) bitti();
$id = (string) ($in['i'] ?? '');
if (!preg_match('/^[a-z0-9]{8,32}$/', $id)) bitti();
$now = time();
$day = stats_day($now);
$visitor = stats_visitor($day);

if (($in['e'] ?? '') === 'hit') {
  $path = (string) ($in['p'] ?? '');
  if (!preg_match('~^/[^\s?#]{0,199}$~u', $path) || str_starts_with($path, '/yonetim')) bitti();
  $title = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($in['t'] ?? ''))), 0, 120);
  $ref = '';
  $r = (string) ($in['r'] ?? '');
  if ($r !== '') {
    $rh = strtolower((string) parse_url($r, PHP_URL_HOST));
    if ($rh !== '' && $rh !== $host && preg_replace('/^www\./', '', $rh) !== preg_replace('/^www\./', '', $host)) $ref = mb_substr(preg_replace('/^www\./', '', $rh), 0, 80);
  }
  // Konum sunucudaki veritabanından bulunur; IP'nin yalnızca son kısmı gizlenmiş hali saklanır.
  $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
  $geo = geo_lookup($ip);
  $loc = ['ip' => ip_masked($ip), 'c' => $geo['country'] ?? '', 'ci' => $geo['city'] ?? '', 'rg' => tr_region($geo['region'] ?? '')];
  stats_update($day, function (array &$d) use ($id, $path, $title, $ref, $visitor, $ua, $now, $loc) {
    if (count($d['views']) >= STATS_DAY_LIMIT) return;
    $mine = 0;
    foreach ($d['views'] as $v) {
      if ($v['i'] === $id) return;
      if ($v['h'] === $visitor && ++$mine >= STATS_VISITOR_LIMIT) return;
    }
    $d['views'][] = ['i' => $id, 't' => $now, 'p' => $path, 'h' => $visitor, 'r' => $ref, 'd' => stats_device($ua), 's' => 0] + $loc;
    if ($title !== '') $d['titles'][$path] = $title;
  });
  bitti();
}

if (($in['e'] ?? '') === 'leave') {
  $sec = max(0, (int) ($in['s'] ?? 0));
  // Gece yarısını geçen ziyaretler için önceki güne de bak
  foreach ([$day, stats_day($now - 86400)] as $k => $dd) {
    if (!is_file(stats_file($dd))) continue;
    $v2 = $k === 0 ? $visitor : stats_visitor($dd);
    $found = stats_update($dd, function (array &$d) use ($id, $v2, $sec, $now) {
      for ($n = count($d['views']) - 1; $n >= 0; $n--) {
        $v = &$d['views'][$n];
        if ($v['i'] !== $id) continue;
        if ($v['h'] === $v2) $v['s'] = min($sec, $now - (int) $v['t'] + 5, STATS_MAX_SECONDS);
        return true;
      }
      return false;
    });
    if ($found) break;
  }
  bitti();
}
bitti();
