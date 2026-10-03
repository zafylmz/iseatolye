<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/calendar.php';
require_once __DIR__ . '/inc/icons.php';
[$y, $m] = cal_ym((string) ($_GET['ay'] ?? ''));
if (!empty($_GET['parca'])) {
  header('Content-Type: text/html; charset=utf-8');
  header('X-Robots-Tag: noindex');
  echo calendar_month($y, $m);
  exit;
}
$c = content();
$title = 'Etkinlik takvimi · ' . TR_MONTHS[$m] . ' ' . $y . ' · ' . $c['brand']['name'];
$description = 'İse Atölye etkinlik takvimi: ' . TR_MONTHS[$m] . ' ' . $y . ' atölyeleri, tarih, saat ve mekân bilgileriyle.';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head page-head--row">
    <div><p class="label">Takvim</p><h1 class="display display--md">Etkinlik takvimi</h1></div>
    <a class="link-more" href="/etkinlikler/"><?= icon('liste') ?>Liste görünümü</a>
  </section>
  <section class="wrap page-body" data-cal-page>
    <?= calendar_month($y, $m) ?>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
