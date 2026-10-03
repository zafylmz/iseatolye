<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$a = $c['about'];
$team = instructors();
$title = 'Hakkımızda · ' . $c['brand']['name'];
$description = excerpt($a['body'] ?? '', 160);
include __DIR__ . '/inc/header.php';
$paras = preg_split('/\n\s*\n/', trim($a['body'] ?? '')) ?: [];
?>
  <section class="wrap page-head page-head--center">
    <p class="label">Hakkımızda</p>
    <h1 class="display"><?= e($a['title'] ?? 'İSE ATÖLYE') ?></h1>
    <p class="lead"><?= e($a['lead'] ?? '') ?></p>
  </section>
  <section class="wrap about">
    <?php if (!empty($a['image'])): ?><figure class="about__img" data-reveal><div class="arch arch--wide"><img src="<?= e($a['image']) ?>" alt="" width="1600" height="1000"></div></figure><?php endif; ?>
    <div class="about__text prose prose--lead" data-reveal>
      <?php foreach ($paras as $i => $p): $p = trim($p); if ($p === '') continue; ?>
        <?php if ($i === count($paras) - 1 && count($paras) > 1): ?><blockquote class="quote quote--big"><p><?= e($p) ?></p><?php if (trim($a['founder'] ?? '') !== ''): ?><footer><?= e($a['founder']) ?><?= trim($a['founder_title'] ?? '') !== '' ? ', ' . e($a['founder_title']) : '' ?></footer><?php endif; ?></blockquote>
        <?php else: ?><p><?= nl2br(e($p)) ?></p><?php endif; ?>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if (!empty($c['stats'])): ?><div class="wrap"><ul class="stats"><?php foreach ($c['stats'] as $s): if (trim($s['value'] ?? '') === '') continue; ?><li><strong><?= e($s['value']) ?></strong><span><?= e($s['label']) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>

  <?php if ($team): ?>
  <section class="section wrap">
    <div class="section-head" data-reveal><div><p class="label">Ekip</p><h2 class="h2">Eğitmenlerimiz</h2></div></div>
    <div class="team">
      <?php foreach ($team as $i): ?>
        <article class="tcard" data-reveal><?= avatar(['id' => $i['id'], 'name' => $i['name'], 'avatar' => $i['photo'] ?? ''], 'xl') ?><h3 class="h3"><?= e($i['name']) ?></h3><?php if (trim($i['title'] ?? '') !== ''): ?><p class="label"><?= e($i['title']) ?></p><?php endif; ?><?php if (trim($i['bio'] ?? '') !== ''): ?><p class="muted"><?= e($i['bio']) ?></p><?php endif; ?><?php if (trim($i['instagram'] ?? '') !== ''): ?><a class="link-more" href="<?= e($i['instagram']) ?>" target="_blank" rel="noopener"><?= icon('instagram') ?>Instagram</a><?php endif; ?></article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($a['gallery'])): ?>
  <section class="section section--sand">
    <div class="wrap">
      <div class="section-head" data-reveal><div><p class="label">Atölyeden kareler</p><h2 class="h2">Birlikte ürettiklerimiz</h2></div><?php foreach ($c['socials'] ?? [] as $s): if (stripos($s['label'], 'instagram') === false || !$s['url']) continue; ?><a class="link-more" href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><?= icon('instagram') ?>Instagram'da takip edin</a><?php endforeach; ?></div>
      <?php require_once __DIR__ . '/inc/media.php'; ?><div class="mosaic" data-zoom-group><?php foreach ($a['gallery'] as $g): ?><button type="button" class="mosaic__item" data-zoom-src="<?= e($g) ?>" data-reveal><img src="<?= e(thumb_url($g)) ?>" alt="" loading="lazy"></button><?php endforeach; ?></div>
    </div>
  </section>
  <?php endif; ?>

  <?php include __DIR__ . '/inc/contact-band.php'; ?>
<?php include __DIR__ . '/inc/footer.php'; ?>
