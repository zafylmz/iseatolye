<?php
// Ortak yardımcılar: içerik dosyalarını okur/yazar, metinleri güvenli yazdırır, tarih ve biçim işleri.
declare(strict_types=1);
date_default_timezone_set('Europe/Istanbul');
mb_internal_encoding('UTF-8');
// PHP hataları data/hata.log dosyasına yazılır (panel > Ayarlar > Sistem kontrolü'nde görünür)
ini_set('log_errors', '1');
ini_set('display_errors', '0');
ini_set('error_log', __DIR__ . '/../data/hata.log');
// Yakalanmamış hata: boş sayfa yerine kısa bir açıklama gösterilir, ayrıntı hata kaydına yazılır.
set_exception_handler(function (Throwable $ex) {
  error_log(get_class($ex) . ': ' . $ex->getMessage() . ' @ ' . basename($ex->getFile()) . ':' . $ex->getLine() . ' ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? ''));
  if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
  $msg = $ex instanceof RuntimeException ? $ex->getMessage() : 'Beklenmeyen bir sorun oluştu.';
  echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bir sorun oluştu</title>'
    . '<div style="font:16px/1.6 system-ui,sans-serif;max-width:520px;margin:12vh auto;padding:0 20px;color:#33271e"><h1 style="font-size:22px">Bir sorun oluştu</h1><p>'
    . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p><p><a href="javascript:history.back()" style="color:#8b5e3c">Geri dönüp tekrar deneyin</a></p></div>';
});

const ROOT = __DIR__ . '/..';
const DATA = ROOT . '/data';
const CONTENT_FILE = DATA . '/content.json';
const SECRET_FILE = DATA . '/secret.php';
const RATE_FILE = DATA . '/rate.json';

const TR_MONTHS = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
const TR_MONTHS_SHORT = ['', 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
const TR_DAYS = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
const TR_DAYS_SHORT = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];

function content(): array {
  static $c = null;
  if ($c === null) $c = json_read(CONTENT_FILE);
  return $c;
}

// Ayarlar: content.json > settings, eksik anahtarlar için varsayılanlar.
function setting(string $key, $default = null) {
  $s = content()['settings'] ?? [];
  return array_key_exists($key, $s) ? $s[$key] : $default;
}

function e($s): string {
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function paragraphs(?string $s): string {
  $parts = preg_split('/\n\s*\n/', trim((string) $s)) ?: [];
  return implode('', array_map(fn($p) => '<p>' . nl2br(e(trim($p))) . '</p>', array_filter($parts, fn($p) => trim($p) !== '')));
}

function asset(string $path): string {
  $file = ROOT . '/' . ltrim($path, '/');
  return $path . '?v=' . (is_file($file) ? filemtime($file) : 0);
}

function phone_href(string $phone): string {
  return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
}

function wa_href(string $num, string $text = ''): string {
  $d = preg_replace('/\D/', '', $num);
  // Türkiye numaraları: 0507... ya da 507... yazılmışsa 90 eklenir
  if (str_starts_with($d, '0') && strlen($d) === 11) $d = '9' . $d;
  elseif (strlen($d) === 10 && $d[0] === '5') $d = '90' . $d;
  return 'https://wa.me/' . $d . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

// Sitenin tam adresi (https://www.alanadi). Yerelde çalışan adresi kullanır.
function site_url(string $path = ''): string {
  $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
  if (is_local()) return 'http://' . $host . $path;
  $base = trim((string) setting('site_url', '')) ?: 'https://' . ($host ?: 'www.iseatolye.com.tr');
  return rtrim($base, '/') . $path;
}

function is_local(): bool {
  return (bool) preg_match('/^(localhost|127(\.\d{1,3}){3}|\[::1\])(:\d+)?$/', (string) ($_SERVER['HTTP_HOST'] ?? ''));
}

function https(): bool {
  return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

// ---------- Veritabanı (MySQL) ----------
// Panel > Sistem kontrolü'nden bağlanınca sitenin bütün verileri (içerik, etkinlikler, blog, medya, üyeler, katılımlar,
// yorumlar, ilgilenenler, mesajlar) MySQL'de tutulur. Bağlantı bilgisi data/db.php dosyasındadır.
// Bağlı değilse aynı veriler data/ altındaki dosyalardadır. Şifreler ve anahtarlar her zaman data/ altındaki PHP dosyalarında kalır.
const DB_FILE = DATA . '/db.php';
const DB_DOCS = ['content', 'events', 'blog', 'medya', 'users', 'registrations', 'comments', 'interests', 'messages', 'blog-comments', 'blog-likes', 'resets'];

function db_config(): ?array {
  static $c = false;
  if ($c === false) { $c = is_file(DB_FILE) ? (include DB_FILE) : null; if (!is_array($c)) $c = null; }
  return $c;
}

function db_connect(array $c): PDO {
  $dsn = 'mysql:host=' . ($c['host'] ?: 'localhost') . (!empty($c['port']) ? ';port=' . (int) $c['port'] : '') . ';dbname=' . $c['name'] . ';charset=utf8mb4';
  $pdo = new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 5]);
  $pdo->exec("SET time_zone = '+03:00'");
  return $pdo;
}

function db(): ?PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $c = db_config();
  if (!$c) return null;
  try { return $pdo = db_connect($c); }
  catch (PDOException $ex) { error_log('Veritabanı bağlantısı: ' . $ex->getMessage()); throw new RuntimeException('Veritabanına şu an bağlanılamıyor. Birkaç dakika sonra tekrar deneyin.'); }
}

function db_install(PDO $pdo): void {
  $pdo->exec('CREATE TABLE IF NOT EXISTS ise_belgeler (ad VARCHAR(64) NOT NULL PRIMARY KEY, veri LONGTEXT NOT NULL, guncel DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
  $pdo->exec('CREATE TABLE IF NOT EXISTS ise_yedekler (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, ad VARCHAR(64) NOT NULL, zaman DATETIME NOT NULL, veri LONGTEXT NOT NULL, KEY ad_zaman (ad, zaman)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

// Bu dosya veritabanında mı tutuluyor? Öyleyse belge adını döner.
function db_doc(string $file): ?string {
  $n = basename($file, '.json');
  return in_array($n, DB_DOCS, true) && db_config() ? $n : null;
}

// Belgeyi veritabanından okur. Veritabanında henüz yoksa ve data/ altında dosyası varsa önce onu aktarır
// (veritabanı sonradan genişletildiğinde dosyada kalan veriler ilk açılışta kendiliğinden taşınır).
// Aktarılan dosya silinmez, data/yedek/ altına taşınır. Belge hiç yoksa false döner.
function db_fetch(string $n, string $file) {
  $q = db()->prepare('SELECT veri FROM ise_belgeler WHERE ad = ?');
  $q->execute([$n]);
  $v = $q->fetchColumn();
  if ($v !== false || !is_file($file)) return $v;
  $raw = (string) @file_get_contents($file);
  if (is_array(json_decode($raw, true))) {
    db()->prepare('INSERT IGNORE INTO ise_belgeler (ad, veri, guncel) VALUES (?, ?, NOW())')->execute([$n, $raw]);
    if (!is_dir(DATA . '/yedek')) @mkdir(DATA . '/yedek', 0755, true);
    @rename($file, DATA . '/yedek/' . $n . '-veritabanina-tasindi-' . date('Ymd-His') . '.json') || @rename($file, $file . '.tasindi');
  } elseif ($raw !== '') error_log('Veritabanına aktarılamadı, dosya bozuk: ' . basename($file));
  $q->execute([$n]);
  return $q->fetchColumn();
}

function data_exists(string $file): bool {
  if ($n = db_doc($file)) return db_fetch($n, $file) !== false;
  return is_file($file);
}

function json_encode_data(array $d): string {
  $json = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
  if ($json === false) throw new RuntimeException('Kayıt hazırlanamadı.');
  return $json;
}

// Belgenin bir kopyası (panel kayıtlarından önce ve her gün bir kez); her belgeden son 40 kopya tutulur.
function db_backup(PDO $pdo, string $n, string $json): void {
  $pdo->prepare('INSERT INTO ise_yedekler (ad, zaman, veri) VALUES (?, NOW(), ?)')->execute([$n, $json]);
  $q = $pdo->prepare('SELECT id FROM ise_yedekler WHERE ad = ? ORDER BY id DESC LIMIT 1 OFFSET 40');
  $q->execute([$n]);
  if ($cut = $q->fetchColumn()) $pdo->prepare('DELETE FROM ise_yedekler WHERE ad = ? AND id <= ?')->execute([$n, $cut]);
}

function db_update(string $n, callable $fn, array $default) {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $pdo->prepare('INSERT IGNORE INTO ise_belgeler (ad, veri, guncel) VALUES (?, ?, NOW())')->execute([$n, json_encode_data($default)]);
    $q = $pdo->prepare('SELECT veri, guncel FROM ise_belgeler WHERE ad = ? FOR UPDATE');
    $q->execute([$n]);
    $row = $q->fetch();
    $data = json_decode((string) $row['veri'], true);
    if (!is_array($data)) $data = $default;
    $result = $fn($data);
    if (substr((string) $row['guncel'], 0, 10) !== date('Y-m-d') && $row['veri'] !== json_encode_data($default)) db_backup($pdo, $n, (string) $row['veri']);
    $pdo->prepare('UPDATE ise_belgeler SET veri = ?, guncel = NOW() WHERE ad = ?')->execute([json_encode_data($data), $n]);
    $pdo->commit();
    return $result;
  } catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($ex instanceof PDOException) { error_log('Veritabanı yazma: ' . $ex->getMessage()); throw new RuntimeException('Kayıt kaydedilemedi, lütfen tekrar deneyin.'); }
    throw $ex;
  }
}

// ---------- JSON dosyaları ----------
function json_read(string $file, array $default = []): array {
  if ($n = db_doc($file)) {
    $d = json_decode((string) db_fetch($n, $file), true);
    return is_array($d) ? $d : $default;
  }
  if (!is_file($file)) return $default;
  $d = json_decode((string) file_get_contents($file), true);
  return is_array($d) ? $d : $default;
}

// Dosyayı kilitleyip okur, $fn ile değiştirir ve geri yazar. $fn'in dönüş değerini döndürür.
// Yeni içerik önce geçici dosyaya yazılır, sonra eskisinin yerine konur: yazma yarıda kalırsa (kota dolması vb.) eski dosya bozulmaz.
function json_update(string $file, callable $fn, array $default = []) {
  if ($n = db_doc($file)) { db_fetch($n, $file); return db_update($n, $fn, $default); }
  if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
  $lock = @fopen($file . '.lock', 'c');
  if (!$lock) throw new RuntimeException('Kayıt dosyası açılamadı. data klasörünün yazma iznini kontrol edin.');
  try {
    flock($lock, LOCK_EX);
    clearstatcache(true, $file);
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($data)) $data = $default;
    $result = $fn($data);
    $json = json_encode_data($data);
    daily_copy($file);
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $json) !== strlen($json) || !@rename($tmp, $file)) {
      @unlink($tmp);
      error_log('json_update yazılamadı: ' . basename($file));
      throw new RuntimeException('Kayıt kaydedilemedi. Sunucuda yer kalmamış ya da data klasörüne yazma izni yok olabilir.');
    }
    @chmod($file, 0644);
    return $result;
  } finally {
    flock($lock, LOCK_UN);
    fclose($lock);
  }
}

