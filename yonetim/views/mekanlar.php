<?php
/** @var string $tok */
$cat = catalog();
$edit = (string) ($_GET['duzenle'] ?? '');
$usage = [];
foreach (events_all() as $ev) foreach ($ev['sessions'] ?? [] as $s) if (($s['venue'] ?? '') !== '') $usage[$s['venue']][$ev['id']] = $ev['title'];
$instUse = [];
foreach (events_all() as $ev) foreach ($ev['instructors'] ?? [] as $i) $instUse[$i] = ($instUse[$i] ?? 0) + 1;
$venueForm = function (array $v) use ($tok): string {
  ob_start(); ?>
  <form method="post" enctype="multipart/form-data" class="subform">
    <?= hidden($tok, 'mekan', ['id' => $v['id'] ?? '']) ?>
    <div class="grid3">
      <label class="span2">Mekan adı<input name="name" value="<?= e($v['name'] ?? '') ?>" required></label>
      <label>Tür<select name="type"><?php foreach (['mekan' => 'Kapalı mekan', 'acik' => 'Açık hava', 'online' => 'Çevrimiçi'] as $k => $l): ?><option value="<?= $k ?>"<?= ($v['type'] ?? 'mekan') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label class="span3">Adres<input name="address" value="<?= e($v['address'] ?? '') ?>" placeholder="Mahalle, sokak, no"></label>
      <label>İlçe<input name="district" value="<?= e($v['district'] ?? 'Silivri') ?>"></label>
      <label>Şehir<input name="city" value="<?= e($v['city'] ?? 'İstanbul') ?>"></label>
      <label>Kapasite <span class="opt">(öneri olarak)</span><input type="number" name="capacity" value="<?= (int) ($v['capacity'] ?? 0) ?>" min="0"></label>
      <label class="span2">Harita bağlantısı <span class="opt">(Google Maps paylaş linki; boşsa adresten oluşur)</span><input type="url" name="map_url" value="<?= e($v['map_url'] ?? '') ?>" placeholder="https://maps.app.goo.gl/..."></label>
      <label>Telefon<input name="phone" value="<?= e($v['phone'] ?? '') ?>"></label>
    </div>
    <label>Ulaşım ve not <span class="opt">(otopark, kat, giriş, açık havada yağmur planı)</span><textarea name="note" rows="2"><?= e($v['note'] ?? '') ?></textarea></label>
    <p class="sub">Fotoğraf</p>
    <?= image_field('photo', (string) ($v['photo'] ?? ''), ['shape' => 'wide']) ?>
    <label>Sayfa adresi <span class="opt">(boşsa addan oluşur)</span><input name="slug" value="<?= e($v['slug'] ?? '') ?>"></label>
    <div class="plist__act plist__act--start"><button class="btn"><?= empty($v['id']) ? 'Mekanı ekle' : 'Kaydet' ?></button><?php if (!empty($v['id'])): ?><a href="./?s=mekanlar">Vazgeç</a><?php endif; ?></div>
  </form>
  <?php return ob_get_clean();
};
$instForm = function (array $x) use ($tok): string {
  ob_start(); ?>
  <form method="post" enctype="multipart/form-data" class="subform">
    <?= hidden($tok, 'egitmen', ['id' => $x['id'] ?? '']) ?>
    <div class="grid2">
      <label>Ad soyad<input name="name" value="<?= e($x['name'] ?? '') ?>" required></label>
      <label>Unvan<input name="title" value="<?= e($x['title'] ?? '') ?>" placeholder="Yoga eğitmeni"></label>
    </div>
    <label>Kısa tanıtım<textarea name="bio" rows="3"><?= e($x['bio'] ?? '') ?></textarea></label>
    <label>Instagram bağlantısı<input type="url" name="instagram" value="<?= e($x['instagram'] ?? '') ?>" placeholder="https://www.instagram.com/..."></label>
    <p class="sub">Fotoğraf</p>
    <?= image_field('photo', (string) ($x['photo'] ?? ''), ['shape' => 'round', 'hint' => 'Yuvarlak kırpılır; yüzün ortada olduğu kare fotoğraf önerilir.']) ?>
    <div class="plist__act plist__act--start"><button class="btn"><?= empty($x['id']) ? 'Eğitmeni ekle' : 'Kaydet' ?></button><?php if (!empty($x['id'])): ?><a href="./?s=mekanlar#egitmenler">Vazgeç</a><?php endif; ?></div>
  </form>
  <?php return ob_get_clean();
};
?>
<div class="bar"><h1>Mekan ve eğitmenler</h1><nav class="jump jump--inline"><a href="#mekanlar">Mekanlar</a><a href="#egitmenler">Eğitmenler</a><a href="#kategoriler">Kategoriler</a></nav></div>

