<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/calendar.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$h = $c['home'] ?? [];
$upcoming = upcoming_events();
$featured = array_values(array_filter($upcoming, fn($ev) => !empty($ev['featured'])));
$grid = array_slice($featured ?: $upcoming, 0, 6);
if (count($grid) < 3) $grid = array_slice($upcoming, 0, 6);
$soon = array_slice($upcoming, 0, 4);
[$cy, $cm] = cal_ym(null);
$monthCount = array_sum(array_map('count', month_items($cy, $cm)));

// Topluluk: son yorumlar ve "düşünüyorum" diyenler
$recent = [];
foreach (json_read(COMMENTS_FILE) as $eid => $list) {
  $ev = event_by_id($eid);
  if (!$ev || !event_visible($ev)) continue;
  foreach ($list as $x) if (($x['status'] ?? '') === 'yayinda' && empty($x['admin']) && ($cu = user_by_id($x['user'] ?? '')) && user_public($cu)) $recent[] = $x + ['ev' => $ev];
}
usort($recent, fn($a, $b) => strcmp($b['date'], $a['date']));
$recent = array_slice($recent, 0, 3);
$memberCount = count(array_filter(users_all(), 'user_public'));

$posts = [];
if (data_exists(DATA . '/blog.json')) { require_once __DIR__ . '/inc/blog.php'; $posts = array_slice(blog_published(), 0, 3); }

