<?php
// Mağaza: fiziksel ürünler (ad, stok adedi, açıklama, fiyat, görseller) ve siparişler.
// Etkinliklerden ayrıdır; ödeme aynı altyapıyla (PayTR kartla ödeme, havale) alınır.
// Stok, ödeme alınınca (ya da panelde sipariş hazırlanmaya alınınca) düşer. Ödemesi bekleyen siparişler
// ürünü kısa süre ayırır: kartla ödemede 45 dakika, havalede Ayarlar'daki süre kadar.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

const SHOP_FILE = DATA . '/magaza.json';
const ORDERS_FILE = DATA . '/siparisler.json';
const SHOP_DEFAULT = ['products' => [], 'settings' => [], 'codes' => []];

const PRODUCT_STATUS = ['yayinda' => 'Satışta', 'taslak' => 'Taslak (sitede görünmez)'];
const ORDER_STATUS = [
  'beklemede' => 'Ödeme bekleniyor',
  'hazirlaniyor' => 'Hazırlanıyor',
  'kargoda' => 'Kargoya verildi',
  'teslim' => 'Teslim edildi',
  'iptal' => 'İptal edildi',
];
const DELIVERY = ['kargo' => 'Kargo ile adrese teslim', 'elden' => 'Atölyeden teslim alma'];
const ORDER_PAY_METHODS = ['kart' => 'Kredi / banka kartı', 'havale' => 'Havale / EFT'];

// ---------- Ürünler ----------
function shop(): array {
  static $s = null;
  if ($s === null) $s = json_read(SHOP_FILE, SHOP_DEFAULT) + SHOP_DEFAULT;
  return $s;
}

function shop_setting(string $k, $default = null) {
  $s = shop()['settings'] ?? [];
  return array_key_exists($k, $s) ? $s[$k] : $default;
}

// Mağaza sayfasının giriş metni (panelden değiştirilebilir; boşsa bu metin görünür)
const SHOP_TITLE = 'İSE ATÖLYE Lokal Mağaza';
const SHOP_TAGLINE = 'Atölyelerimizden hayatınıza uzanan özgün tasarımlar.';
const SHOP_ABOUT = "İse Atölye Lokal Mağaza, eğitmenlerimizin, sanatçılarımızın ve üreticilerimizin kendi elleriyle hazırladığı özgün ürünleri keşfedebileceğiniz özel bir alan. Her biri kendi hikâyesini, emeğini ve yaratıcılığını taşıyan bu ürünleri yalnızca atölyelerimizde deneyimlemekle kalmayıp günlük yaşamınızın bir parçası hâline de getirebilirsiniz.\n\nEl emeği tasarımlardan sanatsal objelere, özgün aksesuarlardan özel üretim parçalara uzanan seçkimizle, yerel üretimi destekliyor ve üreticilerimizle aranızda yeni bir bağ kuruyoruz.\n\nKeşfedin, beğenin, üreticilerimizi destekleyin. Çünkü her ürün, arkasında bir emek, bir fikir.";

function shop_text(string $k): string {
  $v = trim((string) shop_setting($k, ''));
  if ($v !== '') return $v;
  return ['title' => SHOP_TITLE, 'tagline' => trim((string) shop_setting('intro', '')) ?: SHOP_TAGLINE, 'about' => SHOP_ABOUT][$k] ?? '';
}

// Giriş metninin yanındaki kayan galeri: panelde seçilen görseller; seçilmediyse ürün ve galeri fotoğrafları
function shop_slides(): array {
  $list = array_values(array_filter((array) shop_setting('slides', []), fn($p) => is_string($p) && is_file(ROOT . $p)));
  if ($list) return $list;
  foreach (products_public() as $p) foreach ($p['photos'] ?? [] as $g) if (is_file(ROOT . $g)) $list[] = $g;
  if (count($list) < 3) {
    require_once __DIR__ . '/media.php';
    require_once __DIR__ . '/events.php';
    foreach (gallery_albums() as $a) foreach ($a['images'] as $g) $list[] = $g;
  }
  return array_slice(array_values(array_unique($list)), 0, 8);
}

function products_all(): array { return shop()['products']; }

function product_by_id(?string $id): ?array {
  foreach (products_all() as $p) if ($p['id'] === $id) return $p;
  return null;
}

function product_by_slug(string $slug): ?array {
  foreach (products_all() as $p) if (($p['slug'] ?? '') === $slug) return $p;
  return null;
}

