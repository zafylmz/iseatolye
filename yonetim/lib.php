<?php
// Yönetim paneli yardımcıları: oturum, şifre, CSRF, kaydetme ve görsel yükleme.
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once ROOT . '/inc/auth.php';
require_once ROOT . '/inc/events.php';
require_once ROOT . '/inc/media.php';

const AUTH_FILE = DATA . '/auth.php';
const BACKUP_DIR = DATA . '/yedek';
const UPLOAD_DIR = ROOT . '/uploads';
const MAX_UPLOAD = 10 * 1024 * 1024;

session_set_cookie_params([
  'lifetime' => 0, 'path' => '/yonetim', 'httponly' => true, 'samesite' => 'Strict', 'secure' => https(),
]);
session_name('ise_panel');
session_prepare();
session_start();

const SETUP_KEY_FILE = DATA . '/kurulum-anahtari.txt';

function has_password(): bool { return is_file(AUTH_FILE); }

function auth_data(): array {
  static $d = null;
  if ($d === null) { $d = is_file(AUTH_FILE) ? (include AUTH_FILE) : []; if (!is_array($d)) $d = []; }
  return $d;
}

function password_hash_stored(): string { return (string) (auth_data()['hash'] ?? ''); }

// Şifre her değiştiğinde yenilenir; eski oturumlar geçersiz olur
function auth_version(): string { return (string) (auth_data()['v'] ?? ''); }

function save_password(string $plain): void {
  $v = bin2hex(random_bytes(8));
  $data = "<?php\nreturn " . var_export(['hash' => password_hash($plain, PASSWORD_DEFAULT), 'v' => $v], true) . ";\n";
  if (@file_put_contents(AUTH_FILE, $data, LOCK_EX) === false || !is_file(AUTH_FILE)) throw new RuntimeException('Şifre kaydedilemedi: data klasörüne yazılamıyor. Dosya Yöneticisi\'nde data klasörünün izinlerini 755 yapın.');
  @chmod(AUTH_FILE, 0600);
  if (function_exists('opcache_invalidate')) @opcache_invalidate(AUTH_FILE, true);
  $_SESSION['pv'] = $v;
}

// İlk kurulumda şifreyi yalnızca sunucuya erişebilen kişi belirleyebilsin diye data/ altında tek kullanımlık anahtar tutulur
function setup_key(): string {
  $k = is_file(SETUP_KEY_FILE) ? trim((string) file_get_contents(SETUP_KEY_FILE)) : '';
  if (strlen($k) < 8) {
    $k = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
    if (@file_put_contents(SETUP_KEY_FILE, $k . "\n", LOCK_EX) === false) return '';
    @chmod(SETUP_KEY_FILE, 0600);
  }
  return $k;
}

function logged_in(): bool { return !empty($_SESSION['ok']) && has_password() && hash_equals(auth_version(), (string) ($_SESSION['pv'] ?? '')); }

function csrf(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}

function check_csrf(): void {
  if (!hash_equals(csrf(), (string) ($_POST['csrf'] ?? '')) || !same_origin()) {
    http_response_code(400);
    exit('Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.');
  }
}

function flash(?string $msg = null, string $type = 'ok'): ?array {
  if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $f;
}

function redirect(string $to): never {
  header('Location: ' . $to, true, 303);
  exit;
}

function hidden(string $tok, string $action, array $extra = []): string {
  $h = '<input type="hidden" name="csrf" value="' . e($tok) . '"><input type="hidden" name="action" value="' . e($action) . '">';
  foreach ($extra as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
  return $h;
}

// Her kayıttan önce dosyanın bir kopyası data/yedek/ altına alınır; her dosyadan son 20 kopya tutulur.
function backup_file(string $file): void {
  if ($n = db_doc($file)) {
    $q = db()->prepare('SELECT veri FROM ise_belgeler WHERE ad = ?'); $q->execute([$n]);
    if (($v = $q->fetchColumn()) !== false) db_backup(db(), $n, (string) $v);
    return;
  }
  if (!is_file($file)) return;
  if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0755, true);
  $base = basename($file, '.json');
  copy($file, BACKUP_DIR . '/' . $base . '-' . date('Ymd-His') . '.json');
  $all = glob(BACKUP_DIR . '/' . $base . '-*.json') ?: [];
  sort($all);
  foreach (array_slice($all, 0, max(0, count($all) - 20)) as $old) @unlink($old);
}