// Her veri dosyasının günde bir kopyası data/yedek/gunluk/ altında saklanır (son 30 gün).
function daily_copy(string $file): void {
  if (!is_file($file) || str_contains($file, '/yedek/') || basename($file) === 'rate.json') return;
  $dir = DATA . '/yedek/gunluk';
  $dest = $dir . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . date('Ymd') . '.json';
  if (is_file($dest)) return;
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  @copy($file, $dest);
  $old = glob($dir . '/' . pathinfo($file, PATHINFO_FILENAME) . '-*.json') ?: [];
  sort($old);
  foreach (array_slice($old, 0, max(0, count($old) - 30)) as $o) @unlink($o);
}

// Oturum klasörü: sunucunun varsayılanı yazılamıyorsa data/oturum kullanılır.
function session_prepare(): void {
  $p = (string) session_save_path();
  $p = str_contains($p, ';') ? substr($p, strrpos($p, ';') + 1) : $p;
  if ($p !== '' && is_dir($p) && is_writable($p)) return;
  $dir = DATA . '/oturum';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  if (is_writable($dir)) session_save_path($dir);
}

function new_id(int $bytes = 4): string {
  return bin2hex(random_bytes($bytes));
}

function slugify(string $s, string $fallback = 'sayfa'): string {
  $s = strtr(mb_strtolower($s, 'UTF-8'), ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'i̇' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
  return trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-') ?: $fallback;
}

function tr_upper(string $s): string {
  return mb_strtoupper(strtr($s, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
}

function initial(string $name): string {
  return tr_upper(mb_substr(trim($name) ?: '?', 0, 1));
}

// ---------- Tarih ----------
function tr_date(string $ymd, bool $withDay = false): string {
  $t = strtotime($ymd);
  if (!$t) return '';
  return (int) date('j', $t) . ' ' . TR_MONTHS[(int) date('n', $t)] . ' ' . date('Y', $t) . ($withDay ? ', ' . TR_DAYS[(int) date('w', $t)] : '');
}

function tr_date_short(string $ymd): string {
  $t = strtotime($ymd);
  return $t ? (int) date('j', $t) . ' ' . TR_MONTHS[(int) date('n', $t)] : '';
}

function tr_datetime(string $s): string {
  $t = strtotime($s);
  return $t ? tr_date(date('Y-m-d', $t)) . ' ' . date('H:i', $t) : '';
}

function time_ago(string $s): string {
  $t = strtotime($s);
  if (!$t) return '';
  $d = time() - $t;
  if ($d < 60) return 'az önce';
  if ($d < 3600) return intdiv($d, 60) . ' dakika önce';
  if ($d < 86400) return intdiv($d, 3600) . ' saat önce';
  if ($d < 86400 * 7) return intdiv($d, 86400) . ' gün önce';
  return tr_date(date('Y-m-d', $t));
}

function money($n): string {
  $n = (float) $n;
  return number_format($n, fmod($n, 1.0) ? 2 : 0, ',', '.') . ' ₺';
}

// ---------- Gizli anahtar, istemci özeti, hız sınırı ----------
function secret(): string {
  static $s = null;
  if ($s !== null) return $s;
  if (is_file(SECRET_FILE)) $s = (string) (include SECRET_FILE);
  if (!$s) {
    $s = bin2hex(random_bytes(32));
    @file_put_contents(SECRET_FILE, "<?php\nreturn " . var_export($s, true) . ";\n", LOCK_EX);
  }
  return $s;
}

// IP adresi saklanmaz; gizli anahtarla karıştırılmış kısa özeti tutulur.
function client_hash(): string {
  $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
  // IPv6'da bir bağlantının elinde milyarlarca adres olur; sınırlar /64 ağ bloğuna göre uygulanır
  if (str_contains($ip, ':') && ($bin = @inet_pton($ip)) !== false && strlen($bin) === 16) $ip = bin2hex(substr($bin, 0, 8)) . '::/64';
  return substr(hash_hmac('sha256', $ip, secret()), 0, 20);
}

// Son bir saatte $key için en fazla $limit işlem; iki işlem arası en az $gap saniye.
function rate_hit(string $key, int $limit, int $gap = 0): bool {
  $now = time();
  return json_update(RATE_FILE, function (array &$r) use ($key, $limit, $gap, $now) {
    foreach ($r as $k => $times) {
      $r[$k] = array_values(array_filter((array) $times, fn($t) => is_int($t) && $t > $now - 3600));
      if (!$r[$k]) unset($r[$k]);
    }
    $times = $r[$key] ?? [];
    if (count($times) >= $limit || ($gap && $times && $now - max($times) < $gap)) return false;
    $r[$key][] = $now;
    return true;
  });
}

// Başka siteden gönderilen formları reddeder.
function same_origin(): bool {
  $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
  if ($origin === '') return true;
  return strtolower((string) parse_url($origin, PHP_URL_HOST)) === strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
}

// ---------- Bot koruması (formlar): imzalı zaman + tarayıcıda çözülen küçük işlem ----------
const FORM_MIN_SECONDS = 3;
const FORM_MAX_AGE = 3 * 3600;
const FORM_POW_PREFIX = '000';

function form_token(string $scope, int $ts): string {
  return hash_hmac('sha256', $scope . '|' . $ts, secret());
}

function guard_fields(string $scope): string {
  $ts = time();
  return '<input type="hidden" name="ts" value="' . $ts . '"><input type="hidden" name="sig" value="' . e(form_token($scope, $ts)) . '"><input type="hidden" name="pow" value="">'
    . '<div class="hp" aria-hidden="true"><label>Web siteniz<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
}

// Hata varsa açıklamasını, sorun yoksa '' döndürür. 'bot' dönerse bota başarılı gibi görünülür.
function guard_check(string $scope): string {
  if (!same_origin()) return 'Geçersiz istek.';
  if (trim((string) ($_POST['website'] ?? '')) !== '') return 'bot';
  $ts = (int) ($_POST['ts'] ?? 0);
  $sig = (string) ($_POST['sig'] ?? '');
  $pow = (string) ($_POST['pow'] ?? '');
  if (!hash_equals(form_token($scope, $ts), $sig)) return 'Form süresi doldu. Sayfayı yenileyip tekrar deneyin.';
  if (time() - $ts < FORM_MIN_SECONDS) return 'bot';
  if (time() - $ts > FORM_MAX_AGE) return 'Form süresi doldu. Sayfayı yenileyip tekrar deneyin.';
  if (!preg_match('/^\d{1,9}$/', $pow) || !str_starts_with(hash('sha256', $sig . ':' . $pow), FORM_POW_PREFIX)) return 'Güvenlik doğrulaması tamamlanamadı. Sayfayı yenileyip tekrar deneyin.';
  return '';
}

// ---------- Basit yazı biçimi ----------
// ## Başlık, ### Alt başlık, - madde, 1. madde, > alıntı, ![açıklama](/uploads/...), **kalın**, *italik*, [bağlantı](https://...)
function md_inline(string $s): string {
  $s = e($s);
  $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
  $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
  $s = preg_replace('/(?<![\*\w])\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s', '<em>$1</em>', $s);
  $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
  $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) use ($host) {
    $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
    if (!preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $url)) return $m[0];
    $ext = preg_match('#^https?://#i', $url) && ($host === '' || !str_contains(strtolower($url), preg_replace('/^www\./', '', $host)));
    return '<a href="' . e($url) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
  }, $s);
  return (string) $s;
}