function product_url(array $p): string { return '/magaza/' . rawurlencode($p['slug']) . '/'; }
function product_visible(array $p): bool { return ($p['status'] ?? 'taslak') === 'yayinda'; }
function product_cover(array $p): string { return (string) (($p['photos'] ?? [])[0] ?? ''); }

// Sitedeki sıra: elle verilen sıra, sonra en yeni ürün önce
function products_public(): array {
  $list = array_values(array_filter(products_all(), 'product_visible'));
  usort($list, fn($a, $b) => [(int) ($a['sort'] ?? 0), $b['created'] ?? ''] <=> [(int) ($b['sort'] ?? 0), $a['created'] ?? '']);
  return $list;
}

// ---------- Siparişler ----------
function orders_all(): array {
  static $o = null;
  if ($o === null) $o = json_read(ORDERS_FILE, ['orders' => []])['orders'] ?? [];
  return $o;
}

function order_by_code(string $code): ?array {
  foreach (orders_all() as $o) if (strcasecmp($o['code'], $code) === 0) return $o;
  return null;
}

function order_code(array $orders): string {
  $abc = 'ABCDEFGHJKLMNPRSTUVYZ23456789';
  $codes = array_column($orders, 'code');
  do {
    $c = 'MGZ-';
    for ($i = 0; $i < 6; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)];
  } while (in_array($c, $codes, true));
  return $c;
}

function order_key(array $o): string { return substr(hash_hmac('sha256', 'siparis|' . $o['code'], secret()), 0, 16); }

function order_url(array $o, bool $withKey = false): string {
  return '/siparis/' . rawurlencode($o['code']) . '/' . ($withKey ? '?k=' . order_key($o) : '');
}

function order_pay_url(array $o): string {
  return '/siparis-odeme.php?kod=' . rawurlencode($o['code']) . '&k=' . order_key($o);
}

// Stoğu henüz düşülmemiş, iptal edilmemiş sipariş ürünü ayırır. Ödemesi bekleyenler yalnızca süre dolana kadar.
function order_holds(array $o): bool {
  if ($o['status'] === 'iptal' || !empty($o['stock_taken'])) return false;
  if ($o['status'] !== 'beklemede' || !empty($o['paid'])) return true;
  $t = strtotime((string) ($o['created'] ?? '')) ?: time();
  if (($o['method'] ?? '') === 'kart') {
    require_once __DIR__ . '/events.php';
    return $t > time() - CARD_HOLD_MINUTES * 60;
  }
  $h = (int) setting('hold_hours', 48);
  return $h <= 0 || $t > time() - $h * 3600;
}

function order_hold_expired(array $o): bool {
  return $o['status'] === 'beklemede' && empty($o['paid']) && empty($o['stock_taken']) && !order_holds($o);
}

// Satılabilir adet: stok eksi ödemesi beklenen siparişlerin ayırdığı adet
function product_available(array $p, ?array $orders = null): int {
  $held = 0;
  foreach ($orders ?? orders_all() as $o) if (($o['product'] ?? '') === $p['id'] && order_holds($o)) $held += (int) $o['qty'];
  return max(0, (int) ($p['stock'] ?? 0) - $held);
}

function product_max_qty(array $p, ?array $orders = null): int {
  $lim = (int) ($p['max_per_order'] ?? 0);
  $a = product_available($p, $orders);
  return $lim > 0 ? min($lim, $a) : $a;
}

// Kargo ücreti: ürün tutarı "ücretsiz kargo" sınırını geçerse 0
function shipping_fee(string $delivery, float $subtotal): float {
  if ($delivery !== 'kargo') return 0.0;
  $fee = (float) shop_setting('shipping_fee', 0);
  $free = (float) shop_setting('free_over', 0);
  return $free > 0 && $subtotal >= $free ? 0.0 : $fee;
}

// Ürünler yalnızca kargoyla gönderilir ('elden' yalnızca eski siparişlerin gösterimi için DELIVERY'de duruyor).
// İndirim kodları (panel > Mağaza > İndirim kodları): ürün tutarına yüzde indirim, son gün dahil geçerli
function discount_norm(string $c): string {
  return strtoupper(preg_replace('/\s+/', '', $c));
}

function discount_codes(): array {
  return shop()['codes'] ?? [];
}

function discount_state(array $d): string {
  if (empty($d['active'])) return 'kapali';
  return ($d['until'] ?? '') !== '' && $d['until'] < date('Y-m-d') ? 'doldu' : 'aktif';
}

