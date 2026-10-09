<?php
// Bilgilendirme e-postaları (düz metin). Sunucunun mail() işlevi kullanılır; panelden kapatılabilir.
require_once __DIR__ . '/events.php';

function reg_summary(array $r, array $ev): string {
  $s = is_package($ev) ? (event_sessions($ev, false)[0] ?? null) : session_by_id($ev, $r['session']);
  $lines = [
    'Etkinlik: ' . $ev['title'],
    'Tarih: ' . ($s ? (is_package($ev) ? event_when($ev) : session_when($s)) : ''),
    'Mekan: ' . session_place($s),
    'Bilet: ' . $r['ticket_name'] . ' × ' . $r['qty'],
    'Tutar: ' . ($r['total'] > 0 ? money($r['total']) : 'Ücretsiz'),
    'Katılım kodu: ' . $r['code'],
    'Durum: ' . (REG_STATUS[$r['status']] ?? $r['status']),
  ];
  return implode("\n", $lines);
}

function payment_text(array $r, array $ev): string {
  if ($r['paid'] || $r['total'] <= 0 || $r['status'] === 'yedek') return '';
  if ($r['method'] === 'havale' && trim((string) setting('bank_iban', '')) !== '') {
    return "\n\nÖdeme bilgileri\nBanka: " . setting('bank_name', '') . "\nAlıcı: " . setting('bank_holder', '') . "\nIBAN: " . setting('bank_iban', '') . "\nAçıklama: " . $r['code'] . "\n" . setting('payment_note', '');
  }
  if ($r['method'] === 'link' && trim($ev['pay_link'] ?? '') !== '') return "\n\nÖdeme sayfası: " . $ev['pay_link'];
  if ($r['method'] === 'kart') return "\n\nKartla ödemenizi tamamlamadıysanız buradan ödeyebilirsiniz: " . site_url('/odeme.php?kod=' . rawurlencode($r['code']) . '&k=' . reg_key($r));
  if ($r['method'] === 'yerinde') return "\n\nÜcreti etkinlik günü ödeyebilirsiniz.";
  return '';
}

function mail_registration(array $r, array $ev, bool $notifyAdmin = true): void {
  $link = site_url(ticket_url($r, true));
  $head = match ($r['status']) {
    'yedek' => "Kontenjan dolu olduğu için yedek listeye alındınız. Yer açılırsa size haber vereceğiz.",
    'beklemede' => "Kaydınızı aldık. " . ($r['total'] > 0 && $r['method'] !== 'yerinde' ? 'Ödemeniz onaylandığında kaydınız kesinleşecek.' : 'Onaylandığında size bilgi vereceğiz.'),
    default => "Kaydınız onaylandı, sizi aramızda görmek için sabırsızlanıyoruz.",
  };
  send_mail($r['email'], 'Kaydınız alındı: ' . $ev['title'], "Merhaba " . $r['name'] . ",\n\n" . $head . "\n\n" . reg_summary($r, $ev) . payment_text($r, $ev) . "\n\nKaydınızı görüntülemek için: " . $link);
  $to = $notifyAdmin ? admin_email() : '';
  if ($to !== '') send_mail($to, 'Yeni katılım: ' . $ev['title'] . ' (' . $r['code'] . ')', $r['name'] . ' · ' . $r['email'] . ' · ' . $r['phone'] . "\n\n" . reg_summary($r, $ev) . ($r['note'] !== '' ? "\nNot: " . $r['note'] : '') . "\n\nPanel: " . site_url('/yonetim/?s=katilimlar&etkinlik=' . rawurlencode($ev['id'])));
}

function mail_reg_update(array $r, array $ev, string $what): void {
  $msg = match ($what) {
    'onayli' => 'Kaydınız onaylandı. Etkinlikte görüşmek üzere!',
    'iptal' => 'Kaydınız iptal edildi. Bir yanlışlık olduğunu düşünüyorsanız bize yazın.',
    'yedekten' => 'Müjde: yer açıldı ve yedek listeden kayda alındınız.',
    'odeme' => 'Ödemeniz onaylandı, kaydınız kesinleşti. Teşekkür ederiz!',
    'tarih' => 'Kaydınızın tarihi güncellendi. Yeni bilgiler aşağıda.',
    default => 'Kaydınızda bir güncelleme var.',
  };
  send_mail($r['email'], $ev['title'] . ': kaydınız güncellendi', "Merhaba " . $r['name'] . ",\n\n" . $msg . "\n\n" . reg_summary($r, $ev) . payment_text($r, $ev) . "\n\nKaydınız: " . site_url(ticket_url($r, true)));
}
