<?php
/** @var string $tok */ /** @var array $orders */
$id = (string) ($_GET['id'] ?? '');
$found = $id !== '' ? product_by_id($id) : null;
$isNew = !$found;
$p = $found ?? ['id' => '', 'slug' => '', 'name' => '', 'status' => 'yayinda', 'price' => '', 'stock' => 1, 'max_per_order' => 0, 'summary' => '', 'body' => '', 'photos' => [], 'sort' => 0, 'no_return' => false];
// Hatalı kayıttan sonra girilen bilgiler kaybolmasın
$old = $_SESSION['old_post'] ?? null;
unset($_SESSION['old_post']);
if (is_array($old) && (string) ($old['id'] ?? '') === $p['id']) {
  foreach (['name', 'status', 'price', 'stock', 'max_per_order', 'summary', 'body', 'slug', 'sort'] as $k) if (isset($old[$k])) $p[$k] = $old[$k];
  $p['no_return'] = !empty($old['no_return']);
}
$mine = $p['id'] !== '' ? array_filter($orders, fn($o) => ($o['product'] ?? '') === $p['id']) : [];
?>
<div class="bar">
  <div><a class="back" href="./?s=urunler">← Ürünler</a><h1><?= $isNew ? 'Yeni ürün' : e($p['name']) ?></h1></div>
  <?php if (!$isNew): ?>
    <div class="bar__act">
      <?php if ($p['status'] === 'yayinda'): ?><a class="btn btn--ghost" href="<?= e(product_url($p)) ?>" target="_blank" rel="noopener">Sitede gör ↗</a><?php endif; ?>
      <a class="btn btn--ghost" href="./?s=siparisler&amp;urun=<?= e(urlencode($p['id'])) ?>">Siparişler (<?= count($mine) ?>)</a>
    </div>
  <?php endif; ?>
</div>

<form method="post" class="editor">
  <?= hidden($tok, 'urun', ['id' => $p['id']]) ?>
  <section class="card">
    <h2>Ürün bilgileri</h2>
    <label>Ürün adı<input name="name" value="<?= e($p['name']) ?>" required maxlength="120"></label>
    <div class="grid3">
      <label>Adet (stok)<input type="number" name="stock" value="<?= (int) $p['stock'] ?>" min="0" required inputmode="numeric"></label>
      <label>Fiyat (₺, KDV dahil)<input name="price" value="<?= e((string) $p['price']) ?>" inputmode="decimal" placeholder="450"></label>
      <label>Durum<select name="status"><?php foreach (PRODUCT_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $p['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    </div>
    <p class="hint">Stok 0 olunca ürün sitede "Tükendi" görünür ve sipariş alınmaz. Satılan her ürün stoktan kendiliğinden düşer.</p>
    <label>Kısa tanıtım <span class="opt">(ürün kartında ve Google'da görünür, en fazla 300 karakter)</span><textarea name="summary" rows="2" maxlength="300"><?= e($p['summary'] ?? '') ?></textarea></label>
    <label>Açıklama<textarea name="body" rows="10" class="mono" placeholder="Malzeme, ölçüler, renk, bakım bilgisi..."><?= e($p['body'] ?? '') ?></textarea></label>
    <details class="howto"><summary>Yazım kısayolları</summary><ul><li>Paragrafları boş bir satırla ayırın.</li><li><code>## Başlık</code> ara başlık, <code>- </code> madde, <code>**kalın**</code>, <code>[bağlantı](https://...)</code></li></ul></details>
  </section>

  <section class="card">
    <h2>Görseller</h2>
    <p class="hint">İlk görsel kapak olarak kullanılır; sürükleyerek sıralayabilirsiniz. Kare (1:1) görseller en iyi sonucu verir.</p>
    <?= image_field('photos', $p['photos'] ?? [], ['multi' => true, 'hint' => 'Birden fazla fotoğraf seçebilir ya da yeni yükleyebilirsiniz.']) ?>
  </section>

  <section class="card">
    <h2>Diğer</h2>
    <div class="grid3">
      <label>Bir siparişte en fazla <span class="opt">(0 = stok kadar)</span><input type="number" name="max_per_order" value="<?= (int) ($p['max_per_order'] ?? 0) ?>" min="0"></label>
      <label>Sıra <span class="opt">(küçük sayı önce)</span><input type="number" name="sort" value="<?= (int) ($p['sort'] ?? 0) ?>"></label>
      <label>Adres <span class="opt">(boş bırakın, addan oluşur)</span><input name="slug" value="<?= e($p['slug']) ?>" placeholder="urun-adi"></label>
    </div>
    <label class="check"><input type="checkbox" name="no_return" value="1"<?= !empty($p['no_return']) ? ' checked' : '' ?>> Kişiye özel ürün: cayma hakkı yok (sitede bu bilgi gösterilir)</label>
  </section>

  <div class="actions"><a href="./?s=urunler">Vazgeç</a><button class="btn">Kaydet</button></div>
</form>

<?php if (!$isNew): ?>
  <section class="card card--danger">
    <h2>Ürünü sil</h2>
    <form method="post" data-confirm="Bu ürün mağazadan kalıcı olarak silinecek. Satışı durdurmak için durumunu Taslak yapmanız yeterlidir. Emin misiniz?"><?= hidden($tok, 'urun-sil', ['id' => $p['id']]) ?><button class="btn btn--danger">Ürünü sil</button></form>
    <p class="hint">Silinen ürünün eski siparişleri Siparişler bölümünde kalır.</p>
  </section>
<?php endif; ?>
