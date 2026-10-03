<?php
// Herkese açık galeri: etkinlik albümleri ve paneldeki albümler.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/media.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$meta = media_meta()['items'];
$cap = fn($p) => (string) ($meta[$p]['title'] ?? '');
$slug = (string) ($_GET['slug'] ?? '');

if ($slug !== '') {
  $a = gallery_album($slug);
  if (!$a) { http_response_code(404); include __DIR__ . '/404.php'; exit; }
  $title = $a['name'] . ' · Galeri · ' . $c['brand']['name'];
  $description = $a['name'] . ': ' . count($a['images']) . ' fotoğraf. ' . ($a['text'] !== '' ? $a['text'] : $c['brand']['name'] . ' atölyelerinden kareler.');
  $image = $a['cover'];
  include __DIR__ . '/inc/header.php'; ?>
  <section class="wrap page-head">
    <a class="back" href="/galeri/"><?= icon('sol') ?>Tüm albümler</a>
    <p class="label"><?= $a['event'] ? 'Etkinlik albümü' : 'Albüm' ?><?= $a['date'] !== '' ? ' · ' . e(tr_date($a['date'])) : '' ?></p>
    <h1 class="display display--md"><?= e($a['name']) ?></h1>
    <?php if ($a['text'] !== ''): ?><p class="lead"><?= e($a['text']) ?></p><?php endif; ?>
    <p class="muted"><?= count($a['images']) ?> fotoğraf<?php if ($a['event']): ?> · <a class="link" href="<?= e(event_url($a['event'])) ?>">Etkinlik sayfasına git</a><?php endif; ?></p>
  </section>
  <section class="wrap page-body">
    <div class="pgrid" data-zoom-group>
      <?php foreach ($a['images'] as $g): ?>
        <button type="button" class="pgrid__item" data-zoom-src="<?= e($g) ?>" data-zoom-cap="<?= e($cap($g)) ?>"><img src="<?= e(thumb_url($g)) ?>" alt="<?= e($cap($g)) ?>" loading="lazy"></button>
      <?php endforeach; ?>
    </div>
  </section>
  <?php include __DIR__ . '/inc/footer.php'; exit;
}

$albums = gallery_albums();
$title = 'Galeri · ' . $c['brand']['name'];
$description = $c['brand']['name'] . ' atölye ve etkinliklerinden fotoğraflar.';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head">
    <p class="label">Galeri</p>
    <h1 class="display display--md">Atölyelerden kareler</h1>
    <p class="lead">Birlikte ürettiğimiz, öğrendiğimiz ve keyif aldığımız anlar. Bir albüme dokunun, bütün fotoğrafları görün.</p>
  </section>
  <section class="wrap page-body">
    <?php if (!$albums): ?>
      <p class="muted">Galeri çok yakında burada. Bu arada <a class="link" href="/etkinlikler/">yaklaşan etkinliklere</a> göz atabilirsiniz.</p>
    <?php else: ?>
      <div class="agrid">
        <?php foreach ($albums as $a): $n = count($a['images']); ?>
          <a class="acard" href="/galeri/<?= e(rawurlencode($a['slug'])) ?>/" data-reveal>
            <span class="acard__img">
              <img src="<?= e(thumb_url($a['cover'])) ?>" alt="" loading="lazy">
              <?php foreach (array_slice(array_values(array_diff($a['images'], [$a['cover']])), 0, 2) as $k => $g): ?><img class="acard__mini acard__mini--<?= $k + 1 ?>" src="<?= e(thumb_url($g)) ?>" alt="" loading="lazy"><?php endforeach; ?>
            </span>
            <span class="acard__body"><strong><?= e($a['name']) ?></strong><span class="muted"><?= $n ?> fotoğraf<?= $a['date'] !== '' ? ' · ' . e(tr_date($a['date'])) : '' ?></span></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
