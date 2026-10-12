<?php
// Mağaza siparişi için kartla ödeme: PayTR'den ödeme anahtarı alır ve ödeme formunu çerçeve içinde gösterir.
// Kart bilgileri yalnızca PayTR'nin sayfasında girilir; site kart bilgisini görmez ve saklamaz.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/shop.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/paytr.php';
$c = content();
$o = order_by_code((string) ($_GET['kod'] ?? ''));
if (!$o || !hash_equals(order_key($o), (string) ($_GET['k'] ?? ''))) { http_response_code(404); include __DIR__ . '/404.php'; exit; }
$back = order_url($o, true);
if (!empty($o['paid']) || (float) $o['total'] <= 0 || $o['status'] === 'iptal') { header('Location: ' . $back, true, 303); exit; }
if (!paytr_on() || !rate_hit('odeme:' . client_hash(), 20, 3)) { header('Location: ' . $back . '&odeme=kapali', true, 303); exit; }
// Ayırma süresi dolduysa ve bu arada ürün tükendiyse ödeme alınmaz (stoktan fazla satış olmasın)
if (order_hold_expired($o)) {
  $p = product_by_id($o['product']);
  if (!$p || product_available($p) < (int) $o['qty']) { header('Location: ' . $back . '&odeme=tukendi', true, 303); exit; }
}
[$ok, $token] = paytr_start_order($o);
if ($ok !== 'ok') { header('Location: ' . $back . '&odeme=hata', true, 303); exit; }
header('Cache-Control: no-store');
$title = 'Kartla ödeme · ' . $o['code'] . ' · ' . $c['brand']['name'];
$noindex = true;
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap pay-page">
    <a class="back" href="<?= e($back) ?>"><?= icon('sol') ?>Siparişime dön</a>
    <h1 class="h2">Kartla ödeme</h1>
    <p class="muted"><?= e($o['product_name']) ?> × <?= (int) $o['qty'] ?> · <?= e($o['code']) ?> · <strong><?= money($o['total']) ?></strong></p>
    <?php if (!empty(paytr_config()['test'])): ?><p class="notice notice--warn" role="alert">TEST MODU: bu sayfada gerçek ödeme alınmaz, sipariş "ödendi" olmaz.</p><?php endif; ?>
    <p class="muted small">Kart bilgileriniz PayTR'nin güvenli ödeme sayfasında girilir, 3D Secure ile doğrulanır. Ödeme alınınca siparişiniz kendiliğinden onaylanır.</p>
    <iframe src="<?= e(PAYTR_FRAME_URL . rawurlencode($token)) ?>" id="paytriframe" class="pay-frame" title="PayTR güvenli ödeme" scrolling="no"></iframe>
  </section>
  <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
  <script>iFrameResize({}, '#paytriframe');</script>
<?php include __DIR__ . '/inc/footer.php'; ?>
