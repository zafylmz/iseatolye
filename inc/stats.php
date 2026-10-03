<?php
// Site içi ziyaretçi sayacı: Google hesabı ve çerez gerektirmez.
// IP adresi saklanmaz; her gün değişen gizli bir özetle tekil ziyaretçi sayılır.
// Veriler data/stats/YYYY-MM-DD.json dosyalarında tutulur.
declare(strict_types=1);

const STATS_DIR = ROOT . '/data/stats';
const STATS_SECRET_FILE = ROOT . '/data/stats-secret.php';
const STATS_KEEP_DAYS = 400;       // daha eski günler kendiliğinden silinir
const STATS_DAY_LIMIT = 20000;     // bir günde en fazla bu kadar görüntüleme kaydedilir
const STATS_VISITOR_LIMIT = 300;   // aynı ziyaretçiden günde en fazla bu kadar görüntüleme
const STATS_MAX_SECONDS = 1800;    // tek sayfada sayılan en uzun süre (30 dk)
const STATS_SKIP_COOKIE = 'ise_sayma';
const STATS_IP_DAYS = 30;         // gizlenmiş IP bu kadar gün sonra kayıtlardan silinir

function stats_tz(): DateTimeZone {
  static $tz = null;
  return $tz ??= new DateTimeZone('Europe/Istanbul');
}

function stats_day(int $ts): string {
  return (new DateTimeImmutable('@' . $ts))->setTimezone(stats_tz())->format('Y-m-d');
}

function stats_secret(): string {
  static $s = null;
  if ($s !== null) return $s;
  if (is_file(STATS_SECRET_FILE)) $s = (string) (include STATS_SECRET_FILE);
  if (!$s) {
    $s = bin2hex(random_bytes(32));
    file_put_contents(STATS_SECRET_FILE, "<?php\nreturn " . var_export($s, true) . ";\n", LOCK_EX);
  }
  return $s;
}

// Aynı gün, aynı IP ve aynı tarayıcı aynı özeti verir; ertesi gün özet değişir.
function stats_visitor(string $day): string {
  $raw = ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '');
  return substr(hash_hmac('sha256', $day . '|' . $raw, stats_secret()), 0, 16);
}

function stats_is_bot(string $ua): bool {
  return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|fetch|scan|preview|monitor|headless|lighthouse|pagespeed|phantom|selenium|puppeteer|playwright|python|curl|wget|java\/|go-http|axios|node-fetch|facebookexternalhit|whatsapp|telegram|discord|semrush|ahrefs|mj12|dotbot|petal|bytespider|gptbot|claude|perplexity/i', $ua);
}

function stats_device(string $ua): string {
  if (preg_match('/iPad|Tablet|Android(?!.*Mobile)|Silk|Kindle/i', $ua)) return 'tablet';
  if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone/i', $ua)) return 'mobil';
  return 'masaustu';
}

function stats_file(string $day): string {
  return STATS_DIR . '/' . $day . '.json';
}

function stats_read(string $day): array {
  $f = stats_file($day);
  if (!is_file($f)) return ['views' => [], 'titles' => []];
  $d = json_decode((string) file_get_contents($f), true);
  return is_array($d) ? $d + ['views' => [], 'titles' => []] : ['views' => [], 'titles' => []];
}

function stats_update(string $day, callable $fn) {
  if (!is_dir(STATS_DIR)) {
    @mkdir(STATS_DIR, 0755, true);
    stats_cleanup();
  }
  $h = fopen(stats_file($day), 'c+');
  if (!$h) return null;
  try {
    flock($h, LOCK_EX);
    $d = json_decode((string) stream_get_contents($h), true);
    if (!is_array($d)) {
      $d = ['views' => [], 'titles' => []];
      stats_cleanup(); // her yeni günün ilk kaydında eski dosyaları temizle
    }
    $result = $fn($d);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($h);
    flock($h, LOCK_UN);
    return $result;
  } finally {
    fclose($h);
  }
}

function stats_cleanup(): void {
  $limit = stats_day(time() - STATS_KEEP_DAYS * 86400);
  foreach (glob(STATS_DIR . '/*.json') ?: [] as $f) {
    if (basename($f, '.json') < $limit) @unlink($f);
  }
  // Eski günlerde IP alanını sil (konum ve diğer sayılar kalır)
  $ipLimit = stats_day(time() - STATS_IP_DAYS * 86400);
  foreach (glob(STATS_DIR . '/*.json') ?: [] as $f) {
    if (basename($f, '.json') >= $ipLimit) continue;
    $raw = (string) file_get_contents($f);
    if (!str_contains($raw, '"ip":')) continue;
    $d = json_decode($raw, true);
    if (!is_array($d)) continue;
    foreach ($d['views'] as &$v) unset($v['ip']);
    unset($v);
    file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
  }
}

