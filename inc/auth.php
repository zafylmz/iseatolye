<?php
// Üyelik: kayıt, giriş, oturum, "beni hatırla" ve profil yardımcıları.
// Üyeler data/users.json dosyasında tutulur; şifreler yalnızca özet (hash) olarak saklanır.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const USERS_FILE = DATA . '/users.json';
const RESETS_FILE = DATA . '/resets.json';
const MEMBER_SESSION = 'ise_uye';
const REMEMBER_COOKIE = 'ise_hatirla';
const REMEMBER_DAYS = 60;

function users_all(): array {
  static $u = null;
  if ($u === null) $u = json_read(USERS_FILE, ['users' => []])['users'] ?? [];
  return $u;
}

function users_map(): array {
  static $m = null;
  if ($m === null) { $m = []; foreach (users_all() as $u) $m[$u['id']] = $u; }
  return $m;
}

function user_by_id(?string $id): ?array {
  if (!$id) return null;
  return users_map()[$id] ?? null;
}

function user_by(string $field, string $value): ?array {
  $value = mb_strtolower(trim($value));
  foreach (users_all() as $u) if (mb_strtolower((string) ($u[$field] ?? '')) === $value) return $u;
  return null;
}

// Kullanıcı adı: ad soyaddan üretilir, benzersiz yapılır.
function unique_username(string $base, string $exceptId = ''): string {
  $base = substr(str_replace('-', '', slugify($base, 'uye')), 0, 20) ?: 'uye';
  if (strlen($base) < 3) $base .= 'uye';
  $taken = array_map(fn($u) => $u['username'], array_filter(users_all(), fn($u) => $u['id'] !== $exceptId));
  $reserved = ['admin', 'yonetim', 'iseatolye', 'ise', 'atolye', 'destek', 'uye', 'giris', 'cikis'];
  $name = $base; $n = 2;
  while (in_array($name, $taken, true) || in_array($name, $reserved, true)) $name = $base . $n++;
  return $name;
}

function user_url(array $u): string {
  return '/uye/' . rawurlencode($u['username']) . '/';
}

function user_public(array $u): bool {
  return ($u['status'] ?? 'aktif') === 'aktif';
}

// ---------- Oturum ----------
function member_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => https()]);
  session_name(MEMBER_SESSION);
  session_prepare();
  session_start();
}

// Ziyaretçide oturum çerezi yoksa oturum açılmaz (gereksiz çerez oluşmasın).
function current_user(): ?array {
  static $done = false, $user = null;
  if ($done) return $user;
  $done = true;
  if (empty($_COOKIE[MEMBER_SESSION]) && empty($_COOKIE[REMEMBER_COOKIE]) && session_status() !== PHP_SESSION_ACTIVE) return null;
  member_session();
  $id = (string) ($_SESSION['uid'] ?? '');
  if ($id === '' && !empty($_COOKIE[REMEMBER_COOKIE])) $id = remember_check();
  $u = user_by_id($id);
  // Şifre değişince diğer cihazlardaki açık oturumlar kapanır
  $pw = $u ? pw_mark($u) : '';
  if (!$u || ($u['status'] ?? 'aktif') !== 'aktif' || (isset($_SESSION['pw']) && !hash_equals($pw, (string) $_SESSION['pw']))) {
    unset($_SESSION['uid'], $_SESSION['pw']);
    return null;
  }
  $_SESSION['pw'] = $pw;
  return $user = $u;
}

function remember_check(): string {
  [$uid, $tok] = array_pad(explode(':', (string) $_COOKIE[REMEMBER_COOKIE], 2), 2, '');
  $u = user_by_id($uid);
  if (!$u || $tok === '') return '';
  foreach ($u['tokens'] ?? [] as $t) {
    if (($t['exp'] ?? 0) > time() && hash_equals((string) $t['h'], hash('sha256', $tok))) {
      session_regenerate_id(true);
      $_SESSION['uid'] = $uid;
      return $uid;
    }
  }
  setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
  return '';
}

function pw_mark(array $u): string { return substr(hash('sha256', (string) ($u['hash'] ?? '')), 0, 16); }

function user_login(array $u, bool $remember): void {
  member_session();
  session_regenerate_id(true);
  $_SESSION['uid'] = $u['id'];
  $_SESSION['pw'] = pw_mark($u);
  $tok = $remember ? bin2hex(random_bytes(24)) : '';
  json_update(USERS_FILE, function (array &$d) use ($u, $tok) {
    foreach ($d['users'] as &$x) {
      if ($x['id'] !== $u['id']) continue;
      $x['last_login'] = date('Y-m-d H:i');
      if ($tok !== '') {
        $x['tokens'] = array_values(array_filter($x['tokens'] ?? [], fn($t) => ($t['exp'] ?? 0) > time()));
        $x['tokens'][] = ['h' => hash('sha256', $tok), 'exp' => time() + REMEMBER_DAYS * 86400];
        $x['tokens'] = array_slice($x['tokens'], -5);
      }
    }
  }, ['users' => []]);
  if ($tok !== '') setcookie(REMEMBER_COOKIE, $u['id'] . ':' . $tok, ['expires' => time() + REMEMBER_DAYS * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => https()]);
}