function write_json(string $file, array $data): void {
  backup_file($file);
  $tmp = $file . '.tmp';
  file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
  rename($tmp, $file);
}

function save_content(array $c): void { write_json(CONTENT_FILE, $c); }

// Etkinlik kataloğunu kilitli olarak günceller (yedek alınır).
function catalog_update(callable $fn) {
  backup_file(EVENTS_FILE);
  return json_update(EVENTS_FILE, function (array &$d) use ($fn) {
    $d += ['categories' => [], 'venues' => [], 'instructors' => [], 'events' => []];
    return $fn($d);
  }, ['categories' => [], 'venues' => [], 'instructors' => [], 'events' => []]);
}

function regs_update(callable $fn) {
  return json_update(REGS_FILE, function (array &$d) use ($fn) { $d['regs'] ??= []; return $fn($d); }, ['regs' => []]);
}

function post(string $key, string $default = ''): string {
  return trim(str_replace("\r\n", "\n", (string) ($_POST[$key] ?? $default)));
}

function post_int(string $key, int $min = 0, int $max = PHP_INT_MAX): int {
  return max($min, min($max, (int) ($_POST[$key] ?? 0)));
}

function lines(string $s): array {
  return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r\n", "\n", $s))), fn($l) => $l !== ''));
}

// "1.250", "1250", "1250,50" ve "1250.50" yazımlarını sayıya çevirir
function price_input(string $s): float {
  $s = str_replace([' ', '₺', 'TL'], '', trim($s));
  if (preg_match('/^\d+[.,]\d{1,2}$/', $s)) return max(0, round((float) str_replace(',', '.', $s), 2));
  return max(0, round((float) str_replace(['.', ','], ['', '.'], $s), 2));
}

function valid_date(string $s): string { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : ''; }
function valid_time(string $s): string { return preg_match('/^\d{2}:\d{2}$/', $s) ? $s : ''; }

// Bir dosya alanındaki tek ya da çoklu yüklemeyi düz bir listeye çevirir.
function files_of(string $field): array {
  if (empty($_FILES[$field])) return [];
  $f = $_FILES[$field];
  if (!is_array($f['name'])) return [$f];
  $out = [];
  foreach ($f['name'] as $i => $_) {
    $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
  }
  return $out;
}

// Görseli doğrular; telefon fotoğraflarını düz çevirir, en fazla 1920 piksele küçültüp sıkıştırarak /uploads altına kaydeder.
// Saydamlık kullanmayan PNG'ler JPG olur. Büyük orijinal sunucuda tutulmaz. Kaydedilen her görsel kütüphaneye (Galeri) eklenir.
function store_image(array $file, string $prefix, string $sub = '', array $meta = []): ?string {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('Görsel sunucunun izin verdiği boyuttan büyük (' . ini_get('upload_max_filesize') . ').');
  if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Görsel yüklenemedi (hata kodu ' . $file['error'] . ').');
  if ($file['size'] > MAX_UPLOAD) throw new RuntimeException('Görsel 10 MB\'tan büyük olamaz.');
  $info = @getimagesize($file['tmp_name']);
  $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
  if (!$info || !isset($types[$info[2]])) throw new RuntimeException('Yalnızca JPG, PNG, WEBP ya da GIF görsel yükleyebilirsiniz.');
  $ext = $types[$info[2]];
  $dir = UPLOAD_DIR . ($sub !== '' ? '/' . $sub : '');
  if (!is_dir($dir)) mkdir($dir, 0755, true);
  $base = $dir . '/' . substr(slugify($prefix, 'gorsel'), 0, 50) . '-' . bin2hex(random_bytes(4));
  $out = image_optimize($file['tmp_name'], $ext, $base);
  if ($out !== '') $ext = $out;
  elseif (!move_uploaded_file($file['tmp_name'], $base . '.' . $ext)) throw new RuntimeException('Görsel kaydedilemedi. uploads klasörünün yazma izni olduğundan emin olun.');
  $dest = $base . '.' . $ext;
  $name = basename($dest);
  @chmod($dest, 0644);
  $path = '/uploads/' . ($sub !== '' ? $sub . '/' : '') . $name;
  media_save($path, $meta + ['added' => date('Y-m-d H:i:s')]);
  make_thumb($path);
  return $path;
}