function discount_find(string $code, ?array $codes = null): ?array {
  $code = discount_norm($code);
  if ($code === '') return null;
  foreach ($codes ?? discount_codes() as $d) if (($d['code'] ?? '') === $code && discount_state($d) === 'aktif') return $d;
  return null;
}

function discount_amount(float $subtotal, float $pct): float {
  return round($subtotal * max(0, min(100, $pct)) / 100, 2);
}

function delivery_options(): array {
  return ['kargo'];
}

function order_pay_methods(): array {
  require_once __DIR__ . '/paytr.php';
  $m = [];
  if (paytr_on()) $m[] = 'kart';
  if (trim((string) setting('bank_iban', '')) !== '') $m[] = 'havale';
  return $m;
}

// Ürünün stoğuna $n ekler (eksi değer düşer). Stok 0'ın altına inmez; ürün silinmişse bir şey yapmaz.
function product_stock_add(string $pid, int $n): ?int {
  return json_update(SHOP_FILE, function (array &$d) use ($pid, $n) {
    $d += SHOP_DEFAULT;
    foreach ($d['products'] as &$p) if ($p['id'] === $pid) { $p['stock'] = max(0, (int) ($p['stock'] ?? 0) + $n); return $p['stock']; }
    return null;
  }, SHOP_DEFAULT);
}

// Siparişi günceller ($fn siparişi değiştirir) ve stok durumunu buna göre ayarlar:
// ödenen ya da hazırlanmaya alınan siparişte stok bir kez düşer, iptal edilende geri eklenir. [önce, sonra] döner.
function order_update(string $id, callable $fn): ?array {
  $res = json_update(ORDERS_FILE, function (array &$d) use ($id, $fn) {
    $d['orders'] ??= [];
    foreach ($d['orders'] as &$o) {
      if ($o['id'] !== $id) continue;
      $before = $o;
      if ($fn($o) === false) return null;
      $take = $o['status'] !== 'iptal' && (!empty($o['paid']) || $o['status'] !== 'beklemede');
      $stock = 0;
      if ($take && empty($o['stock_taken'])) { $o['stock_taken'] = true; $stock = -(int) $o['qty']; }
      elseif ($o['status'] === 'iptal' && !empty($o['stock_taken'])) { $o['stock_taken'] = false; $stock = (int) $o['qty']; }
      elseif (!empty($o['stock_taken']) && (int) $o['qty'] !== (int) $before['qty']) $stock = (int) $before['qty'] - (int) $o['qty'];
      $o['updated'] = date('Y-m-d H:i');
      return [$before, $o, $stock];
    }
    return null;
  }, ['orders' => []]);
  if (!$res) return null;
  [$before, $o, $stock] = $res;
  if ($stock !== 0) product_stock_add((string) $o['product'], $stock);
  return [$before, $o];
}

// ---------- E-postalar ----------
function order_summary(array $o): string {
  $lines = [
    'Ürün: ' . $o['product_name'] . ' × ' . (int) $o['qty'],
    'Ürün tutarı: ' . money($o['subtotal']),
  ];
  if ((float) ($o['discount'] ?? 0) > 0) $lines[] = 'İndirim (' . $o['discount_code'] . ', %' . (float) $o['discount_pct'] . '): -' . money($o['discount']);
  if (($o['delivery'] ?? '') === 'kargo') $lines[] = 'Kargo: ' . ((float) $o['shipping'] > 0 ? money($o['shipping']) : 'Ücretsiz');
  $lines[] = 'Toplam: ' . money($o['total']);
  $lines[] = 'Teslimat: ' . (DELIVERY[$o['delivery']] ?? '') . (($o['delivery'] ?? '') === 'kargo' ? "\nAdres: " . $o['address'] : '');
  $lines[] = 'Sipariş kodu: ' . $o['code'];
  $lines[] = 'Durum: ' . (ORDER_STATUS[$o['status']] ?? $o['status']);
  if (trim((string) ($o['cargo'] ?? '')) !== '') $lines[] = 'Kargo takip: ' . $o['cargo'];
  return implode("\n", $lines);
}

function order_payment_text(array $o): string {
  if (!empty($o['paid']) || $o['status'] === 'iptal') return '';
  if ($o['method'] === 'havale' && trim((string) setting('bank_iban', '')) !== '') {
    return "\n\nÖdeme bilgileri\nBanka: " . setting('bank_name', '') . "\nAlıcı: " . setting('bank_holder', '') . "\nIBAN: " . setting('bank_iban', '') . "\nTutar: " . money($o['total']) . "\nAçıklama: " . $o['code'] . "\nÖdemeniz ulaşınca siparişinizi hazırlamaya başlarız.";
  }
  if ($o['method'] === 'kart') return "\n\nKartla ödemenizi tamamlamadıysanız buradan ödeyebilirsiniz: " . site_url(order_pay_url($o));
  return '';
}