$ld = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $c['brand']['name'], 'url' => site_url('/'), 'email' => $c['contact']['email'] ?? '', 'telephone' => $c['contact']['phone'] ?? '', 'sameAs' => array_values(array_filter(array_column($c['socials'] ?? [], 'url')))];
$headExtra = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
$title = $c['brand']['name'] . ' · ' . $c['brand']['tagline'];
ob_start(); include __DIR__ . '/inc/cal-modal.php'; $bodyEnd = ob_get_clean();
include __DIR__ . '/inc/header.php';
?>
  <section class="hero">
    <div class="wrap hero__grid">
      <div class="hero__text">
        <p class="label" data-reveal><?= e($h['eyebrow'] ?? '') ?></p>
        <h1 class="display" data-reveal><?= e($h['title'] ?? '') ?></h1>
        <p class="lead" data-reveal><?= e($h['subtitle'] ?? '') ?></p>
        <div class="actions" data-reveal>
          <a class="btn" href="/etkinlikler/">Etkinlikleri keşfet</a>
          <button class="btn btn--ghost" type="button" data-cal-open><?= icon('takvim') ?>Takvimi aç</button>
        </div>
      </div>
      <div class="hero__media" data-reveal>
        <?php $heroImg = (string) (($upcoming[0]['cover'] ?? '') ?: ($h['hero_image'] ?? '')); // sıradaki etkinliğin görseli; etkinlik yoksa sayfanın kendi görseli ?>
        <div class="arch"><?php if ($heroImg !== ''): ?><img src="<?= e($heroImg) ?>" alt="<?= e(!empty($upcoming[0]['cover']) ? $upcoming[0]['title'] : '') ?>" width="1200" height="1500" fetchpriority="high"><?php endif; ?></div>
        <?php if ($n = $upcoming[0] ?? null): $ns = next_session($n); ?>
          <a class="hero__next" href="<?= e(event_url($n)) ?>">
            <span class="label">Sıradaki etkinlik</span>
            <strong><?= e($n['title']) ?></strong>
            <span><?= e(tr_date_short($ns['date']) . ($ns['start'] ? ' · ' . $ns['start'] : '')) ?> · <?= e(session_place($ns, false)) ?></span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if (!empty($c['stats'])): ?>
  <div class="wrap">
    <ul class="stats" data-reveal>
      <?php foreach ($c['stats'] as $s): if (trim($s['value'] ?? '') === '') continue; ?><li><strong><?= e($s['value']) ?></strong><span><?= e($s['label']) ?></span></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <section class="section wrap" aria-labelledby="yaklasan">
    <div class="section-head" data-reveal>
      <div><p class="label">Etkinlikler</p><h2 class="h2" id="yaklasan"><?= e($h['events_title'] ?? 'Yaklaşan etkinlikler') ?></h2></div>
      <a class="link-more" href="/etkinlikler/">Tüm etkinlikler<?= icon('sag') ?></a>
    </div>
    <?php if ($grid): ?>
      <div class="egrid"><?php foreach ($grid as $ev) include __DIR__ . '/inc/event-card.php'; ?></div>
    <?php else: ?>
      <p class="empty">Yeni etkinlikler çok yakında burada. Takipte kalmak için <a href="/uye-ol/">üye olun</a>.</p>
    <?php endif; ?>
  </section>

  <section class="section section--sand" aria-labelledby="bu-ay">
    <div class="wrap month">
      <div class="month__text" data-reveal>
        <p class="label">Takvim</p>
        <h2 class="h2" id="bu-ay"><?= e($h['calendar_title'] ?? 'Bu ay neler var?') ?></h2>
        <p class="muted"><?= e($h['calendar_text'] ?? '') ?></p>
        <?php if ($soon): ?>
        <ol class="soon">
          <?php foreach ($soon as $ev): $s = next_session($ev); $b = date_badge($ev); ?>
            <li><a href="<?= e(event_url($ev)) ?>"><span class="soon__date"><b><?= e($b['day']) ?></b><?= e($b['mon']) ?></span><span class="soon__t"><strong><?= e($ev['title']) ?></strong><span><?= e(($s['start'] ?? '') . ' · ' . session_place($s, false)) ?></span></span><?= icon('ileri') ?></a></li>
          <?php endforeach; ?>
        </ol>
        <?php endif; ?>
      </div>
      <button class="month__cal" type="button" data-cal-open aria-label="Büyük takvimi aç: <?= e(TR_MONTHS[$cm] . ' ' . $cy) ?>, <?= $monthCount ?> etkinlik" data-reveal>
        <?= calendar_month($cy, $cm, 'mini') ?>
      </button>
    </div>
  </section>

  <section class="section wrap" aria-labelledby="nasil">
    <div class="section-head section-head--center" data-reveal><div><p class="label">Katılım</p><h2 class="h2" id="nasil"><?= e($h['steps_title'] ?? 'Nasıl katılırım?') ?></h2></div></div>
    <ol class="steps">
      <?php foreach ($h['steps'] ?? [] as $i => $s): ?><li data-reveal><span class="steps__n"><?= sprintf('%02d', $i + 1) ?></span><h3><?= e($s['title']) ?></h3><p><?= e($s['text']) ?></p></li><?php endforeach; ?>
    </ol>
  </section>

  <section class="section section--line" aria-labelledby="biz">
    <div class="wrap story">
      <div class="story__media" data-reveal><?php if (!empty($c['about']['image'])): ?><img src="<?= e($c['about']['image']) ?>" alt="" width="1200" height="1400" loading="lazy"><?php endif; ?></div>
      <div class="story__text" data-reveal>
        <p class="label">Hakkımızda</p>
        <h2 class="h2" id="biz"><?= e($c['about']['lead'] ?? '') ?></h2>
        <?php if (trim($h['quote'] ?? '') !== ''): ?><blockquote class="quote"><p><?= e($h['quote']) ?></p><footer><?= e($c['about']['founder'] ?? '') ?></footer></blockquote><?php endif; ?>
        <a class="link-more" href="/hakkimizda/">Hikâyemizi okuyun<?= icon('sag') ?></a>
      </div>
    </div>
  </section>

  <section class="section wrap" aria-labelledby="kurumsal">
    <div class="corp-band" data-reveal>
      <div>
        <p class="label">Kurumsal</p>
        <h2 class="h2" id="kurumsal"><?= e($c['corporate']['lead'] ?? '') ?></h2>
        <?php if (!empty($c['corporate']['brands'])): ?><p class="corp-band__brands"><span class="muted">İş birliği yaptığımız markalardan bazıları</span><?php foreach ($c['corporate']['brands'] as $b): ?><b><?= e($b) ?></b><?php endforeach; ?></p><?php endif; ?>
      </div>
      <div class="corp-band__act"><a class="btn" href="/kurumsal/">Kurumsal etkinlikler</a><a class="btn btn--ghost" href="/iletisim/?konu=kurumsal">Teklif isteyin</a></div>
    </div>
  </section>

  <section class="section section--sand" aria-labelledby="topluluk">
    <div class="wrap">
      <div class="section-head" data-reveal>
        <div><p class="label">Topluluk</p><h2 class="h2" id="topluluk"><?= e($h['community_title'] ?? 'Topluluktan') ?></h2><p class="muted section-head__sub"><?= e($h['community_text'] ?? '') ?></p></div>
        <?php if (!current_user()): ?><a class="btn" href="/uye-ol/">Ücretsiz üye ol</a><?php endif; ?>
      </div>
      <?php if ($recent): ?>
        <div class="voices">
          <?php foreach ($recent as $r): $u = user_by_id($r['user']); ?>
            <figure class="voice" data-reveal>
              <blockquote><p><?= e(mb_strlen($r['text']) > 220 ? mb_substr($r['text'], 0, 219) . '…' : $r['text']) ?></p></blockquote>
              <figcaption><a href="<?= e(user_url($u)) ?>"><?= avatar($u, 'sm') ?></a><span><a href="<?= e(user_url($u)) ?>"><strong><?= e($u['name']) ?></strong></a><a class="muted" href="<?= e(event_url($r['ev'])) ?>#yorumlar"><?= e($r['ev']['title']) ?></a></span></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="empty empty--left">İlk yorumu siz yazın: katıldığınız etkinliğin sayfasında deneyiminizi paylaşın.</p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($posts): ?>
  <section class="section wrap" aria-labelledby="blog">
    <div class="section-head" data-reveal><div><p class="label">Blog</p><h2 class="h2" id="blog">Atölyeden notlar</h2></div><a class="link-more" href="/blog/">Tüm yazılar<?= icon('sag') ?></a></div>
    <div class="posts posts--3"><?php foreach ($posts as $p) include __DIR__ . '/inc/post-card.php'; ?></div>
  </section>
  <?php endif; ?>

  <?php include __DIR__ . '/inc/contact-band.php'; ?>
<?php include __DIR__ . '/inc/footer.php'; ?>
