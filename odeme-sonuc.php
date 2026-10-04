<?php
// iyzico ödeme dönüşü. iyzico ödeme sayfasından sonra buraya "token" gönderir.
// Sonuç her zaman iyzico'dan yeniden sorgulanır; gelen veriye tek başına güvenilmez.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/iyzico.php';
$token = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($_POST['token'] ?? $_GET['token'] ?? ''));
if ($token === '') { header('Location: /', true, 303); exit; }
$res = iyzico_result($token);
$r = by_id(regs_all(), (string) ($res['basketId'] ?? ''));
if (!$r) { error_log('iyzico dönüşü: kayıt bulunamadı (' . ($res['errorMessage'] ?? $res['basketId'] ?? '') . ')'); header('Location: /hesabim/', true, 303); exit; }
$back = ticket_url($r, true);
$ok = ($res['status'] ?? '') === 'success' && ($res['paymentStatus'] ?? '') === 'SUCCESS'
  && (string) ($res['conversationId'] ?? '') === $r['code'] && (float) ($res['paidPrice'] ?? 0) >= (float) $r['total'] - 0.01;
if (!$ok) {
  if (($res['status'] ?? '') !== 'success' || ($res['paymentStatus'] ?? '') !== 'FAILURE') error_log('iyzico dönüşü (' . $r['code'] . '): ' . ($res['paymentStatus'] ?? '') . ' ' . ($res['errorCode'] ?? '') . ' ' . ($res['errorMessage'] ?? ''));
  header('Location: ' . $back . '&odeme=hata', true, 303); exit;
}
$upd = json_update(REGS_FILE, function (array &$d) use ($r, $res) {
  foreach ($d['regs'] as &$x) {
    if ($x['id'] !== $r['id']) continue;
    if (!empty($x['paid'])) return null;
    $x['paid'] = true;
    $x['method'] = 'iyzico';
    $x['pay_ref'] = (string) ($res['paymentId'] ?? '');
    if ($x['status'] === 'beklemede') $x['status'] = 'onayli';
    $x['updated'] = date('Y-m-d H:i');
    return $x;
  }
  return null;
}, ['regs' => []]);
if ($upd && ($ev = event_by_id($upd['event']))) {
  require_once __DIR__ . '/inc/mail.php';
  mail_reg_update($upd, $ev, 'odeme');
  if (($to = admin_email()) !== '') send_mail($to, 'Kartla ödeme alındı: ' . $ev['title'] . ' (' . $upd['code'] . ')', $upd['name'] . ' · ' . $upd['email'] . "\nTutar: " . money($upd['total']) . "\niyzico ödeme no: " . $upd['pay_ref'] . ($upd['status'] === 'iptal' ? "\n\nDikkat: bu kayıt iptal edilmiş durumda, ücret iadesi gerekebilir." : '') . "\n\nPanel: " . site_url('/yonetim/?s=kayit&id=' . rawurlencode($upd['id'])));
}
header('Location: ' . $back . '&odeme=ok', true, 303);