// Görseller artık kütüphanede kalır; yalnızca Galeri bölümünden silinir.
function drop_image(?string $path): void {}

function media_save(string $path, array $fields): void {
  json_update(MEDIA_FILE, function (array &$d) use ($path, $fields) {
    $d += ['items' => [], 'albums' => []];
    $d['items'][$path] = array_filter($fields + ($d['items'][$path] ?? []), fn($v) => $v !== '' && $v !== null);
  }, ['items' => [], 'albums' => []]);
  media_meta(true);
}

// Görselin sitede nerelerde kullanıldığı: [['label' => ..., 'url' => ...], ...]
function media_usage(): array {
  $use = [];
  $add = function (string $p, string $label, string $url) use (&$use) { if ($p !== '') $use[$p][] = ['label' => $label, 'url' => $url]; };
  $cat = catalog();
  foreach ($cat['events'] ?? [] as $ev) {
    $u = './?s=etkinlik&id=' . rawurlencode($ev['id']) . '#gorseller';
    $add((string) ($ev['cover'] ?? ''), 'Kapak: ' . $ev['title'], $u);
    foreach ($ev['gallery'] ?? [] as $g) $add((string) $g, 'Galeri: ' . $ev['title'], $u);
  }
  foreach ($cat['venues'] ?? [] as $v) $add((string) ($v['photo'] ?? ''), 'Mekan: ' . $v['name'], './?s=mekanlar&duzenle=' . rawurlencode($v['id']) . '#mekanlar');
  foreach ($cat['instructors'] ?? [] as $x) $add((string) ($x['photo'] ?? ''), 'Eğitmen: ' . $x['name'], './?s=mekanlar&duzenle=' . rawurlencode($x['id']) . '#egitmenler');
  foreach (json_read(BLOG_FILE)['posts'] ?? [] as $p) {
    $u = './?s=yazi&slug=' . rawurlencode($p['slug']);
    $add((string) ($p['cover'] ?? ''), 'Blog kapağı: ' . $p['title'], $u);
    if (preg_match_all('#\]\((/uploads/[^)\s]+)\)#', (string) ($p['body'] ?? ''), $m)) foreach (array_unique($m[1]) as $g) $add($g, 'Blog yazısı: ' . $p['title'], $u);
  }
  $c = content();
  $add((string) ($c['home']['hero_image'] ?? ''), 'Ana sayfa görseli', './?s=sayfalar');
  $add((string) ($c['about']['image'] ?? ''), 'Hakkımızda görseli', './?s=sayfalar&t=hakkimizda');
  foreach ($c['about']['gallery'] ?? [] as $g) $add((string) $g, 'Hakkımızda galerisi', './?s=sayfalar&t=hakkimizda');
  $add((string) ($c['corporate']['image'] ?? ''), 'Kurumsal görseli', './?s=sayfalar&t=kurumsal');
  $add((string) ($c['brand']['logo'] ?? ''), 'Logo', './?s=sayfalar&t=marka');
  $add((string) ($c['brand']['og_image'] ?? ''), 'Paylaşım görseli', './?s=sayfalar&t=marka');
  return $use;
}

