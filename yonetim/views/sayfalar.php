<?php
/** @var array $c */ /** @var string $tok */
$t = (string) ($_GET['t'] ?? 'ana');
$tabs = ['ana' => 'Ana sayfa', 'hakkimizda' => 'Hakkımızda', 'kurumsal' => 'Kurumsal', 'marka' => 'Logo, marka ve duyuru'];
if (!isset($tabs[$t])) $t = 'ana';
$icons = ['nilufer' => 'Nilüfer (iyi oluş)', 'ekip' => 'Ekip', 'isik' => 'Ampul (yaratıcılık)', 'yaprak' => 'Yaprak', 'el-emegi' => 'El emeği', 'kalem' => 'Kalem', 'yildiz' => 'Yıldız', 'kisiler' => 'Kişiler'];
$pair = function (string $name, array $items, int $min, array $labels, bool $withIcon = false) use ($icons): string {
  while (count($items) < $min) $items[] = [];
  ob_start();
  foreach (array_values($items) as $i => $s): ?>
    <div class="grid2 row<?= $withIcon ? ' row--icon' : '' ?>">
      <?php if ($withIcon): ?><label>Simge<select name="<?= $name ?>[<?= $i ?>][icon]"><?php foreach ($icons as $k => $l): ?><option value="<?= $k ?>"<?= ($s['icon'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label><?php endif; ?>
      <label><?= $labels[0] ?><input name="<?= $name ?>[<?= $i ?>][<?= $labels[2] ?>]" value="<?= e($s[$labels[2]] ?? '') ?>"></label>
      <label class="<?= $withIcon ? 'span2' : '' ?>"><?= $labels[1] ?><textarea name="<?= $name ?>[<?= $i ?>][<?= $labels[3] ?>]" rows="2"><?= e($s[$labels[3]] ?? '') ?></textarea></label>
    </div>
  <?php endforeach;
  return ob_get_clean();
};
?>
<div class="bar"><h1>Sayfalar</h1><nav class="seg"><?php foreach ($tabs as $k => $l): ?><a href="./?s=sayfalar&amp;t=<?= $k ?>"<?= $t === $k ? ' aria-current="page"' : '' ?>><?= $l ?></a><?php endforeach; ?></nav></div>

<?php if ($t === 'ana'): $h = $c['home']; ?>
  <form method="post" enctype="multipart/form-data">
    <?= hidden($tok, 'sayfa-ana') ?>
    <section class="card">
      <h2>Giriş bölümü</h2>
      <p class="sub">Kemerli görsel</p>
      <?= image_field('hero_image', (string) ($h['hero_image'] ?? ''), ['shape' => 'tall', 'hint' => 'Sıradaki etkinliğin kapak görseli otomatik gösterilir; bu görsel yalnızca yaklaşan etkinlik yokken kullanılır. Dikey, 1200x1500 piksel önerilir.']) ?>
      <label>Üst satır<input name="eyebrow" value="<?= e($h['eyebrow'] ?? '') ?>"></label>
      <label>Büyük başlık<input name="title" value="<?= e($h['title'] ?? '') ?>"></label>
      <label>Açıklama<textarea name="subtitle" rows="2"><?= e($h['subtitle'] ?? '') ?></textarea></label>
    </section>
    <section class="card">
      <h2>Rakamlar</h2>
      <p class="hint">Boş bıraktığınız satır görünmez.</p>
      <?php $stats = $c['stats'] ?? []; while (count($stats) < 3) $stats[] = []; foreach ($stats as $i => $s): ?>
        <div class="grid2 row"><label>Değer<input name="stats[<?= $i ?>][value]" value="<?= e($s['value'] ?? '') ?>" placeholder="Her hafta"></label><label>Açıklama<input name="stats[<?= $i ?>][label]" value="<?= e($s['label'] ?? '') ?>" placeholder="yeni bir etkinlik"></label></div>
      <?php endforeach; ?>
    </section>
    <section class="card">
      <h2>Bölüm başlıkları</h2>
      <div class="grid2">
        <label>Etkinlikler<input name="events_title" value="<?= e($h['events_title'] ?? '') ?>"></label>
        <label>Takvim<input name="calendar_title" value="<?= e($h['calendar_title'] ?? '') ?>"></label>
      </div>
      <label>Takvim açıklaması<input name="calendar_text" value="<?= e($h['calendar_text'] ?? '') ?>"></label>
      <div class="grid2">
        <label>Topluluk<input name="community_title" value="<?= e($h['community_title'] ?? '') ?>"></label>
        <label>Topluluk açıklaması<input name="community_text" value="<?= e($h['community_text'] ?? '') ?>"></label>
      </div>
      <label>Alıntı<textarea name="quote" rows="2"><?= e($h['quote'] ?? '') ?></textarea></label>
    </section>
    <section class="card">
      <h2>Nasıl katılırım? adımları</h2>
      <label>Başlık<input name="steps_title" value="<?= e($h['steps_title'] ?? '') ?>"></label>
      <?= $pair('steps', $h['steps'] ?? [], 3, ['Adım', 'Açıklama', 'title', 'text']) ?>
    </section>
    <div class="actions"><button class="btn">Kaydet</button></div>
  </form>

<?php elseif ($t === 'hakkimizda'): $a = $c['about']; ?>
  <form method="post" enctype="multipart/form-data">
    <?= hidden($tok, 'sayfa-hakkimizda') ?>
    <section class="card">
      <h2>Metin</h2>
      <div class="grid2"><label>Başlık<input name="title" value="<?= e($a['title'] ?? '') ?>"></label><label>Giriş cümlesi<input name="lead" value="<?= e($a['lead'] ?? '') ?>"></label></div>
      <label>Hikâye <span class="opt">(paragrafları boş satırla ayırın)</span><textarea name="body" rows="10"><?= e($a['body'] ?? '') ?></textarea></label>
      <div class="grid2"><label>Kurucu adı<input name="founder" value="<?= e($a['founder'] ?? '') ?>"></label><label>Kurucu unvanı<input name="founder_title" value="<?= e($a['founder_title'] ?? '') ?>"></label></div>
    </section>
    <section class="card">
      <h2>Görseller</h2>
      <p class="sub">Ana görsel</p>
      <?= image_field('image', (string) ($a['image'] ?? ''), ['shape' => 'tall', 'hint' => 'Dikey önerilir.']) ?>
      <p class="sub">"Atölyeden kareler" bölümü <span class="opt">(sürükleyerek sıralayabilirsiniz)</span></p>
      <?= image_field('gallery', $a['gallery'] ?? [], ['multi' => true]) ?>
      <p class="hint">Eğitmenler bölümü etkinliklerdeki eğitmen listesinden otomatik gelir.</p>
    </section>
    <div class="actions"><a href="/hakkimizda/" target="_blank" rel="noopener">Sayfayı gör ↗</a><button class="btn">Kaydet</button></div>
  </form>

<?php elseif ($t === 'kurumsal'): $k = $c['corporate']; ?>
  <form method="post" enctype="multipart/form-data">
    <?= hidden($tok, 'sayfa-kurumsal') ?>
    <section class="card">
      <h2>Metin</h2>
      <div class="grid2"><label>Başlık<input name="title" value="<?= e($k['title'] ?? '') ?>"></label><label>Giriş cümlesi<input name="lead" value="<?= e($k['lead'] ?? '') ?>"></label></div>
      <label>Açıklama<textarea name="body" rows="8"><?= e($k['body'] ?? '') ?></textarea></label>
      <label>Çalışılan markalar <span class="opt">(her satır bir marka)</span><textarea name="brands" rows="3"><?= e(implode("\n", $k['brands'] ?? [])) ?></textarea></label>
      <p class="sub">Görsel</p>
      <?= image_field('image', (string) ($k['image'] ?? ''), ['shape' => 'wide']) ?>
    </section>
    <section class="card">
      <h2>Hizmet alanları</h2>
      <label>Başlık<input name="services_title" value="<?= e($k['services_title'] ?? '') ?>"></label>
      <?= $pair('services', $k['services'] ?? [], 3, ['Başlık', 'Açıklama', 'title', 'text'], true) ?>
    </section>
    <section class="card">
      <h2>Nasıl çalışıyoruz?</h2>
      <label>Başlık<input name="process_title" value="<?= e($k['process_title'] ?? '') ?>"></label>
      <?= $pair('process', $k['process'] ?? [], 3, ['Adım', 'Açıklama', 'title', 'text']) ?>
    </section>
    <section class="card">
      <h2>Kurumsal iletişim</h2>
      <div class="grid3"><label>Yetkili<input name="contact_name" value="<?= e($k['contact_name'] ?? '') ?>"></label><label>Telefon<input name="contact_phone" value="<?= e($k['contact_phone'] ?? '') ?>"></label><label>E-posta<input name="contact_email" value="<?= e($k['contact_email'] ?? '') ?>"></label></div>
    </section>
    <div class="actions"><a href="/kurumsal/" target="_blank" rel="noopener">Sayfayı gör ↗</a><button class="btn">Kaydet</button></div>
  </form>

<?php else: $b = $c['brand']; $an = $c['announcement'] ?? []; ?>
  <form method="post" enctype="multipart/form-data">
    <?= hidden($tok, 'marka') ?>
    <section class="card">
      <h2>Logo</h2>
      <p class="hint">Logo yüklemezseniz "<?= e($b['logo_text']) ?>" yazısı Montserrat harfleriyle aralıklı olarak gösterilir. Logo için yatay, arka planı şeffaf PNG ya da SVG'den dönüştürülmüş PNG önerilir (yükseklik en az 120 piksel).</p>
      <?php if (empty($b['logo'])): ?><span class="logo logo--preview"><?= e($b['logo_text']) ?></span><?php endif; ?>
      <?= image_field('logo', (string) ($b['logo'] ?? ''), ['shape' => 'logo', 'button' => 'Logo görseli seç', 'hint' => 'Görseli kaldırırsanız yazı logo kullanılır.']) ?>
      <div class="grid2"><label>Marka adı<input name="name" value="<?= e($b['name']) ?>"></label><label>Logo yazısı<input name="logo_text" value="<?= e($b['logo_text']) ?>"></label></div>
    </section>
    <section class="card">
      <h2>Tanıtım</h2>
      <label>Kısa slogan<input name="tagline" value="<?= e($b['tagline'] ?? '') ?>"></label>
      <label>Google açıklaması<textarea name="description" rows="2"><?= e($b['description'] ?? '') ?></textarea></label>
      <label>Alt bilgi yazısı<textarea name="footer_text" rows="2"><?= e($b['footer_text'] ?? '') ?></textarea></label>
      <p class="sub">Paylaşım görseli</p>
      <?= image_field('og_image', (string) ($b['og_image'] ?? ''), ['shape' => 'wide', 'hint' => 'WhatsApp ve Instagram\'da bağlantı paylaşıldığında görünür, 1200x630 piksel.']) ?>
    </section>
    <section class="card">
      <h2>Duyuru şeridi</h2>
      <p class="hint">Sitenin en üstünde ince bir şerit olarak görünür. Ör. "Kasım programı yayında!"</p>
      <label class="check"><input type="checkbox" name="ann_on" value="1"<?= !empty($an['on']) ? ' checked' : '' ?>> Duyuruyu göster</label>
      <div class="grid2"><label>Yazı<input name="ann_text" value="<?= e($an['text'] ?? '') ?>"></label><label>Bağlantı <span class="opt">(isteğe bağlı)</span><input name="ann_url" value="<?= e($an['url'] ?? '') ?>" placeholder="/takvim/"></label></div>
    </section>
    <div class="actions"><button class="btn">Kaydet</button></div>
  </form>
<?php endif; ?>
