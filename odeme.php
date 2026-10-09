<?php
// Kartla ödeme: PayTR'den ödeme anahtarı alır ve PayTR ödeme formunu bu sayfada çerçeve içinde gösterir.
// Kart bilgileri yalnızca PayTR'nin sayfasında girilir; site kart bilgisini görmez ve saklamaz.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/paytr.php';
$c = content();
$r = reg_by_code((string) ($_GET['kod'] ?? ''));
$me = current_user();
$keyOk = $r && hash_equals(reg_key($r), (string) ($_GET['k'] ?? ''));
if (!$r || (!$keyOk && (!$me || ($r['user'] ?? '') !== $me['id']))) { http_response_code(404); include __DIR__ . '/404.php'; exit; }
$back = ticket_url($r, true);
$ev = event_by_id($r['event']);
if (!$ev || !empty($r['paid']) || (float) $r['total'] <= 0 || !in_array($r['status'], ['beklemede', 'onayli'], true)) { header('Location: ' . $back, true, 303); exit; }
if (!paytr_on() || !rate_hit('odeme:' . client_hash(), 20, 3)) { header('Location: ' . $back . '&odeme=kapali', true, 303); exit; }
[$ok, $token] = paytr_start($r, $ev);
if ($ok !== 'ok') { header('Location: ' . $back . '&odeme=hata', true, 303); exit; }
header('Cache-Control: no-store');
$title = 'Kartla ödeme · ' . $r['code'] . ' · ' . $c['brand']['name'];
$noindex = true;
$csp_pay = true;
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap pay-page">
    <a class="back" href="<?= e($back) ?>"><?= icon('sol') ?>Kaydıma dön</a>
    <h1 class="h2">Kartla ödeme</h1>
    <p class="muted"><?= e($ev['title']) ?> · <?= e($r['code']) ?> · <strong><?= money($r['total']) ?></strong></p>
    <p class="muted small">Kart bilgileriniz PayTR'nin güvenli ödeme sayfasında girilir, 3D Secure ile doğrulanır. Ödeme alınınca kaydınız kendiliğinden kesinleşir.</p>
    <iframe src="<?= e(PAYTR_FRAME_URL . rawurlencode($token)) ?>" id="paytriframe" class="pay-frame" title="PayTR güvenli ödeme" scrolling="no"></iframe>
  </section>
  <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
  <script>iFrameResize({}, '#paytriframe');</script>
<?php include __DIR__ . '/inc/footer.php'; ?>
