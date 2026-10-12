<?php
/** @var string $tok */ /** @var array $orders */
$products = products_all();
usort($products, fn($a, $b) => [(int) ($a['sort'] ?? 0), $b['created'] ?? ''] <=> [(int) ($b['sort'] ?? 0), $a['created'] ?? '']);
$st = shop()['settings'] ?? [];
?>
<div class="bar"><h1>Ürünler</h1><a class="btn" href="./?s=urun">Yeni ürün</a></div>
<p class="hint">Mağaza sayfası: <a href="/magaza/" target="_blank" rel="noopener">/magaza/</a>. Etkinliklerden ayrıdır. Stok, ödemesi alınan siparişlerde kendiliğinden düşer; iptal edilen siparişte geri eklenir.</p>

<?php if (!$products): ?><section class="card"><p class="hint">Henüz ürün yok. <a href="./?s=urun">İlk ürünü ekleyin.</a></p></section><?php endif; ?>
<ul class="plist">
  <?php foreach ($products as $p):
    $mine = array_filter($orders, fn($o) => ($o['product'] ?? '') === $p['id']);
    $sold = array_sum(array_map(fn($o) => !empty($o['paid']) && $o['status'] !== 'iptal' ? (int) $o['qty'] : 0, $mine));
    $held = array_sum(array_map(fn($o) => order_holds($o) ? (int) $o['qty'] : 0, $mine));
    $img = product_cover($p); ?>
    <li class="card">
      <?php if ($img !== ''): ?><img class="thumb" src="<?= e(thumb_url($img)) ?>" alt=""><?php else: ?><span class="thumb thumb--empty">İA</span><?php endif; ?>
      <div class="plist__info">
        <strong><?= e($p['name']) ?></strong>
        <span><?= pill($p['status'] === 'yayinda' ? 'Satışta' : 'Taslak', $p['status'] === 'yayinda' ? 'on' : '') ?> <?= e(money($p['price'])) ?><?= (int) $p['stock'] <= 0 ? ' · <b>Tükendi</b>' : '' ?></span>
        <span>Stok <b><?= (int) $p['stock'] ?></b> adet<?= $held ? ' · ' . $held . ' adet ödeme bekleyen siparişte ayrılmış' : '' ?> · <?= $sold ?> adet satıldı</span>
      </div>
      <div class="plist__act">
        <form method="post" class="inline-form"><?= hidden($tok, 'urun-stok', ['id' => $p['id'], 'back' => './?s=urunler']) ?><label class="sr" for="stok-<?= e($p['id']) ?>">Stok</label><input id="stok-<?= e($p['id']) ?>" type="number" name="stock" value="<?= (int) $p['stock'] ?>" min="0" style="width:84px"><button class="btn btn--ghost">Stoğu kaydet</button></form>
        <?php if ($p['status'] === 'yayinda'): ?><a class="btn btn--ghost" href="<?= e(product_url($p)) ?>" target="_blank" rel="noopener">Gör</a><?php endif; ?>
        <a class="btn" href="./?s=urun&amp;id=<?= e(urlencode($p['id'])) ?>">Düzenle</a>
      </div>
    </li>
  <?php endforeach; ?>
</ul>

<form method="post" class="card" id="ayarlar">
  <?= hidden($tok, 'magaza-ayar') ?>
  <h2>Mağaza ayarları</h2>
  <p class="sub">Mağaza sayfasının girişi</p>
  <label>Başlık<input name="title" value="<?= e(shop_text('title')) ?>" maxlength="80"></label>
  <label>Alt başlık<input name="tagline" value="<?= e(shop_text('tagline')) ?>" maxlength="200"></label>
  <label>Tanıtım yazısı <span class="opt">(paragrafları boş satırla ayırın)</span><textarea name="about" rows="7" maxlength="3000"><?= e(shop_text('about')) ?></textarea></label>
  <p class="sub">Yazının yanındaki kayan galeri <span class="opt">(boşsa ürün ve galeri fotoğrafları gösterilir; sürükleyerek sıralayabilirsiniz)</span></p>
  <?= image_field('slides', $st['slides'] ?? [], ['multi' => true, 'hint' => 'Kare ya da dikey fotoğraflar en iyi sonucu verir.']) ?>
  <p class="sub">Kargo</p>
  <div class="grid3">
    <label>Kargo ücreti (₺) <span class="opt">(0 = ücretsiz)</span><input name="shipping_fee" value="<?= e((string) ($st['shipping_fee'] ?? 0)) ?>" inputmode="decimal"></label>
    <label>Ücretsiz kargo sınırı (₺) <span class="opt">(0 = yok)</span><input name="free_over" value="<?= e((string) ($st['free_over'] ?? 0)) ?>" inputmode="decimal"></label>
    <label>Gönderim süresi <span class="opt">(isteğe bağlı)</span><input name="ship_days" value="<?= e($st['ship_days'] ?? '') ?>" maxlength="80" placeholder="1-3 iş günü içinde kargoda"></label>
  </div>
  <p class="hint">Ödeme yöntemleri etkinliklerle aynıdır: kartla ödeme <a href="./?s=ayarlar#kart">PayTR bilgileri</a> girilince, havale <a href="./?s=ayarlar#odeme">IBAN</a> girilince görünür. Ödenmeyen havale siparişleri, Ayarlar'daki havale süresi dolunca ürünü ayırmayı bırakır.</p>
  <div><button class="btn">Kaydet</button></div>
</form>
