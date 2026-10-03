      <?php
        $p = blog_find((string) ($_GET['slug'] ?? '')) ?? ['slug' => '', 'title' => '', 'category' => '', 'excerpt' => '', 'body' => '', 'cover' => '', 'date' => date('Y-m-d'), 'published' => false, 'comments' => true];
        $knownCats = array_column(blog_categories(blog_all()), 'name');
      ?>
      <div class="bar"><h1><?= $p['slug'] === '' ? 'Yeni yazı' : e($p['title']) ?></h1><?php if ($p['slug'] !== '' && !empty($p['published'])): ?><a href="/blog/<?= e($p['slug']) ?>/" target="_blank" rel="noopener">Sitede gör</a><?php endif; ?></div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="yazi"><input type="hidden" name="orig" value="<?= e($p['slug']) ?>">
        <section class="card">
          <label>Başlık<input name="title" value="<?= e($p['title']) ?>" required></label>
          <div class="grid3">
            <label>Kategori<input name="category" value="<?= e($p['category'] ?? '') ?>" list="cats" placeholder="Tasarım"></label>
            <label>Tarih<input type="date" name="date" value="<?= e($p['date'] ?? date('Y-m-d')) ?>"></label>
            <label>Adres (boş bırakın, başlıktan oluşur)<input name="slug" value="<?= e($p['slug']) ?>" placeholder="yazi-adresi"></label>
          </div>
          <datalist id="cats"><?php foreach ($knownCats as $k): ?><option value="<?= e($k) ?>"><?php endforeach; ?></datalist>
          <label>Kısa özet (listede ve Google'da görünür)<textarea name="excerpt" rows="2"><?= e($p['excerpt'] ?? '') ?></textarea></label>
          <div class="checks">
            <label class="check"><input type="checkbox" name="published" value="1"<?= !empty($p['published']) ? ' checked' : '' ?>> Yayında (işaretli değilse taslak)</label>
            <label class="check"><input type="checkbox" name="comments" value="1"<?= ($p['comments'] ?? true) !== false ? ' checked' : '' ?>> Yorumlara açık</label>
          </div>
        </section>
        <section class="card">
          <h2>Yazı</h2>
          <details class="howto"><summary>Yazım kısayolları</summary>
            <ul>
              <li>Paragrafları boş bir satırla ayırın.</li>
              <li><code>## Başlık</code> ara başlık, <code>### Alt başlık</code> küçük başlık. Ara başlıklar yazı sayfasında "Bu yazıda" listesine eklenir.</li>
              <li><code>**kalın**</code>, <code>*italik*</code>, <code>[bağlantı yazısı](https://adres.com)</code></li>
              <li>Satır başında <code>- </code> madde işareti, <code>1. </code> numaralı liste, <code>&gt; </code> alıntı.</li>
              <li>Görsel: imleci görselin gelmesini istediğiniz satıra koyup "Galeriden görsel ekle"ye basın; <code>![](/uploads/...)</code> satırı oraya eklenir. Köşeli paranteze açıklama yazabilirsiniz.</li>
            </ul>
          </details>
          <textarea name="body" id="yazi-body" rows="22" class="mono"><?= e($p['body'] ?? '') ?></textarea>
          <div><button type="button" class="btn btn--ghost btn--sm" data-pick-insert="#yazi-body">Galeriden görsel ekle</button></div>
        </section>
        <section class="card">
          <h2>Kapak görseli</h2>
          <?= image_field('cover', (string) ($p['cover'] ?? ''), ['shape' => 'wide']) ?>
          <p class="hint">1600x1000 piksel önerilir. Kapaksız yazılar listede yalnızca yazıyla görünür.</p>
        </section>
        <div class="actions"><a href="./?s=blog">Vazgeç</a><button class="btn">Kaydet</button></div>
      </form>

