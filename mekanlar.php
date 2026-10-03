<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$slug = (string) ($_GET['slug'] ?? '');
if ($slug !== '') {
  $v = null; foreach (venues() as $x) if ($x['slug'] === $slug) $v = $x;
  if (!$v) { http_response_code(404); include __DIR__ . '/404.php'; exit; }
  $up = upcoming_events(0, fn($ev) => in_array($v['id'], array_column(event_sessions($ev, false), 'venue'), true));
  $pastHere = array_slice(array_values(array_filter(past_events(), fn($ev) => in_array($v['id'], array_column($ev['sessions'] ?? [], 'venue'), true))), 0, 6);
  $map = map_url($v);
  $title = $v['name'] . ' · Mekanlar · ' . $c['brand']['name'];
  $description = $v['name'] . ' mekânında düzenlenen İse Atölye etkinlikleri.';
  include __DIR__ . '/inc/header.php'; ?>
  <section class="wrap venue-head">
    <a class="back" href="/mekanlar/"><?= icon('sol') ?>Tüm mekanlar</a>
    <div class="venue-head__grid">
      <div>
        <p class="label"><?= e(['acik' => 'Açık hava', 'online' => 'Çevrimiçi'][$v['type'] ?? ''] ?? 'Mekan') ?></p>
        <h1 class="display display--md"><?= e($v['name']) ?></h1>
        <ul class="book__meta">
          <?php $addr = trim(implode(', ', array_filter([$v['address'] ?? '', $v['district'] ?? '', $v['city'] ?? '']))); if ($addr !== ''): ?><li><?= icon('konum') ?><span><?= e($addr) ?></span></li><?php endif; ?>
          <?php if (trim($v['phone'] ?? '') !== ''): ?><li><?= icon('telefon') ?><a href="<?= e(phone_href($v['phone'])) ?>"><?= e($v['phone']) ?></a></li><?php endif; ?>
          <?php if ((int) ($v['capacity'] ?? 0) > 0): ?><li><?= icon('kisiler') ?><span>Ortalama <?= (int) $v['capacity'] ?> kişilik</span></li><?php endif; ?>
        </ul>
        <?php if (trim($v['note'] ?? '') !== ''): ?><p class="muted"><?= nl2br(e($v['note'])) ?></p><?php endif; ?>
        <?php if ($map !== ''): ?><a class="btn btn--ghost" href="<?= e($map) ?>" target="_blank" rel="noopener"><?= icon('konum') ?>Haritada aç</a><?php endif; ?>
      </div>
      <?php if (!empty($v['photo'])): ?><div class="venue-head__img"><img src="<?= e($v['photo']) ?>" alt="<?= e($v['name']) ?>"></div><?php endif; ?>
    </div>
  </section>
  <section class="section wrap" style="padding-top:0">
    <h2 class="h3">Bu mekandaki yaklaşan etkinlikler</h2>
    <?php if ($up): ?><div class="egrid"><?php foreach ($up as $ev) include __DIR__ . '/inc/event-card.php'; ?></div><?php else: ?><p class="muted">Şu an bu mekanda planlanmış etkinlik yok.</p><?php endif; ?>
    <?php if ($pastHere): ?><h2 class="h3" style="margin-top:56px">Burada yaptıklarımız</h2><div class="egrid egrid--sm"><?php foreach ($pastHere as $ev) include __DIR__ . '/inc/event-card.php'; ?></div><?php endif; ?>
  </section>
  <?php include __DIR__ . '/inc/footer.php'; exit;
}
$title = 'Mekanlar · ' . $c['brand']['name'];
$description = 'İse Atölye etkinliklerinin yapıldığı mekanlar: Silivri ve çevresinde her hafta farklı bir yer.';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head">
    <p class="label">Mekanlar</p>
    <h1 class="display display--md">Her hafta farklı bir mekânda</h1>
    <p class="lead">Atölyelerimizi Silivri ve çevresindeki sevdiğimiz mekânlarda yapıyoruz. Mekâna dokunun, oradaki etkinlikleri görün.</p>
  </section>
  <section class="wrap page-body">
    <div class="vgrid">
      <?php foreach (venues() as $v): $n = count(upcoming_events(0, fn($ev) => in_array($v['id'], array_column(event_sessions($ev, false), 'venue'), true))); ?>
        <a class="vcard" href="<?= e(venue_url($v)) ?>" data-reveal>
          <span class="vcard__img"><?php if (!empty($v['photo'])): ?><img src="<?= e($v['photo']) ?>" alt="" loading="lazy"><?php endif; ?></span>
          <span class="vcard__body"><strong><?= e($v['name']) ?></strong><span class="muted"><?= e(trim(implode(', ', array_filter([$v['district'] ?? '', $v['city'] ?? ''])))) ?></span><span class="vcard__n"><?= $n ? $n . ' yaklaşan etkinlik' : 'Yakında yeni etkinlik' ?></span></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