// ---------- Galeriden görsel seçme alanı ----------
// Tek görsel: image_field('cover', $ev['cover']); çoklu: image_field('gallery', $ev['gallery'], ['multi' => true]).
// Form gönderildiğinde image_pick / gallery_pick ile okunur.
function image_field(string $name, $current, array $o = []): string {
  $multi = !empty($o['multi']);
  $list = array_values(array_filter($multi ? (array) $current : [(string) $current], fn($p) => is_string($p) && $p !== ''));
  $input = $name . ($multi ? '_sec[]' : '_sec');
  $h = '<div class="imgf' . ($multi ? ' imgf--multi' : '') . (!empty($o['shape']) ? ' imgf--' . e($o['shape']) : '') . '" data-imgf data-input="' . e($input) . '" data-multi="' . ($multi ? '1' : '0') . '">';
  $h .= '<input type="hidden" name="' . e($name) . '__on" value="1">';
  if (!$multi) $h .= '<input type="hidden" name="' . e($input) . '" value="">';
  $h .= '<div class="imgf__list" data-imgf-list>';
  foreach ($list as $p) $h .= image_field_item($p, $input);
  $h .= '</div><div class="imgf__bar">';
  $h .= '<button type="button" class="btn btn--ghost btn--sm" data-pick>' . e($o['button'] ?? ($multi ? 'Galeriden ekle' : 'Galeriden seç')) . '</button>';
  if (!empty($o['hint'])) $h .= '<span class="hint">' . e($o['hint']) . '</span>';
  $h .= '</div></div>';
  return $h;
}

function image_field_item(string $p, string $input): string {
  return '<figure class="imgf__item" draggable="true"><img src="' . e(thumb_url($p)) . '" alt="" loading="lazy"><input type="hidden" name="' . e($input) . '" value="' . e($p) . '"><button type="button" class="imgf__x" data-imgf-x aria-label="Kaldır" title="Kaldır">✕</button></figure>';
}

// Form alanı gönderilmediyse eski değer korunur.
function image_pick(string $name, string $current): string {
  if (empty($_POST[$name . '__on'])) return $current;
  $v = $_POST[$name . '_sec'] ?? '';
  if (is_array($v)) $v = (string) end($v);
  // Mevcut değer (ör. kökteki /og.jpg) galeride olmasa da korunur
  return (string) $v === $current ? $current : media_valid((string) $v);
}

function gallery_pick(string $name, array $current): array {
  if (empty($_POST[$name . '__on'])) return $current;
  $out = [];
  foreach ((array) ($_POST[$name . '_sec'] ?? []) as $v) {
    if (!is_string($v)) continue;
    $p = in_array($v, $current, true) ? $v : media_valid($v);
    if ($p !== '' && !in_array($p, $out, true)) $out[] = $p;
  }
  return $out;
}

// Panelde kullanılan küçük biçimlendiriciler
function pill(string $text, string $tone = ''): string {
  return '<span class="pill' . ($tone !== '' ? ' pill--' . $tone : '') . '">' . e($text) . '</span>';
}

function reg_tone(string $status): string {
  return ['onayli' => 'on', 'beklemede' => 'warn', 'yedek' => 'info', 'iptal' => 'off'][$status] ?? '';
}

function event_tone(string $status): string {
  return ['yayinda' => 'on', 'taslak' => '', 'ertelendi' => 'warn', 'iptal' => 'off'][$status] ?? '';
}

function reg_session_label(array $r, ?array $ev): string {
  if (!$ev) return '–';
  if (($r['session'] ?? '') === '*') return 'Tüm oturumlar';
  $s = session_by_id($ev, $r['session'] ?? '');
  return $s ? session_when($s, false) : 'Silinmiş oturum';
}

function panel_url(array $q): string {
  return './?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null));
}
