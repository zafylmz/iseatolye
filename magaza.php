<?php
// Mağaza: ürün listesi (/magaza/) ve ürün sayfası (/magaza/urun-adi/).
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/shop.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/media.php';
$c = content();
$slug = (string) ($_GET['slug'] ?? '');
$intro = shop_text('tagline');

// Ürün kartında ve sayfasında stok yazısı
$stockLabel = function (array $p): string {
  $a = product_available($p);
  if ($a <= 0) return 'Tükendi';
  return 'Stokta ' . $a . ' adet';
};

if ($slug === '') {
  $list = products_public();
  $slides = shop_slides();
  $title = shop_text('title') . ' · ' . $c['brand']['name'];
  $description = $intro . ' ' . excerpt(shop_text('about'), 120);
  if ($slides) $image = $slides[0];
  $canonical = '/magaza/';
  $headExtra = '<script type="application/ld+json">' . json_encode(breadcrumb_ld(['Mağaza' => '/magaza/']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
  include __DIR__ . '/inc/header.php';
  ?>
  <section class="wrap page-head shop-head<?= $slides ? '' : ' shop-head--text' ?>">
    <div class="shop-head__text">
      <p class="label">Mağaza</p>
      <h1 class="display display--md"><?= e(shop_text('title')) ?></h1>
      <p class="lead"><?= e($intro) ?></p>
      <div class="prose shop-head__about"><?= paragraphs(shop_text('about')) ?></div>
    </div>
    <?php if ($slides): $gm = media_meta()['items']; ?>
      <div class="slider" data-slider aria-roledescription="carousel" aria-label="Mağazadan kareler">
        <div class="slider__track" data-slider-track data-zoom-group>
          <?php foreach ($slides as $i => $s): ?><button type="button" class="slider__slide" data-zoom-src="<?= e($s) ?>" data-zoom-cap="<?= e($gm[$s]['title'] ?? '') ?>" aria-label="Fotoğraf <?= $i + 1 ?> / <?= count($slides) ?>"><img src="<?= e($s) ?>" alt="<?= e($gm[$s]['title'] ?? '') ?>" width="1200" height="1200"<?= $i ? ' loading="lazy"' : ' fetchpriority="high"' ?> decoding="async"></button><?php endforeach; ?>
        </div>
        <?php if (count($slides) > 1): ?>
          <button type="button" class="slider__nav slider__nav--prev" data-slider-prev aria-label="Önceki fotoğraf"><?= icon('sol') ?></button>
          <button type="button" class="slider__nav slider__nav--next" data-slider-next aria-label="Sonraki fotoğraf"><?= icon('sag') ?></button>
          <div class="slider__dots" data-slider-dots><?php foreach ($slides as $i => $s): ?><button type="button" aria-label="<?= $i + 1 ?>. fotoğraf"<?= $i ? '' : ' aria-current="true"' ?>></button><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
  <section class="wrap page-body">
    <?php if ($list): ?>
      <div class="pgrid">
        <?php foreach ($list as $p): $a = product_available($p); $img = product_cover($p); ?>
          <article class="pcard<?= $a <= 0 ? ' pcard--out' : '' ?>" data-reveal>
            <a class="pcard__media" href="<?= e(product_url($p)) ?>" tabindex="-1" aria-hidden="true">
              <?php if ($img !== ''): ?><img src="<?= e($img) ?>" alt="" width="900" height="900" loading="lazy" decoding="async"><?php endif; ?>
              <?php if ($a <= 0): ?><span class="ecard__flag">Tükendi</span><?php endif; ?>
            </a>
            <div class="pcard__body">
              <h2 class="pcard__title"><a href="<?= e(product_url($p)) ?>"><?= e($p['name']) ?></a></h2>
              <?php if (trim($p['summary'] ?? '') !== ''): ?><p class="pcard__sum"><?= e($p['summary']) ?></p><?php endif; ?>
              <div class="ecard__foot"><span class="ecard__price"><?= money($p['price']) ?></span><span class="ecard__int"><?= e($stockLabel($p)) ?></span></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty"><p>Mağazamız çok yakında açılıyor. Ürünlerimizi buraya ekliyoruz.</p><a class="btn btn--ghost" href="/etkinlikler/">Etkinliklere göz atın</a></div>
    <?php endif; ?>
  </section>
  <?php
  include __DIR__ . '/inc/footer.php';
  exit;
}

$p = product_by_slug($slug);
if (!$p || !product_visible($p)) { http_response_code(404); include __DIR__ . '/404.php'; exit; }
$avail = product_available($p);
$max = product_max_qty($p);
$photos = array_values(array_filter((array) ($p['photos'] ?? [])));
$gm = media_meta()['items'];
$buyUrl = product_url($p) . 'siparis/';
$others = array_slice(array_values(array_filter(products_public(), fn($x) => $x['id'] !== $p['id'] && product_available($x) > 0)), 0, 3);

$title = $p['name'] . ' · Mağaza · ' . $c['brand']['name'];
$description = trim($p['summary'] ?? '') ?: excerpt($p['body'] ?? '');
$image = $photos[0] ?? '/og.jpg';
$imageAlt = $p['name'];
$canonical = product_url($p);
$ld = [
  '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['name'], 'description' => $description,
  'image' => array_map(fn($x) => site_url($x), $photos ?: ['/og.jpg']), 'url' => site_url(product_url($p)),
  'brand' => ['@type' => 'Brand', 'name' => $c['brand']['name']],
  'offers' => ['@type' => 'Offer', 'price' => (float) $p['price'], 'priceCurrency' => 'TRY', 'url' => site_url(product_url($p)), 'availability' => 'https://schema.org/' . ($avail > 0 ? 'InStock' : 'OutOfStock'), 'itemCondition' => 'https://schema.org/NewCondition'],
];
$headExtra = '<script type="application/ld+json">' . json_encode([$ld, breadcrumb_ld(['Mağaza' => '/magaza/', $p['name'] => product_url($p)])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
if ($photos) $headExtra .= '<link rel="preload" as="image" href="' . e($photos[0]) . '" fetchpriority="high">';
$ogType = 'product';
include __DIR__ . '/inc/header.php';
?>
  <article class="event product">
    <header class="wrap event__head">
      <a class="back" href="/magaza/"><?= icon('sol') ?>Mağaza</a>
      <h1 class="display display--md"><?= e($p['name']) ?></h1>
      <?php if (trim($p['summary'] ?? '') !== ''): ?><p class="lead"><?= e($p['summary']) ?></p><?php endif; ?>
    </header>

    <div class="wrap event__grid">
      <div class="event__main">
        <?php if ($photos): ?>
          <figure class="event__cover product__cover"><img src="<?= e($photos[0]) ?>" alt="<?= e($p['name']) ?>" width="1200" height="1200" fetchpriority="high" decoding="async" data-zoom></figure>
          <?php if (count($photos) > 1): ?><div class="event__gallery" data-zoom-group><?php foreach ($photos as $g): ?><button type="button" class="event__thumb" data-zoom-src="<?= e($g) ?>" data-zoom-cap="<?= e($gm[$g]['title'] ?? $p['name']) ?>"><img src="<?= e(thumb_url($g)) ?>" alt="<?= e($gm[$g]['title'] ?? '') ?>" loading="lazy"></button><?php endforeach; ?></div><?php endif; ?>
        <?php endif; ?>
        <section class="event__block product__desc" aria-labelledby="aciklama">
          <h2 class="h3" id="aciklama">Ürün açıklaması</h2>
          <div class="prose"><?= md($p['body'] ?? '') ?: '<p class="muted">Açıklama eklenmedi.</p>' ?></div>
        </section>
        <section class="event__block">
          <h2 class="h3">Teslimat ve iade</h2>
          <ul class="checks checks--plain">
            <?php if (in_array('kargo', delivery_options(), true)): $fee = (float) shop_setting('shipping_fee', 0); $free = (float) shop_setting('free_over', 0); ?>
              <li><?= icon('kargo') ?>Kargo ile adrese teslim<?= $fee > 0 ? ': ' . money($fee) . ($free > 0 ? ', ' . money($free) . ' ve üzeri ücretsiz' : '') : ': ücretsiz' ?><?= trim((string) shop_setting('ship_days', '')) !== '' ? ' · ' . e(shop_setting('ship_days')) : '' ?></li>
            <?php endif; ?>
            <?php if (in_array('elden', delivery_options(), true)): ?><li><?= icon('konum') ?>Atölyeden ücretsiz teslim alabilirsiniz<?= trim((string) shop_setting('pickup_note', '')) !== '' ? ' · ' . e(shop_setting('pickup_note')) : '' ?></li><?php endif; ?>
            <?php if (!empty($p['no_return'])): ?><li><?= icon('bilgi') ?>Bu ürün kişiye özel hazırlandığı için cayma hakkı kapsamında değildir. Ayıplı ürünlerde yasal haklarınız saklıdır. <a class="link" href="/mesafeli-satis/#urun-satisi">Ayrıntılar</a></li>
            <?php else: ?><li><?= icon('geri') ?>Teslimattan sonraki 14 gün içinde cayma hakkınız vardır. <a class="link" href="/mesafeli-satis/#urun-satisi">Ayrıntılar</a></li><?php endif; ?>
          </ul>
        </section>
      </div>

      <aside class="event__side">
        <form class="book" method="get" action="<?= e($buyUrl) ?>" data-buy>
          <div class="book__price"><span class="label">Fiyat</span><strong><?= money($p['price']) ?></strong></div>
          <ul class="book__meta">
            <li><?= icon('canta') ?><span><?= e($stockLabel($p)) ?><?php if ($avail > 0 && $avail <= 3): ?><small>Son ürünler</small><?php endif; ?></span></li>
            <?php if (in_array('kargo', delivery_options(), true)): ?><li><?= icon('kargo') ?><span>Kargo ile teslim<?php if (trim((string) shop_setting('ship_days', '')) !== ''): ?><small><?= e(shop_setting('ship_days')) ?></small><?php endif; ?></span></li><?php endif; ?>
          </ul>
          <?php if ($max > 0): ?>
            <div class="qty">
              <label for="adet">Adet</label>
              <div class="qty__ctl"><button type="button" class="btn btn--ghost btn--icon" data-qty="-1" aria-label="Azalt">−</button><input id="adet" name="adet" type="number" min="1" max="<?= $max ?>" value="1" inputmode="numeric"><button type="button" class="btn btn--ghost btn--icon" data-qty="1" aria-label="Artır">+</button></div>
            </div>
            <button class="btn btn--block" type="submit"><?= icon('canta') ?>Satın al</button>
          <?php else: ?>
            <span class="btn btn--block btn--off" aria-disabled="true">Tükendi</span>
            <?php if (trim($c['contact']['whatsapp'] ?? '') !== ''): ?><p class="muted small center">Yeniden geldiğinde haber vermemizi isterseniz bize yazın.</p><?php endif; ?>
          <?php endif; ?>
          <div class="book__links"><button type="button" data-share data-title="<?= e($p['name']) ?>" data-url="<?= e(site_url(product_url($p))) ?>"><?= icon('paylas') ?>Paylaş</button></div>
        </form>
        <?php if (trim($c['contact']['whatsapp'] ?? '') !== ''): ?><a class="side-help" href="<?= e(wa_href($c['contact']['whatsapp'], $p['name'] . ' hakkında bilgi almak istiyorum.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><span>Sorunuz mu var?<small>WhatsApp'tan yazın</small></span></a><?php endif; ?>
      </aside>
    </div>
  </article>

  <?php if ($others): ?>
  <section class="section wrap" aria-labelledby="diger">
    <div class="section-head"><div><p class="label">Mağaza</p><h2 class="h2" id="diger">Diğer ürünler</h2></div><a class="link-more" href="/magaza/">Tümü<?= icon('sag') ?></a></div>
    <div class="pgrid">
      <?php foreach ($others as $x): $img = product_cover($x); ?>
        <article class="pcard" data-reveal>
          <a class="pcard__media" href="<?= e(product_url($x)) ?>" tabindex="-1" aria-hidden="true"><?php if ($img !== ''): ?><img src="<?= e($img) ?>" alt="" width="900" height="900" loading="lazy" decoding="async"><?php endif; ?></a>
          <div class="pcard__body"><h3 class="pcard__title"><a href="<?= e(product_url($x)) ?>"><?= e($x['name']) ?></a></h3><div class="ecard__foot"><span class="ecard__price"><?= money($x['price']) ?></span><span class="ecard__int"><?= e($stockLabel($x)) ?></span></div></div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($max > 0): ?>
  <div class="mbar"><div><strong><?= money($p['price']) ?></strong><span><?= e($stockLabel($p)) ?></span></div><a class="btn" href="<?= e($buyUrl) ?>"><?= icon('canta') ?>Satın al</a></div>
  <?php endif; ?>
<?php include __DIR__ . '/inc/footer.php'; ?>