function mail_order(array $o): void {
  $head = $o['status'] === 'beklemede' && empty($o['paid'])
    ? 'Siparişinizi aldık. Ödemeniz onaylanınca siparişinizi hazırlamaya başlayacağız.'
    : 'Siparişinizi aldık, hazırlamaya başlıyoruz.';
  send_mail($o['email'], 'Siparişiniz alındı: ' . $o['product_name'], "Merhaba " . $o['name'] . ",\n\n" . $head . "\n\n" . order_summary($o) . order_payment_text($o) . "\n\nSiparişinizi görüntülemek için: " . site_url(order_url($o, true)));
  if (($to = admin_email()) !== '') send_mail($to, 'Yeni sipariş: ' . $o['product_name'] . ' (' . $o['code'] . ')', $o['name'] . ' · ' . $o['email'] . ' · ' . $o['phone'] . "\n\n" . order_summary($o) . "\nÖdeme: " . (ORDER_PAY_METHODS[$o['method']] ?? '') . ($o['note'] !== '' ? "\nNot: " . $o['note'] : '') . "\n\nPanel: " . site_url('/yonetim/?s=siparis&id=' . rawurlencode($o['id'])));
}

function mail_order_update(array $o, string $what): void {
  $msg = match ($what) {
    'odeme' => 'Ödemeniz alındı, siparişinizi hazırlamaya başlıyoruz. Teşekkür ederiz!',
    'kargoda' => 'Siparişiniz kargoya verildi.' . (trim((string) ($o['cargo'] ?? '')) !== '' ? ' Kargo takip bilgisi aşağıda.' : ''),
    'teslim' => 'Siparişiniz teslim edildi. Bizi tercih ettiğiniz için teşekkür ederiz!',
    'hazirlaniyor' => (($o['delivery'] ?? '') === 'elden' ? 'Siparişiniz hazırlanıyor. Teslim almaya hazır olduğunda size haber vereceğiz.' : 'Siparişiniz hazırlanıyor.'),
    'iptal' => 'Siparişiniz iptal edildi. Ödeme yaptıysanız iadeniz ödeme yönteminize yapılacak. Bir yanlışlık olduğunu düşünüyorsanız bize yazın.',
    default => 'Siparişinizde bir güncelleme var.',
  };
  send_mail($o['email'], 'Siparişiniz: ' . $o['product_name'] . ' (' . $o['code'] . ')', "Merhaba " . $o['name'] . ",\n\n" . $msg . "\n\n" . order_summary($o) . order_payment_text($o) . "\n\nSiparişiniz: " . site_url(order_url($o, true)));
}

// ---------- Kartla ödeme (PayTR) ----------
function paytr_start_order(array $o): array {
  require_once __DIR__ . '/paytr.php';
  $cfg = paytr_config();
  if (!$cfg) return ['err', 'Kartla ödeme şu an kullanılamıyor.'];
  $inv = $o['invoice'] ?? [];
  $addr = trim((string) ($o['address'] ?? '')) ?: trim((string) ($inv['address'] ?? '')) ?: trim((string) (content()['contact']['address'] ?? '')) ?: 'Türkiye';
  $basket = (float) ($o['discount'] ?? 0) > 0
    ? [[mb_substr($o['product_name'] . ' × ' . (int) $o['qty'] . ' (%' . (float) $o['discount_pct'] . ' indirimli)', 0, 120), number_format((float) $o['subtotal'] - (float) $o['discount'], 2, '.', ''), 1]]
    : [[mb_substr($o['product_name'], 0, 120), number_format((float) $o['unit_price'], 2, '.', ''), (int) $o['qty']]];
  if ((float) $o['shipping'] > 0) $basket[] = ['Kargo', number_format((float) $o['shipping'], 2, '.', ''), 1];
  $back = site_url(order_url($o, true));
  [$ok, $res] = paytr_token($cfg, [
    'oid' => paytr_oid($o),
    'email' => (string) $o['email'],
    'amount' => paytr_kurus((float) $o['total']),
    'basket' => $basket,
    'name' => trim((string) ($inv['title'] ?? '')) !== '' ? (string) $inv['title'] : (string) $o['name'],
    'address' => $addr,
    'phone' => paytr_phone((string) $o['phone']),
    'ok_url' => $back . '&odeme=ok',
    'fail_url' => $back . '&odeme=hata',
  ]);
  if ($ok !== 'ok') error_log('PayTR başlatma hatası (' . $o['code'] . '): ' . $res);
  return [$ok, $ok === 'ok' ? $res : 'Ödeme sayfası açılamadı: ' . $res];
}