function md(string $body, ?array &$toc = null): string {
  $toc = [];
  $used = [];
  $blocks = preg_split('/\n\s*\n/', str_replace("\r\n", "\n", trim($body))) ?: [];
  $html = '';
  foreach ($blocks as $b) {
    $b = trim($b);
    if ($b === '') continue;
    $lines = explode("\n", $b);
    if (preg_match('/^(#{2,3})\s+(.+)$/', $b, $m) && count($lines) === 1) {
      $lvl = strlen($m[1]);
      $id = slugify($m[2], 'bolum');
      $base = $id; $n = 2;
      while (isset($used[$id])) $id = $base . '-' . $n++;
      $used[$id] = true;
      if ($lvl === 2) $toc[] = [$id, $m[2]];
      $html .= "<h$lvl id=\"" . e($id) . '">' . md_inline($m[2]) . "</h$lvl>";
    } elseif (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)\)$/', $b, $m) && preg_match('#^(/uploads/|https://)#', $m[2])) {
      $html .= '<figure><img src="' . e($m[2]) . '" alt="' . e($m[1]) . '" loading="lazy" decoding="async">' . ($m[1] !== '' ? '<figcaption>' . e($m[1]) . '</figcaption>' : '') . '</figure>';
    } elseif (count(array_filter($lines, fn($l) => preg_match('/^\s*[-*]\s+/', $l))) === count($lines)) {
      $html .= '<ul>' . implode('', array_map(fn($l) => '<li>' . md_inline(preg_replace('/^\s*[-*]\s+/', '', $l)) . '</li>', $lines)) . '</ul>';
    } elseif (count(array_filter($lines, fn($l) => preg_match('/^\s*\d+[.)]\s+/', $l))) === count($lines)) {
      $html .= '<ol>' . implode('', array_map(fn($l) => '<li>' . md_inline(preg_replace('/^\s*\d+[.)]\s+/', '', $l)) . '</li>', $lines)) . '</ol>';
    } elseif (str_starts_with($b, '>')) {
      $q = implode("\n", array_map(fn($l) => preg_replace('/^>\s?/', '', $l), $lines));
      $html .= '<blockquote><p>' . nl2br(md_inline($q)) . '</p></blockquote>';
    } else {
      $html .= '<p>' . nl2br(md_inline($b)) . '</p>';
    }
  }
  return $html;
}

