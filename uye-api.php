<?php
// Üye işlemleri: "katılmayı düşünüyorum", etkinlik yorumu, yorum silme, kayıt iptali.
// fetch ile JSON döner; JavaScript yoksa geldiği sayfaya yönlendirir.
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';

$wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$back = safe_return(parse_url((string) ($_SERVER['HTTP_REFERER'] ?? '/'), PHP_URL_PATH) ?: '/');

function reply_out(bool $ok, string $msg, array $extra = [], string $anchor = ''): never {
  global $wantsJson, $back;
  if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!$ok) http_response_code(400);
    echo json_encode(['ok' => $ok, 'message' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
  }
  member_flash($msg, $ok ? 'ok' : 'err');
  header('Location: ' . $back . $anchor, true, 303);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /'); exit; }
if (!member_csrf_ok()) reply_out(false, 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.');
$action = (string) ($_POST['action'] ?? '');
$me = current_user();
$now = date('Y-m-d H:i');

// Kayıt iptali: üye kendi kaydını, misafir bilet bağlantısındaki anahtarla
if ($action === 'bilet-iptal') {
  $r = reg_by_code((string) ($_POST['code'] ?? ''));
  if (!$r) reply_out(false, 'Kayıt bulunamadı.');
  $own = ($me && ($r['user'] ?? '') === $me['id']) || hash_equals(reg_key($r), (string) ($_POST['k'] ?? ''));
  if (!$own) reply_out(false, 'Bu kaydı iptal etme yetkiniz yok.');
  if ($r['status'] === 'iptal') reply_out(true, 'Bu kayıt zaten iptal edilmiş.');
  $ev = event_by_id($r['event']);
  $s = $ev ? (is_package($ev) ? (event_sessions($ev, false)[0] ?? null) : session_by_id($ev, $r['session'])) : null;
  if (!$s || session_ts($s) <= time()) reply_out(false, 'Başlamış ya da geçmiş bir etkinliğin kaydı iptal edilemez.');
  json_update(REGS_FILE, function (array &$d) use ($r, $now) {
    foreach ($d['regs'] as &$x) if ($x['id'] === $r['id']) { $x['status'] = 'iptal'; $x['updated'] = $now; $x['admin_note'] = trim(($x['admin_note'] ?? '') . "\nKatılımcı iptal etti: " . $now); }
  }, ['regs' => []]);
  require_once __DIR__ . '/inc/mail.php';
  $to = admin_email();
  if ($to !== '') send_mail($to, 'Kayıt iptali: ' . $ev['title'] . ' (' . $r['code'] . ')', $r['name'] . ' kaydını iptal etti.' . "\n\n" . reg_summary(['status' => 'iptal'] + $r, $ev) . "\n\nYedek listedekileri panelden kayda alabilirsiniz: " . site_url('/yonetim/?s=katilimlar&etkinlik=' . rawurlencode($ev['id'])));
  $back = $me ? '/hesabim/' : ticket_url($r, true);
  reply_out(true, 'Kaydınız iptal edildi.');
}

if (!$me) reply_out(false, 'Bu işlem için giriş yapmalısınız.');
$ev = event_by_id((string) ($_POST['event'] ?? ''));
if (!$ev || !event_visible($ev)) reply_out(false, 'Etkinlik bulunamadı.');
$back = event_url($ev);

if ($action === 'ilgi') {
  if (!rate_hit('ilgi:' . $me['id'], 60)) reply_out(false, 'Çok fazla deneme yaptınız, biraz sonra tekrar deneyin.');
  $on = json_update(INTERESTS_FILE, function (array &$d) use ($ev, $me) {
    if (isset($d[$ev['id']][$me['id']])) { unset($d[$ev['id']][$me['id']]); if (empty($d[$ev['id']])) unset($d[$ev['id']]); return false; }
    $d[$ev['id']][$me['id']] = date('Y-m-d H:i');
    return true;
  });
  $count = count(json_read(INTERESTS_FILE)[$ev['id']] ?? []);
  reply_out(true, $on ? 'Katılmayı düşünenler arasına eklendiniz.' : 'Listeden çıkarıldınız.', ['on' => $on, 'count' => $count], '#katilimcilar');
}

if ($action === 'yorum') {
  if (($ev['comments'] ?? true) === false) reply_out(false, 'Bu etkinlik yorumlara kapalı.');
  $text = trim(str_replace("\r\n", "\n", (string) ($_POST['text'] ?? '')));
  $parent = (string) ($_POST['parent'] ?? '');
  if (mb_strlen($text) < 2) reply_out(false, 'Yorumunuz çok kısa.');
  if (mb_strlen($text) > 1500) reply_out(false, 'Yorumunuz en fazla 1500 karakter olabilir.');
  if (preg_match_all('#(https?://|www\.)#i', $text) > 1) reply_out(false, 'Yorumlarda en fazla 1 bağlantı olabilir.');
  if (!rate_hit('yorum:' . $me['id'], 10, 20)) reply_out(false, 'Kısa sürede çok fazla yorum gönderdiniz. Biraz sonra tekrar deneyin.');
  $mod = (bool) setting('comment_moderation', false);
  $res = json_update(COMMENTS_FILE, function (array &$d) use ($ev, $me, $text, $parent, $mod, $now) {
    $list = $d[$ev['id']] ?? [];
    if ($parent !== '' && !in_array($parent, array_column(array_filter($list, fn($x) => ($x['parent'] ?? '') === ''), 'id'), true)) $parent = '';
    foreach ($list as $x) if (($x['user'] ?? '') === $me['id'] && $x['text'] === $text) return null;
    $cm = ['id' => new_id(), 'user' => $me['id'], 'text' => $text, 'date' => $now, 'status' => $mod ? 'beklemede' : 'yayinda', 'parent' => $parent, 'admin' => false];
    $d[$ev['id']][] = $cm;
    return $cm;
  });
  if (!$res) reply_out(false, 'Bu yorumu zaten gönderdiniz.');
  if ($mod) reply_out(true, 'Teşekkürler! Yorumunuz onaylandıktan sonra yayınlanacak.', ['pending' => true], '#yorumlar');
  // Sayfa yenilenmeden eklemek için yorumun HTML'i
  $c = content(); $cm = $res; $sub = []; $tok = member_csrf(); $attendedIds = attendee_ids($ev, true); $past = event_is_past($ev);
  require_once __DIR__ . '/inc/icons.php';
  ob_start(); include __DIR__ . '/inc/comment.php'; $html = ob_get_clean();
  reply_out(true, 'Yorumunuz yayınlandı.', ['html' => $html, 'parent' => $res['parent']], '#yorum-' . $res['id']);
}

if ($action === 'yorum-sil') {
  $id = (string) ($_POST['id'] ?? '');
  $ok = json_update(COMMENTS_FILE, function (array &$d) use ($ev, $me, $id) {
    foreach ($d[$ev['id']] ?? [] as $k => $x) {
      if ($x['id'] !== $id || ($x['user'] ?? '') !== $me['id']) continue;
      array_splice($d[$ev['id']], $k, 1);
      // Yanıtları da kaldır
      $d[$ev['id']] = array_values(array_filter($d[$ev['id']], fn($y) => ($y['parent'] ?? '') !== $id));
      return true;
    }
    return false;
  });
  reply_out($ok, $ok ? 'Yorumunuz silindi.' : 'Yorum bulunamadı.', [], '#yorumlar');
}

reply_out(false, 'Geçersiz istek.');
