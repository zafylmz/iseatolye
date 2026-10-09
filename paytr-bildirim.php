<?php
// PayTR ödeme bildirimi (Bildirim URL). PayTR her ödeme denemesinin sonucunu sunucudan sunucuya buraya gönderir.
// İmza doğrulanmadan hiçbir şey yapılmaz. PayTR yanıt olarak yalnızca "OK" bekler; alamazsa bildirimi tekrarlar.
// Aynı bildirim birden fazla gelebilir, kayıt yalnızca bir kez "ödendi" yapılır.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/paytr.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
$cfg = paytr_config();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cfg) { http_response_code(404); exit; }
$p = $_POST;
if (!paytr_verify($cfg, $p)) { error_log('PayTR bildirimi: imza hatalı (' . substr((string) ($p['merchant_oid'] ?? ''), 0, 40) . ')'); http_response_code(400); echo 'PAYTR notification failed: bad hash'; exit; }
$oid = (string) $p['merchant_oid'];
$r = reg_by_code(paytr_code_of_oid($oid));
if (!$r) { error_log('PayTR bildirimi: kayıt bulunamadı (' . $oid . ')'); echo 'OK'; exit; }
if (($p['status'] ?? '') !== 'success') {
  error_log('PayTR ödeme başarısız (' . $r['code'] . '): ' . ($p['failed_reason_code'] ?? '') . ' ' . ($p['failed_reason_msg'] ?? ''));
  echo 'OK'; exit;
}
$test = !empty($p['test_mode']);
// Canlı moddayken gelen test ödemesi reddedilir (herkese açık test kartlarıyla bilet alınamasın)
if ($test && empty($cfg['test'])) { error_log('PayTR: canlı modda test bildirimi reddedildi (' . $oid . ')'); echo 'OK'; exit; }
$alert = function (string $why) use ($r, $p, $oid) {
  error_log('PayTR bildirimi (' . $r['code'] . '): ' . $why);
  if (($to = admin_email()) !== '') send_mail($to, 'İADE GEREKEBİLİR: ' . $r['code'], $why . "\nPayTR sipariş no: " . $oid . "\nTahsil edilen: " . money(((int) ($p['total_amount'] ?? 0)) / 100) . "\nKayıt tutarı: " . money($r['total']) . "\n\nPanel: " . site_url('/yonetim/?s=kayit&id=' . rawurlencode($r['id'])));
};
require_once __DIR__ . '/inc/mail.php';
if ((int) ($p['total_amount'] ?? 0) < paytr_kurus((float) $r['total'])) { $alert('Ödenen tutar kayıt tutarından az; kayıt "ödendi" yapılmadı.'); echo 'OK'; exit; }
if (!empty($r['paid']) && ($r['pay_ref'] ?? '') !== $oid && !str_starts_with((string) ($r['pay_ref'] ?? ''), $oid)) { $alert('Bu kayıt zaten ödenmişti, ikinci bir kart ödemesi alındı.'); echo 'OK'; exit; }
$upd = json_update(REGS_FILE, function (array &$d) use ($r, $oid, $test) {
  foreach ($d['regs'] as &$x) {
    if ($x['id'] !== $r['id']) continue;
    if (!empty($x['paid'])) return null;
    $x['method'] = 'kart';
    $x['pay_ref'] = $oid . ($test ? ' (TEST, gerçek ödeme değil)' : '');
    // Test ödemesi kaydı "ödendi" yapmaz; yalnızca akışın çalıştığı görülür
    if ($test) { $x['test_paid'] = true; $x['updated'] = date('Y-m-d H:i'); return null; }
    $x['paid'] = true;
    if ($x['status'] === 'beklemede') $x['status'] = 'onayli';
    $x['updated'] = date('Y-m-d H:i');
    return $x;
  }
  return null;
}, ['regs' => []]);
if ($test && ($to = admin_email()) !== '') send_mail($to, 'PayTR test ödemesi başarılı: ' . $r['code'], "Test modunda ödeme akışı çalıştı. Kayıt \"ödendi\" yapılmadı.\nPayTR sipariş no: " . $oid);
if ($upd && ($ev = event_by_id($upd['event']))) {
  try {
    mail_reg_update($upd, $ev, 'odeme');
    if (($to = admin_email()) !== '') send_mail($to, 'Kartla ödeme alındı: ' . $ev['title'] . ' (' . $upd['code'] . ')', $upd['name'] . ' · ' . $upd['email'] . "\nTutar: " . money($upd['total']) . "\nPayTR sipariş no: " . $upd['pay_ref'] . ($upd['status'] === 'iptal' ? "\n\nDikkat: bu kayıt iptal edilmiş durumda, ücret iadesi gerekebilir." : '') . "\n\nPanel: " . site_url('/yonetim/?s=kayit&id=' . rawurlencode($upd['id'])));
  } catch (Throwable $ex) { error_log('PayTR bildirimi e-posta hatası: ' . $ex->getMessage()); }
}
echo 'OK';
