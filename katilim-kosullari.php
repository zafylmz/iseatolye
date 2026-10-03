<?php
require_once __DIR__ . '/inc/bootstrap.php';
$c = content();
$title = 'Katılım koşulları · ' . $c['brand']['name'];
$description = 'Etkinlik kaydı, kontenjan, iptal ve iade koşulları ile topluluk kuralları.';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head">
    <p class="label">Koşullar</p>
    <h1 class="display display--md">Katılım koşulları</h1>
  </section>
  <section class="wrap page-body"><div class="prose"><?= md((string) setting('terms', '')) ?></div></section>
<?php include __DIR__ . '/inc/footer.php'; ?>
