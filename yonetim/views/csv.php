<?php
// Katılım listesini Excel'de açılabilen CSV olarak indirir (Türkçe karakterler için UTF-8 BOM, ; ayraç).
/** @var array $regs */
if (($_GET['tur'] ?? '') === 'fatura') {
  // Fatura listesi: muhasebe programına ya da mali müşavire gönderilebilir
  $tab = (string) ($_GET['d'] ?? 'kesilecek'); $fm = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['ay'] ?? '')) ? $_GET['ay'] : '';
  $rows = array_values(array_filter(regs_all(), function ($r) use ($tab, $fm) {
    $has = trim((string) ($r['inv_no'] ?? '')) !== '';
    if ((float) $r['total'] <= 0 || !($r['paid'] || $has)) return false;
    $ok = $tab === 'kesildi' ? $has && $r['status'] !== 'iptal' : ($tab === 'iptal' ? $has && $r['status'] === 'iptal' : invoice_due($r));
    return $ok && ($fm === '' || str_starts_with((string) ($tab === 'kesildi' ? ($r['inv_date'] ?: $r['created']) : $r['created']), $fm));
  }));
  usort($rows, fn($a, $b) => strcmp($a['created'], $b['created']));
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="faturalar-' . $tab . ($fm !== '' ? '-' . $fm : '') . '-' . date('Ymd') . '.csv"');
  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF");
  $cell = fn($v) => preg_match('/^[\s]*[=+\-@\t\r]/u', (string) $v) ? "'" . $v : (string) $v;
  fputcsv($out, ['Kod', 'Kayıt tarihi', 'Fatura türü', 'Alıcı', 'Vergi dairesi', 'Vergi no / TCKN', 'Adres', 'E-posta', 'Hizmet', 'Adet', 'Tutar (KDV dahil)', 'Matrah', 'KDV', 'KDV oranı', 'Fatura no', 'Fatura tarihi', 'Kayıt durumu'], ';');
  foreach ($rows as $r) {
    $rev = event_by_id($r['event']); $iv = $r['invoice'] ?? []; [$n, $v, $rate] = vat_split((float) $r['total']);
    fputcsv($out, array_map($cell, [$r['code'], $r['created'], invoice_corporate($r) ? 'Kurumsal' : 'Bireysel', invoice_corporate($r) ? $iv['title'] : $r['name'], $iv['tax_office'] ?? '', $iv['tax_no'] ?? '', $iv['address'] ?? '', $r['email'], ($rev['title'] ?? '') . ' (' . $r['ticket_name'] . ')', $r['qty'], number_format((float) $r['total'], 2, ',', ''), number_format($n, 2, ',', ''), number_format($v, 2, ',', ''), $rate, $r['inv_no'] ?? '', $r['inv_date'] ?? '', REG_STATUS[$r['status']] ?? $r['status']]), ';');
  }
  exit;
}
$fe = (string) ($_GET['etkinlik'] ?? ''); $fs = (string) ($_GET['oturum'] ?? ''); $fst = (string) ($_GET['durum'] ?? '');
$rows = array_values(array_filter(regs_all(), fn($r) => ($fe === '' || $r['event'] === $fe) && ($fs === '' || $r['session'] === $fs) && ($fst === '' || $r['status'] === $fst)));
usort($rows, fn($a, $b) => strcmp($a['created'], $b['created']));
$ev = $fe !== '' ? event_by_id($fe) : null;
$name = 'katilimlar-' . ($ev ? $ev['slug'] : 'tum') . '-' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
$cell = fn($v) => preg_match('/^[\s]*[=+\-@\t\r]/u', (string) $v) ? "'" . $v : (string) $v; // formül enjeksiyonunu önler
fputcsv($out, ['Kod', 'Ad soyad', 'E-posta', 'Telefon', 'Etkinlik', 'Tarih', 'Bilet', 'Adet', 'Kişi', 'Diğer kişiler', 'Tutar', 'Ödeme yöntemi', 'Ödendi', 'Durum', 'Geldi', 'Not', 'Panel notu', 'Üye', 'Kayıt zamanı'], ';');
foreach ($rows as $r) {
  $rev = $ev ?? event_by_id($r['event']);
  fputcsv($out, array_map($cell, [
    $r['code'], $r['name'], $r['email'], $r['phone'], $rev['title'] ?? '', reg_session_label($r, $rev), $r['ticket_name'], $r['qty'], $r['seats'], $r['others'] ?? '',
    number_format((float) $r['total'], 2, ',', ''), PAY_METHODS[$r['method']] ?? '', $r['paid'] ? 'Evet' : 'Hayır', REG_STATUS[$r['status']] ?? $r['status'], $r['checked_in'] ? 'Evet' : 'Hayır',
    $r['note'] ?? '', $r['admin_note'] ?? '', $r['user'] ? 'Evet' : 'Hayır', $r['created'],
  ]), ';');
}
fclose($out);