// Panel için: verilen gün aralığındaki görüntülemeleri özetler.
function stats_summary(int $days): array {
  $now = time();
  $out = [
    'days' => [], 'visitors' => 0, 'views' => 0, 'dur_sum' => 0, 'dur_n' => 0,
    'pages' => [], 'refs' => [], 'devices' => ['masaustu' => 0, 'mobil' => 0, 'tablet' => 0],
    'hours' => array_fill(0, 24, ['visitors' => 0, 'views' => 0]), 'active' => 0,
    'countries' => [], 'cities' => [], 'recent' => [],
  ];
  $titles = [];
  $hourVisitors = array_fill(0, 24, []);
  for ($i = $days - 1; $i >= 0; $i--) {
    $day = stats_day($now - $i * 86400);
    $d = stats_read($day);
    $titles = $d['titles'] + $titles;
    $seen = [];
    $pageSeen = [];
    $devSeen = [];
    foreach ($d['views'] as $v) {
      $p = (string) ($v['p'] ?? '/');
      $h = (string) ($v['h'] ?? '');
      $s = (int) ($v['s'] ?? 0);
      $seen[$h] = true;
      $pg = &$out['pages'][$p];
      $pg ??= ['views' => 0, 'visitors' => 0, 'dur_sum' => 0, 'dur_n' => 0];
      $pg['views']++;
      if (empty($pageSeen[$p][$h])) { $pageSeen[$p][$h] = true; $pg['visitors']++; }
      if ($s > 0) { $pg['dur_sum'] += $s; $pg['dur_n']++; $out['dur_sum'] += $s; $out['dur_n']++; }
      unset($pg);
      if (empty($devSeen[$h])) {
        $cc = (string) ($v['c'] ?? '');
        $out['countries'][$cc] = ($out['countries'][$cc] ?? 0) + 1;
        if (($v['ci'] ?? '') !== '' || ($v['rg'] ?? '') !== '') {
          $ck = implode('|', [$v['ci'] ?? '', $v['rg'] ?? '', $cc]);
          $out['cities'][$ck] = ($out['cities'][$ck] ?? 0) + 1;
        }
      }
      if (empty($devSeen[$h])) { $devSeen[$h] = true; $out['devices'][$v['d'] ?? 'masaustu'] = ($out['devices'][$v['d'] ?? 'masaustu'] ?? 0) + 1; }
      $ref = (string) ($v['r'] ?? '');
      if ($ref !== '') $out['refs'][$ref] = ($out['refs'][$ref] ?? 0) + 1;
      if ($days === 1) {
        $hr = (int) (new DateTimeImmutable('@' . (int) $v['t']))->setTimezone(stats_tz())->format('G');
        $out['hours'][$hr]['views']++;
        $hourVisitors[$hr][$h] = true;
      }
    }
    $out['recent'] = array_merge(array_reverse($d['views']), $out['recent']);
    if (count($out['recent']) > 60) $out['recent'] = array_slice($out['recent'], 0, 60);
    $out['days'][] = ['day' => $day, 'visitors' => count($seen), 'views' => count($d['views'])];
    $out['visitors'] += count($seen);
    $out['views'] += count($d['views']);
  }
  if ($days === 1) foreach ($hourVisitors as $hr => $set) $out['hours'][$hr]['visitors'] = count($set);
  // Son 5 dakikada sayfa açan tekil ziyaretçi
  $recent = [];
  foreach (stats_read(stats_day($now))['views'] as $v) if ((int) $v['t'] > $now - 300) $recent[$v['h'] ?? ''] = true;
  $out['active'] = count($recent);
  foreach ($out['pages'] as $p => &$pg) $pg['title'] = $titles[$p] ?? '';
  unset($pg);
  uasort($out['pages'], fn($a, $b) => $b['views'] <=> $a['views']);
  arsort($out['refs']);
  arsort($out['countries']);
  usort($out['recent'], fn($a, $b) => (int) $b['t'] <=> (int) $a['t']);
  arsort($out['cities']);
  return $out;
}

function stats_duration(int $sec): string {
  if ($sec <= 0) return '–';
  if ($sec < 60) return $sec . ' sn';
  $m = intdiv($sec, 60); $s = $sec % 60;
  return $m . ' dk' . ($s ? ' ' . $s . ' sn' : '');
}