function md_plain(string $body): string {
  $s = preg_replace(['/!\[[^\]]*\]\([^)]*\)/', '/\[([^\]]+)\]\([^)]*\)/', '/^#{2,3}\s+/m', '/[*`>]/', '/^\s*[-]\s+/m'], ['', '$1', '', '', ''], $body);
  return trim((string) preg_replace('/\s+/u', ' ', (string) $s));
}

function excerpt(string $s, int $len = 160): string {
  $s = md_plain($s);
  return mb_strlen($s) > $len ? rtrim(mb_substr($s, 0, $len - 1)) . '…' : $s;
}

// ---------- E-posta (sunucunun mail() işlevi) ----------
function mail_error(?string $set = null): string {
  static $err = '';
  if ($set !== null) $err = $set;
  return $err;
}

function php_mail_ok(): bool {
  return function_exists('mail') && !in_array('mail', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
}

// Önce panelde girilen SMTP hesabıyla, yoksa PHP mail() ile gönderir. Hiçbir durumda sayfayı çökertmez.
function send_mail(string $to, string $subject, string $text): bool {
  mail_error('');
  if (!setting('mail_enabled', true) || !filter_var($to, FILTER_VALIDATE_EMAIL) || is_local()) return false;
  try {
    require_once __DIR__ . '/smtp.php';
    $host = preg_replace('/^www\./', '', strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'iseatolye.com.tr'))));
    $smtp = smtp_config();
    $from = trim((string) setting('mail_from', ''));
    // SMTP sunucuları genelde yalnızca giriş yapılan hesaptan göndermeye izin verir
    if ($smtp && filter_var($smtp['user'] ?? '', FILTER_VALIDATE_EMAIL)) $from = (string) $smtp['user'];
    if ($from === '') $from = 'bildirim@' . $host;
    $brand = (string) (content()['brand']['name'] ?? 'İSE ATÖLYE');
    $reply = (string) (content()['contact']['email'] ?? '');
    $body = $text . "\n\n—\n" . $brand . "\n" . site_url('/');
    if ($smtp) {
      $err = smtp_send($smtp, $from, $brand, $to, $subject, $body, $reply !== $from ? $reply : '');
      if ($err === '') return true;
      mail_error($err);
      error_log('E-posta gönderilemedi (SMTP): ' . $err);
      return false;
    }
    if (!php_mail_ok()) {
      mail_error('Sunucuda PHP mail() kapalı ve SMTP ayarı girilmemiş.');
      error_log('E-posta gönderilemedi: mail() kapalı, SMTP ayarı yok.');
      return false;
    }
    $name = '=?UTF-8?B?' . base64_encode($brand) . '?=';
    $headers = "From: $name <$from>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit"
      . ($reply !== '' ? "\r\nReply-To: $reply" : '');
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    // Bazı sunucular -f (gönderen) parametresine izin vermez; o durumda parametresiz denenir
    $ok = @mail($to, $subj, $body, $headers, '-f' . $from) || @mail($to, $subj, $body, $headers);
    if (!$ok) mail_error('Sunucu mail() ile göndermeyi reddetti.');
    return $ok;
  } catch (Throwable $ex) {
    mail_error($ex->getMessage());
    error_log('E-posta gönderilemedi: ' . $ex->getMessage());
    return false;
  }
}

function admin_email(): string {
  return trim((string) setting('notify_email', '')) ?: (string) (content()['contact']['email'] ?? '');
}

// Arama sonuçlarında görünen sayfa yolu (BreadcrumbList). $items: ['Başlık' => '/adres/', ...]; Ana sayfa başa eklenir.
function breadcrumb_ld(array $items): array {
  $list = []; $i = 1;
  foreach (['Ana sayfa' => '/'] + $items as $name => $url) $list[] = ['@type' => 'ListItem', 'position' => $i++, 'name' => (string) $name, 'item' => site_url($url)];
  return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
}