<section class="card" id="mekanlar">
  <h2>Mekanlar <span class="count"><?= count($cat['venues']) ?></span></h2>
  <p class="hint">Etkinlik tarihlerinde seçtiğiniz mekanlar burada tutulur. Her mekanın sitede kendi sayfası olur ve orada yapılan etkinlikler listelenir.</p>
  <ul class="plist plist--flat">
    <?php foreach ($cat['venues'] as $v): ?>
      <?php if ($edit === $v['id']): ?><li class="card card--edit"><?= $venueForm($v) ?></li><?php continue; endif; ?>
      <li class="card">
        <?php if (!empty($v['photo'])): ?><img class="thumb" src="<?= e($v['photo']) ?>" alt=""><?php else: ?><span class="thumb thumb--empty">⌂</span><?php endif; ?>
        <div class="plist__info">
          <strong><?= e($v['name']) ?></strong>
          <span><?= e(['acik' => 'Açık hava', 'online' => 'Çevrimiçi'][$v['type'] ?? ''] ?? 'Kapalı mekan') ?> · <?= e(trim(implode(', ', array_filter([$v['address'] ?? '', $v['district'] ?? '']))) ?: 'Adres girilmedi') ?><?= !empty($v['capacity']) ? ' · ' . (int) $v['capacity'] . ' kişi' : '' ?></span>
          <span><?= isset($usage[$v['id']]) ? count($usage[$v['id']]) . ' etkinlikte kullanılıyor' : 'Henüz kullanılmadı' ?></span>
        </div>
        <div class="plist__act">
          <a class="btn btn--ghost" href="<?= e(venue_url($v)) ?>" target="_blank" rel="noopener">Gör</a>
          <a class="btn" href="./?s=mekanlar&amp;duzenle=<?= e(urlencode($v['id'])) ?>#mekanlar">Düzenle</a>
          <?php if (!isset($usage[$v['id']])): ?><form method="post" data-confirm="Bu mekanı silmek istiyor musunuz?"><?= hidden($tok, 'mekan-sil', ['id' => $v['id']]) ?><button class="btn btn--danger">Sil</button></form><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <details class="adder"<?= !$cat['venues'] ? ' open' : '' ?>><summary class="btn btn--ghost">+ Yeni mekan</summary><?= $venueForm([]) ?></details>
</section>

<section class="card" id="egitmenler">
  <h2>Eğitmenler <span class="count"><?= count($cat['instructors']) ?></span></h2>
  <ul class="plist plist--flat">
    <?php foreach ($cat['instructors'] as $x): ?>
      <?php if ($edit === $x['id']): ?><li class="card card--edit"><?= $instForm($x) ?></li><?php continue; endif; ?>
      <li class="card">
        <?php if (!empty($x['photo'])): ?><img class="thumb thumb--round" src="<?= e($x['photo']) ?>" alt=""><?php else: ?><span class="thumb thumb--round thumb--empty"><?= e(initial($x['name'])) ?></span><?php endif; ?>
        <div class="plist__info"><strong><?= e($x['name']) ?></strong><span><?= e($x['title'] ?? '') ?><?= isset($instUse[$x['id']]) ? ' · ' . $instUse[$x['id']] . ' etkinlik' : '' ?></span></div>
        <div class="plist__act">
          <a class="btn" href="./?s=mekanlar&amp;duzenle=<?= e(urlencode($x['id'])) ?>#egitmenler">Düzenle</a>
          <form method="post" data-confirm="Eğitmen silinecek ve etkinliklerden kaldırılacak. Emin misiniz?"><?= hidden($tok, 'egitmen-sil', ['id' => $x['id']]) ?><button class="btn btn--danger">Sil</button></form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <details class="adder"><summary class="btn btn--ghost">+ Yeni eğitmen</summary><?= $instForm([]) ?></details>
</section>

<section class="card" id="kategoriler">
  <h2>Kategoriler</h2>
  <p class="hint">Etkinlik listesinde filtre olarak görünür. Boş bıraktığınız satır silinir; kullanımda olan bir kategori silinirse o etkinlikler "kategorisiz" olur.</p>
  <form method="post">
    <?= hidden($tok, 'kategoriler') ?>
    <div class="rows" data-rows="cats">
      <?php foreach ($cat['categories'] as $i => $k): ?>
        <div class="rrow rrow--inline" data-row><input type="hidden" name="cats[<?= $i ?>][id]" value="<?= e($k['id']) ?>"><input name="cats[<?= $i ?>][name]" value="<?= e($k['name']) ?>" aria-label="Kategori adı"><span class="hint"><?= count(array_filter(events_all(), fn($ev) => ($ev['category'] ?? '') === $k['id'])) ?> etkinlik</span><button type="button" class="btn btn--ghost btn--sm" data-remove>Kaldır</button></div>
      <?php endforeach; ?>
    </div>
    <template data-tpl="cats"><div class="rrow rrow--inline" data-row><input type="hidden" name="cats[__i__][id]" value=""><input name="cats[__i__][name]" placeholder="Yeni kategori" aria-label="Kategori adı"><span></span><button type="button" class="btn btn--ghost btn--sm" data-remove>Kaldır</button></div></template>
    <div class="plist__act plist__act--start"><button type="button" class="btn btn--ghost" data-add="cats">+ Kategori ekle</button><button class="btn">Kategorileri kaydet</button></div>
  </form>
</section>