function user_logout(): void {
  member_session();
  $uid = (string) ($_SESSION['uid'] ?? '');
  if ($uid !== '' && !empty($_COOKIE[REMEMBER_COOKIE])) {
    json_update(USERS_FILE, function (array &$d) use ($uid) {
      foreach ($d['users'] as &$x) if ($x['id'] === $uid) $x['tokens'] = [];
    }, ['users' => []]);
  }
  setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
  $_SESSION = [];
  session_destroy();
  setcookie(MEMBER_SESSION, '', ['expires' => time() - 3600, 'path' => '/']);
}

// Girişi zorunlu sayfalar için: giriş yoksa giriş sayfasına yönlendirir, geri dönüş adresiyle.
function require_user(): array {
  $u = current_user();
  if ($u) return $u;
  header('Location: /giris/?donus=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/')), true, 303);
  exit;
}

function safe_return(string $to): string {
  return preg_match('#^/(?!/)[^\s\\\\]*$#', $to) ? $to : '/hesabim/';
}

function member_csrf(): string {
  member_session();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}

function member_csrf_ok(): bool {
  member_session();
  return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? '')) && same_origin();
}

function member_flash(?string $msg = null, string $type = 'ok'): ?array {
  member_session();
  if ($msg !== null) { $_SESSION['mflash'] = [$msg, $type]; return null; }
  $f = $_SESSION['mflash'] ?? null;
  unset($_SESSION['mflash']);
  return $f;
}

// Avatar: yüklenmiş fotoğraf ya da baş harf.
function avatar(?array $u, string $size = ''): string {
  $cls = 'avatar' . ($size !== '' ? ' avatar--' . $size : '');
  if (!$u) return '<span class="' . $cls . ' avatar--ghost" aria-hidden="true">?</span>';
  if (!empty($u['avatar'])) return '<span class="' . $cls . '"><img src="' . e($u['avatar']) . '" alt="" loading="lazy" decoding="async"></span>';
  // Baş harfe göre üç sıcak tondan biri (tek vurgu rengi korunur, tonlar bej ailesinden)
  $tone = hexdec(substr(md5((string) ($u['id'] ?? $u['name'] ?? '')), 0, 2)) % 3;
  return '<span class="' . $cls . ' avatar--t' . $tone . '" aria-hidden="true">' . e(initial((string) ($u['name'] ?? '?'))) . '</span>';
}

function display_name(?array $u): string {
  return $u ? (string) $u['name'] : 'Eski üye';
}

// Üye fotoğrafı: kare kırpılır, 480 piksele küçültülür.
function store_avatar(array $file): ?string {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Fotoğraf yüklenemedi.');
  if ($file['size'] > 6 * 1024 * 1024) throw new RuntimeException('Fotoğraf 6 MB\'tan büyük olamaz.');
  $info = @getimagesize($file['tmp_name']);
  if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new RuntimeException('Yalnızca JPG, PNG ya da WEBP fotoğraf yükleyebilirsiniz.');
  $dir = ROOT . '/uploads/uyeler';
  if (!is_dir($dir)) mkdir($dir, 0755, true);
  $name = 'uye-' . bin2hex(random_bytes(6)) . '.jpg';
  if (!function_exists('imagecreatetruecolor')) {
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Fotoğraf kaydedilemedi.');
    return '/uploads/uyeler/' . $name;
  }
  $src = match ($info[2]) { IMAGETYPE_JPEG => imagecreatefromjpeg($file['tmp_name']), IMAGETYPE_PNG => imagecreatefrompng($file['tmp_name']), IMAGETYPE_WEBP => imagecreatefromwebp($file['tmp_name']) };
  if (!$src) throw new RuntimeException('Fotoğraf okunamadı.');
  $side = min($info[0], $info[1]);
  $sx = (int) (($info[0] - $side) / 2); $sy = (int) (($info[1] - $side) / 2);
  $out = min(480, $side);
  $dst = imagecreatetruecolor($out, $out);
  imagefill($dst, 0, 0, imagecolorallocate($dst, 250, 246, 240));
  imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $out, $out, $side, $side);
  imagejpeg($dst, $dir . '/' . $name, 85);
  return '/uploads/uyeler/' . $name;
}

function delete_upload(?string $path): void {
  if (!$path || !str_starts_with($path, '/uploads/')) return;
  $file = realpath(ROOT . $path);
  $base = realpath(ROOT . '/uploads');
  if ($file && $base && str_starts_with($file, $base . DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
}
