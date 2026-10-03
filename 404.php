<?php
require_once __DIR__ . '/inc/bootstrap.php';
$c = content();
$title = 'Sayfa bulunamadı · ' . ($c['brand']['name'] ?? '');
$noindex = true;
if (!headers_sent()) http_response_code(404);
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head page-head--center" style="min-height:52vh">
    <p class="label">Hata 404</p>
    <h1 class="display display--md">Bu sayfa bulunamadı.</h1>
    <p class="lead">Sayfa taşınmış ya da etkinlik yayından kaldırılmış olabilir.</p>
    <div class="actions actions--center"><a class="btn" href="/etkinlikler/">Etkinliklere göz atın</a><a class="btn btn--ghost" href="/">Ana sayfa</a></div>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