// PayTR bildirimi bir mağaza siparişine aitse işler (imza önceden doğrulanmıştır). PayTR'ye "OK" yazılmasını çağıran yapar.
function shop_paytr_notify(array $cfg, array $p, string $oid, string $code): void {
  $o = order_by_code($code);
  if (!$o) { error_log('PayTR bildirimi: sipariş bulunamadı (' . $oid . ')'); return; }
  if (($p['status'] ?? '') !== 'success') { error_log('PayTR ödeme başarısız (' . $o['code'] . '): ' . ($p['failed_reason_code'] ?? '') . ' ' . ($p['failed_reason_msg'] ?? '')); return; }
  $test = !empty($p['test_mode']);
  if ($test && empty($cfg['test'])) { error_log('PayTR: canlı modda test bildirimi reddedildi (' . $oid . ')'); return; }
  $alert = function (string $why) use ($o, $p, $oid) {
    error_log('PayTR bildirimi (' . $o['code'] . '): ' . $why);
    if (($to = admin_email()) !== '') send_mail($to, 'İADE GEREKEBİLİR: ' . $o['code'], $why . "\nPayTR sipariş no: " . $oid . "\nTahsil edilen: " . money(((int) ($p['total_amount'] ?? 0)) / 100) . "\nSipariş tutarı: " . money($o['total']) . "\n\nPanel: " . site_url('/yonetim/?s=siparis&id=' . rawurlencode($o['id'])));
  };
  if ((int) ($p['total_amount'] ?? 0) < paytr_kurus((float) $o['total'])) { $alert('Ödenen tutar sipariş tutarından az; sipariş "ödendi" yapılmadı.'); return; }
  if (!empty($o['paid']) && !str_starts_with((string) ($o['pay_ref'] ?? ''), $oid)) { $alert('Bu sipariş zaten ödenmişti, ikinci bir kart ödemesi alındı.'); return; }
  if ($test) {
    order_update($o['id'], function (array &$x) use ($oid) { if (!empty($x['paid'])) return false; $x['method'] = 'kart'; $x['pay_ref'] = $oid . ' (TEST, gerçek ödeme değil)'; $x['test_paid'] = true; });
    if (($to = admin_email()) !== '') send_mail($to, 'PayTR test ödemesi başarılı: ' . $o['code'], "Test modunda ödeme akışı çalıştı. Sipariş \"ödendi\" yapılmadı.\nPayTR sipariş no: " . $oid);
    return;
  }
  $stockBefore = (int) (product_by_id($o['product'])['stock'] ?? 0);
  $res = order_update($o['id'], function (array &$x) use ($oid) {
    if (!empty($x['paid'])) return false;
    $x['paid'] = true;
    $x['method'] = 'kart';
    $x['pay_ref'] = $oid;
    if ($x['status'] === 'beklemede') $x['status'] = 'hazirlaniyor';
  });
  if (!$res) return;
  [$before, $upd] = $res;
  try {
    mail_order_update($upd, 'odeme');
    $short = empty($before['stock_taken']) && $stockBefore < (int) $upd['qty'];
    if (($to = admin_email()) !== '') send_mail($to, 'Kartla ödeme alındı: ' . $upd['product_name'] . ' (' . $upd['code'] . ')', $upd['name'] . ' · ' . $upd['email'] . "\nTutar: " . money($upd['total']) . "\nPayTR sipariş no: " . $oid . ($upd['status'] === 'iptal' ? "\n\nDikkat: bu sipariş iptal edilmiş durumda, ücret iadesi gerekebilir." : '') . ($short ? "\n\nDikkat: ödeme süresi dolduktan sonra yapıldı ve stokta yeterli ürün yoktu. Stoğu kontrol edin." : '') . "\n\nPanel: " . site_url('/yonetim/?s=siparis&id=' . rawurlencode($upd['id'])));
  } catch (Throwable $ex) { error_log('PayTR bildirimi e-posta hatası: ' . $ex->getMessage()); }
}
