<?php
// Blog için beğeni ve yorum alma uç noktası. Tarayıcıdan fetch ile JSON döner;
// JavaScript yoksa yazı sayfasına geri yönlendirir.
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/blog.php';

$wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$slug = (string) ($_POST['slug'] ?? '');

function done(bool $ok, string $msg, array $extra = []): never {
  global $wantsJson, $slug;
  if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!$ok) http_response_code(400);
    echo json_encode(['ok' => $ok, 'message' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
  }
  $to = preg_match('/^[a-z0-9-]+$/', $slug) ? '/blog/' . $slug . '/' : '/blog/';
  header('Location: ' . $to . ($ok ? '' : '?hata=1') . '#yorumlar', true, 303);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /blog/'); exit; }

// Başka bir siteden gönderilen formları reddet
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
if ($origin !== '' && strtolower((string) parse_url($origin, PHP_URL_HOST)) !== strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')))) done(false, 'Geçersiz istek.');

$post = blog_find($slug);
if (!$post || empty($post['published'])) done(false, 'Yazı bulunamadı.');
$hash = client_hash();
$now = time();

$action = (string) ($_POST['action'] ?? '');

if ($action === 'like') {
  if (!rate_hit('like:' . $hash, 40)) done(false, 'Çok fazla deneme yaptınız, biraz sonra tekrar deneyin.');
  $cookie = liked_cookie();
  [$count, $liked] = json_update(BLOG_LIKES_FILE, function (array &$d) use ($slug, $hash, $cookie) {
    $e = $d[$slug] ?? ['count' => 0, 'ips' => []];
    // Beğeni yalnızca bu ziyaretçinin kaydı listede varsa geri alınır; çerez tek başına sayıyı düşüremez
    $was = in_array($hash, $e['ips'], true) || in_array($slug, $cookie, true);
    if (in_array($hash, $e['ips'], true)) {
      $e['ips'] = array_values(array_diff($e['ips'], [$hash]));
      $e['count'] = max(0, $e['count'] - 1);
    } elseif ($was) {
      // Başka bir ağdan beğenmiş: yalnızca bu tarayıcıdaki işaret kaldırılır
    } else {
      $e['ips'][] = $hash;
      $e['ips'] = array_slice($e['ips'], -5000);
      $e['count']++;
    }
    $d[$slug] = $e;
    return [$e['count'], !$was];
  });
  $cookie = $liked ? array_values(array_unique([...$cookie, $slug])) : array_values(array_diff($cookie, [$slug]));
  setcookie('ise_begeni', implode(',', array_slice($cookie, -100)), ['expires' => $now + 31536000, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
  done(true, $liked ? 'Beğendiniz, teşekkürler.' : 'Beğeninizi geri aldınız.', ['count' => $count, 'liked' => $liked]);
}

if ($action === 'comment') {
  if (($post['comments'] ?? true) === false) done(false, 'Bu yazı yorumlara kapalı.');
  $sig = (string) ($_POST['sig'] ?? '');
  $name = trim((string) ($_POST['name'] ?? ''));
  $email = trim((string) ($_POST['email'] ?? ''));
  $text = trim(str_replace("\r\n", "\n", (string) ($_POST['text'] ?? '')));
  // Bot koruması: gizli alan, imzalı süre ve tarayıcıda çözülen küçük işlem
  $g = guard_check('blog|' . $slug);
  if ($g === 'bot') done(true, 'Yorumunuz alındı, onaylandıktan sonra yayınlanacak.');
  if ($g !== '') done(false, $g);
  // 4) İçerik kontrolleri
  if (mb_strlen($name) < 2 || mb_strlen($name) > 60) done(false, 'Lütfen adınızı yazın (2-60 karakter).');
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) done(false, 'E-posta adresi geçerli görünmüyor.');
  if (mb_strlen($text) < 3) done(false, 'Yorumunuz çok kısa.');
  if (mb_strlen($text) > 2000) done(false, 'Yorumunuz en fazla 2000 karakter olabilir.');
  if (preg_match_all('#(https?://|www\.)#i', $name . ' ' . $text) > COMMENT_MAX_LINKS) done(false, 'Yorumlarda en fazla ' . COMMENT_MAX_LINKS . ' bağlantı olabilir.');
  if (preg_match('#https?://|www\.#i', $name)) done(false, 'Ad alanına bağlantı yazılamaz.');
  // 5) Aynı IP'den hız sınırı
  if (!rate_hit('comment:' . $hash, COMMENT_PER_HOUR, COMMENT_GAP)) done(false, 'Kısa sürede çok fazla yorum gönderdiniz. Lütfen biraz sonra tekrar deneyin.');

  $saved = json_update(BLOG_COMMENTS_FILE, function (array &$d) use ($slug, $sig, $name, $email, $text, $hash, $now) {
    foreach ($d[$slug] ?? [] as $old) {
      // Aynı form ikinci kez ya da aynı yorum tekrar gönderilmesin
      if (($old['sig'] ?? '') === $sig || (($old['ip'] ?? '') === $hash && $old['text'] === $text)) return false;
    }
    $d[$slug][] = [
      'id' => bin2hex(random_bytes(4)), 'name' => $name, 'email' => $email, 'text' => $text,
      'date' => date('Y-m-d H:i', $now), 'status' => 'pending', 'reply' => '',
      'ip' => $hash, 'sig' => $sig,
    ];
    return true;
  });
  if (!$saved) done(false, 'Bu yorumu zaten gönderdiniz.');
  done(true, 'Teşekkürler! Yorumunuz onaylandıktan sonra yayınlanacak.');
}

done(false, 'Geçersiz istek.');
