<?php
/** @var string $tok */ /** @var array $regs */
$id = (string) ($_GET['id'] ?? '');
$cat = catalog();
$found = $id !== '' ? event_by_id($id) : null;
$isNew = !$found;
$ev = $found ?? [
  'id' => '', 'slug' => '', 'status' => 'taslak', 'title' => '', 'summary' => '', 'category' => $cat['categories'][0]['id'] ?? '', 'cover' => '', 'gallery' => [], 'body' => '',
  'includes' => [], 'bring' => [], 'faq' => [], 'instructors' => [], 'level' => 'herkes', 'age' => '', 'duration' => '',
  'sessions' => [['id' => '', 'date' => '', 'start' => '', 'end' => '', 'venue' => $cat['venues'][0]['id'] ?? '', 'place' => '', 'capacity' => 0, 'status' => 'acik', 'note' => '']],
  'package' => false, 'capacity' => 0, 'tickets' => [['id' => '', 'name' => 'Kişi başı', 'price' => 0, 'seats' => 1, 'limit' => 0, 'until' => '', 'note' => '']],
  'pay_methods' => ['havale', 'yerinde'], 'pay_link' => '', 'reg_open' => true, 'reg_close_hours' => 3, 'max_per_order' => 4, 'waitlist' => true, 'approval' => false,
  'show_left' => true, 'show_attendees' => true, 'comments' => true, 'featured' => false, 'pinned' => false, 'cancel_policy' => '', 'seo_title' => '',
];
// Hatalı kayıttan sonra girilen bilgiler kaybolmasın
$old = $_SESSION['old_post'] ?? null;
unset($_SESSION['old_post']);
if (is_array($old) && (string) ($old['id'] ?? '') === $ev['id']) {
  foreach (['title', 'summary', 'body', 'status', 'category', 'level', 'age', 'duration', 'slug', 'cancel_policy', 'pay_link', 'seo_title', 'capacity', 'reg_close_hours', 'max_per_order'] as $k) if (isset($old[$k])) $ev[$k] = $old[$k];
  foreach (['sessions', 'tickets', 'faq'] as $k) $ev[$k] = array_values((array) ($old[$k] ?? []));
  foreach (['includes', 'bring'] as $k) $ev[$k] = lines((string) ($old[$k] ?? ''));
  foreach (['package', 'reg_open', 'waitlist', 'approval', 'show_left', 'show_attendees', 'comments', 'featured', 'pinned'] as $k) $ev[$k] = !empty($old[$k]);
  $ev['pay_methods'] = (array) ($old['pay_methods'] ?? []);
  $ev['instructors'] = (array) ($old['instructors'] ?? []);
}
$evRegs = $ev['id'] !== '' ? regs_of_event($ev['id'], $regs) : [];
$regCount = fn(string $sid) => count(array_filter($evRegs, fn($r) => $r['session'] === $sid && $r['status'] !== 'iptal'));
$venueOpts = function (string $sel) use ($cat): string {
  $h = '<option value="">Diğer (yeri aşağıya yazın)</option>';
  foreach ($cat['venues'] as $v) $h .= '<option value="' . e($v['id']) . '" data-cap="' . (int) ($v['capacity'] ?? 0) . '"' . ($sel === $v['id'] ? ' selected' : '') . '>' . e($v['name']) . ($v['capacity'] ? ' · ' . (int) $v['capacity'] . ' kişi' : '') . '</option>';
  return $h;
};
$sessionRow = function (string $i, array $s) use ($venueOpts, $regCount): string {
  $n = $s['id'] !== '' ? $regCount($s['id']) : 0;
  ob_start(); ?>
  <div class="rrow" data-row>
    <input type="hidden" name="sessions[<?= $i ?>][id]" value="<?= e($s['id'] ?? '') ?>">
    <div class="rrow__grid rrow__grid--session">
      <label>Tarih<input type="date" name="sessions[<?= $i ?>][date]" value="<?= e($s['date'] ?? '') ?>"></label>
      <label>Başlangıç<input type="time" name="sessions[<?= $i ?>][start]" value="<?= e($s['start'] ?? '') ?>"></label>
      <label>Bitiş<input type="time" name="sessions[<?= $i ?>][end]" value="<?= e($s['end'] ?? '') ?>"></label>
      <label class="span2">Mekan<select name="sessions[<?= $i ?>][venue]" data-venue><?= $venueOpts((string) ($s['venue'] ?? '')) ?></select></label>
      <label data-session-cap>Kontenjan<input type="number" name="sessions[<?= $i ?>][capacity]" value="<?= (int) ($s['capacity'] ?? 0) ?>" min="0" inputmode="numeric"></label>
      <label>Durum<select name="sessions[<?= $i ?>][status]"><?php foreach (['acik' => 'Açık', 'dolu' => 'Dolu (elle kapat)', 'iptal' => 'İptal edildi'] as $k => $l): ?><option value="<?= $k ?>"<?= ($s['status'] ?? 'acik') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label class="span3">Yer bilgisi <span class="opt">(mekan "Diğer" ise ya da buluşma noktası)</span><input name="sessions[<?= $i ?>][place]" value="<?= e($s['place'] ?? '') ?>" placeholder="Ör. Selimpaşa sahil, iskele önü"></label>
      <label class="span2">Kısa not <span class="opt">(isteğe bağlı)</span><input name="sessions[<?= $i ?>][note]" value="<?= e($s['note'] ?? '') ?>" placeholder="Ör. 1. hafta, Sabah grubu"></label>
    </div>
    <div class="rrow__foot"><span class="hint"><?= $n ? $n . ' kayıt var; silmek yerine iptal edin.' : '' ?></span><button type="button" class="btn btn--ghost btn--sm" data-remove<?= $n ? ' disabled title="Kaydı olan tarih silinemez"' : '' ?>>Tarihi kaldır</button></div>
  </div>
  <?php return ob_get_clean();
};
$ticketRow = function (string $i, array $t): string {
  ob_start(); ?>
  <div class="rrow" data-row>
    <input type="hidden" name="tickets[<?= $i ?>][id]" value="<?= e($t['id'] ?? '') ?>">
    <div class="rrow__grid rrow__grid--ticket">
      <label class="span2">Bilet adı<input name="tickets[<?= $i ?>][name]" value="<?= e($t['name'] ?? '') ?>" placeholder="Kişi başı, Erken kayıt, İki kişilik"></label>
      <label>Fiyat (₺)<input name="tickets[<?= $i ?>][price]" value="<?= e((string) ($t['price'] ?? 0)) ?>" inputmode="decimal"></label>
      <label>Kaç kişilik<input type="number" name="tickets[<?= $i ?>][seats]" value="<?= (int) ($t['seats'] ?? 1) ?>" min="1" max="20"></label>
      <label>Adet sınırı <span class="opt">(0 = yok)</span><input type="number" name="tickets[<?= $i ?>][limit]" value="<?= (int) ($t['limit'] ?? 0) ?>" min="0"></label>
      <label>Son satış günü<input type="date" name="tickets[<?= $i ?>][until]" value="<?= e($t['until'] ?? '') ?>"></label>
      <label class="span3">Açıklama <span class="opt">(isteğe bağlı)</span><input name="tickets[<?= $i ?>][note]" value="<?= e($t['note'] ?? '') ?>" placeholder="Ör. Tüm malzemeler dahil"></label>
    </div>
    <div class="rrow__foot"><span></span><button type="button" class="btn btn--ghost btn--sm" data-remove>Bileti kaldır</button></div>
  </div>
  <?php return ob_get_clean();
};
$faqRow = function (string $i, array $q): string {
  ob_start(); ?>
  <div class="rrow" data-row>
    <label>Soru<input name="faq[<?= $i ?>][q]" value="<?= e($q['q'] ?? '') ?>"></label>
    <label>Cevap<textarea name="faq[<?= $i ?>][a]" rows="2"><?= e($q['a'] ?? '') ?></textarea></label>
    <div class="rrow__foot"><span></span><button type="button" class="btn btn--ghost btn--sm" data-remove>Soruyu kaldır</button></div>
  </div>
  <?php return ob_get_clean();
};
?>
<div class="bar">
  <div><a class="back" href="./?s=etkinlikler">← Etkinlikler</a><h1><?= $isNew ? 'Yeni etkinlik' : e($ev['title']) ?></h1></div>
  <?php if (!$isNew): ?>
    <div class="bar__act">
      <?php if (($ev['status'] ?? '') !== 'taslak'): ?><a class="btn btn--ghost" href="<?= e(event_url($ev)) ?>" target="_blank" rel="noopener">Sitede gör ↗</a><?php endif; ?>
      <a class="btn btn--ghost" href="./?s=katilimlar&amp;etkinlik=<?= e(urlencode($ev['id'])) ?>">Katılımlar (<?= count(array_filter($evRegs, fn($r) => $r['status'] !== 'iptal')) ?>)</a>
    </div>
  <?php endif; ?>
</div>

<form method="post" enctype="multipart/form-data" class="editor" data-editor>
  <?= hidden($tok, 'etkinlik', ['id' => $ev['id'], 'old_cover' => $ev['cover'] ?? '']) ?>
  <nav class="jump" aria-label="Bölümler"><a href="#temel">Temel</a><a href="#tarihler">Tarih ve mekan</a><a href="#biletler">Bilet ve ödeme</a><a href="#kurallar">Kayıt kuralları</a><a href="#detaylar">Detaylar</a><a href="#gorseller">Görseller</a></nav>

  <section class="card" id="temel">
    <h2>Temel bilgiler</h2>
    <label>Etkinlik adı<input name="title" value="<?= e($ev['title']) ?>" required maxlength="120"></label>
    <div class="grid3">
      <label>Durum<select name="status"><?php foreach (EVENT_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $ev['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label>Kategori<select name="category"><option value="">Seçilmedi</option><?php foreach ($cat['categories'] as $k): ?><option value="<?= e($k['id']) ?>"<?= $ev['category'] === $k['id'] ? ' selected' : '' ?>><?= e($k['name']) ?></option><?php endforeach; ?></select></label>
      <label>Seviye<select name="level"><?php foreach (LEVELS as $k => $l): ?><option value="<?= $k ?>"<?= ($ev['level'] ?? '') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label>Süre <span class="opt">(isteğe bağlı)</span><input name="duration" value="<?= e($ev['duration'] ?? '') ?>" placeholder="2 saat"></label>
      <label>Yaş <span class="opt">(isteğe bağlı)</span><input name="age" value="<?= e($ev['age'] ?? '') ?>" placeholder="18+, 7-12 yaş"></label>
      <label>Adres <span class="opt">(boş bırakın, addan oluşur)</span><input name="slug" value="<?= e($ev['slug']) ?>" placeholder="etkinlik-adi"></label>
    </div>
    <p class="hint">Taslak: sitede görünmez. Ertelendi / İptal edildi: sayfa açık kalır, kayıt alınmaz ve etiket gösterilir.</p>
    <label>Kısa tanıtım <span class="opt">(kartlarda ve Google'da görünür, en fazla 300 karakter)</span><textarea name="summary" rows="2" maxlength="300"><?= e($ev['summary'] ?? '') ?></textarea></label>
    <label>Açıklama<textarea name="body" rows="10" class="mono"><?= e($ev['body'] ?? '') ?></textarea></label>
    <details class="howto"><summary>Yazım kısayolları</summary><ul><li>Paragrafları boş bir satırla ayırın.</li><li><code>## Başlık</code> ara başlık, <code>- </code> madde, <code>**kalın**</code>, <code>[bağlantı](https://...)</code></li></ul></details>
    <div class="checks">
      <label class="check"><input type="checkbox" name="featured" value="1"<?= !empty($ev['featured']) ? ' checked' : '' ?>> Ana sayfada öne çıkar</label>
      <label class="check"><input type="checkbox" name="pinned" value="1"<?= !empty($ev['pinned']) ? ' checked' : '' ?>> Listelerde en üstte sabitle</label>
    </div>
  </section>

  <section class="card" id="tarihler">
    <h2>Tarih ve mekan</h2>
    <p class="hint">Aynı etkinliği farklı gün, saat ya da mekanlarda yapacaksanız her biri için bir tarih ekleyin. Katılımcı kayıt olurken tarihini seçer; her tarihin kendi kontenjanı olur. Kontenjan 0 ise sınırsızdır.</p>
    <label class="check check--box"><input type="checkbox" name="package" value="1" data-package<?= !empty($ev['package']) ? ' checked' : '' ?>><span><strong>Tek kayıtla tüm tarihlere katılınır</strong> (kurs, çok haftalı program). Kontenjan etkinlik için tek bir sayıdır.</span></label>
    <label class="narrow" data-package-cap>Kurs kontenjanı <span class="opt">(0 = sınırsız)</span><input type="number" name="capacity" value="<?= (int) ($ev['capacity'] ?? 0) ?>" min="0"></label>
    <div class="rows" data-rows="sessions">
      <?php foreach (array_values($ev['sessions']) as $i => $s) echo $sessionRow((string) $i, $s + ['id' => '']); ?>
    </div>
    <template data-tpl="sessions"><?= $sessionRow('__i__', ['id' => '', 'venue' => $cat['venues'][0]['id'] ?? '', 'status' => 'acik', 'capacity' => 0]) ?></template>
    <div><button type="button" class="btn btn--ghost" data-add="sessions">+ Tarih ekle</button> <button type="button" class="btn btn--ghost" data-dup-last="sessions" title="Son tarihi bir hafta sonrasına kopyalar">+ Bir hafta sonrası</button></div>
    <p class="hint">Mekan listesini <a href="./?s=mekanlar">Mekan ve eğitmenler</a> bölümünden düzenleyebilirsiniz. Mekan seçince kontenjan boşsa mekanın kapasitesi önerilir.</p>
  </section>

  <section class="card" id="biletler">
    <h2>Bilet ve ödeme</h2>
    <p class="hint">Ücretsiz etkinlikte fiyatı 0 yazın. Erken kayıt için son satış günü olan ayrı bir bilet, çift ya da grup için "kaç kişilik" değeri 2 olan bir bilet ekleyebilirsiniz.</p>
    <div class="rows" data-rows="tickets">
      <?php foreach (array_values($ev['tickets']) as $i => $t) echo $ticketRow((string) $i, $t); ?>
    </div>
    <template data-tpl="tickets"><?= $ticketRow('__i__', ['seats' => 1]) ?></template>
    <div><button type="button" class="btn btn--ghost" data-add="tickets">+ Bilet ekle</button></div>
    <p class="sub">Ödeme yöntemleri <span class="opt">(ücretli biletler için)</span></p>
    <div class="checks">
      <?php foreach (PAY_METHODS as $k => $l): ?><label class="check"><input type="checkbox" name="pay_methods[]" value="<?= $k ?>"<?= in_array($k, $ev['pay_methods'] ?? [], true) ? ' checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?>
    </div>
    <label>Online ödeme bağlantısı <span class="opt">(iyzico, Shopier, PayTR linki vb.)</span><input type="url" name="pay_link" value="<?= e($ev['pay_link'] ?? '') ?>" placeholder="https://"></label>
    <?php if (trim((string) setting('bank_iban', '')) === ''): ?><p class="notice">Havale seçeneği için <a href="./?s=ayarlar#odeme">Ayarlar</a> bölümünden banka ve IBAN bilgisini girin.</p><?php endif; ?>
  </section>

  <section class="card" id="kurallar">
    <h2>Kayıt kuralları</h2>
    <div class="grid3">
      <label>Kayıtlar kaç saat önce kapansın<input type="number" name="reg_close_hours" value="<?= (int) ($ev['reg_close_hours'] ?? 0) ?>" min="0"></label>
      <label>Bir kayıtta en fazla kişi<input type="number" name="max_per_order" value="<?= (int) ($ev['max_per_order'] ?? 4) ?>" min="1" max="50"></label>
    </div>
    <div class="checks checks--col">
      <label class="check"><input type="checkbox" name="reg_open" value="1"<?= !empty($ev['reg_open']) ? ' checked' : '' ?>> Kayıtlar açık</label>
      <label class="check"><input type="checkbox" name="waitlist" value="1"<?= !empty($ev['waitlist']) ? ' checked' : '' ?>> Kontenjan dolunca yedek liste tut</label>
      <label class="check"><input type="checkbox" name="approval" value="1"<?= !empty($ev['approval']) ? ' checked' : '' ?>> Her kaydı ben onaylayayım (ücretsiz etkinlikte de)</label>
      <label class="check"><input type="checkbox" name="show_left" value="1"<?= !empty($ev['show_left']) ? ' checked' : '' ?>> Kalan yer sayısını göster</label>
      <label class="check"><input type="checkbox" name="show_attendees" value="1"<?= !empty($ev['show_attendees']) ? ' checked' : '' ?>> Katılan üyeleri etkinlik sayfasında göster</label>
      <label class="check"><input type="checkbox" name="comments" value="1"<?= ($ev['comments'] ?? true) !== false ? ' checked' : '' ?>> Yorumlara açık</label>
    </div>
    <label>Bu etkinliğe özel iptal koşulu <span class="opt">(boşsa genel koşul gösterilir)</span><textarea name="cancel_policy" rows="2" placeholder="<?= e((string) setting('cancel_policy', '')) ?>"><?= e($ev['cancel_policy'] ?? '') ?></textarea></label>
  </section>

  <section class="card" id="detaylar">
    <h2>Detaylar</h2>
    <div class="grid2">
      <label>Ücrete dahil olanlar <span class="opt">(her satır bir madde)</span><textarea name="includes" rows="4"><?= e(implode("\n", $ev['includes'] ?? [])) ?></textarea></label>
      <label>Yanınızda getirin <span class="opt">(her satır bir madde)</span><textarea name="bring" rows="4"><?= e(implode("\n", $ev['bring'] ?? [])) ?></textarea></label>
    </div>
    <p class="sub">Eğitmenler</p>
    <?php if (!$cat['instructors']): ?><p class="hint">Henüz eğitmen eklenmedi. <a href="./?s=mekanlar#egitmenler">Eğitmen ekleyin.</a></p><?php endif; ?>
    <div class="checks"><?php foreach ($cat['instructors'] as $x): ?><label class="check"><input type="checkbox" name="instructors[]" value="<?= e($x['id']) ?>"<?= in_array($x['id'], $ev['instructors'] ?? [], true) ? ' checked' : '' ?>> <?= e($x['name']) ?></label><?php endforeach; ?></div>
    <p class="sub">Sık sorulan sorular</p>
    <div class="rows" data-rows="faq"><?php foreach (array_values($ev['faq'] ?? []) as $i => $q) echo $faqRow((string) $i, $q); ?></div>
    <template data-tpl="faq"><?= $faqRow('__i__', []) ?></template>
    <div><button type="button" class="btn btn--ghost" data-add="faq">+ Soru ekle</button></div>
    <label>Google başlığı <span class="opt">(isteğe bağlı, boşsa etkinlik adı)</span><input name="seo_title" value="<?= e($ev['seo_title'] ?? '') ?>" maxlength="90"></label>
  </section>

  <section class="card" id="gorseller">
    <h2>Görseller</h2>
    <p class="sub">Kapak görseli</p>
    <?= image_field('cover', (string) ($ev['cover'] ?? ''), ['shape' => 'wide', 'hint' => 'Yatay, 1600x1100 piksel önerilir.']) ?>
    <p class="sub">Etkinlik galerisi <span class="opt">(etkinlik sayfasında ve sitedeki Galeri sayfasında albüm olarak görünür; sürükleyerek sıralayabilirsiniz)</span></p>
    <?= image_field('gallery', $ev['gallery'] ?? [], ['multi' => true, 'hint' => 'Birden fazla fotoğraf seçebilir ya da yeni yükleyebilirsiniz.']) ?>
  </section>

  <div class="actions"><a href="./?s=etkinlikler">Vazgeç</a><button class="btn">Kaydet</button></div>
</form>

<?php if (!$isNew): ?>
  <section class="card card--danger">
    <h2>Diğer işlemler</h2>
    <div class="plist__act plist__act--start">
      <form method="post"><?= hidden($tok, 'etkinlik-kopyala', ['id' => $ev['id']]) ?><button class="btn btn--ghost">Kopyasını oluştur</button></form>
      <form method="post" data-confirm="<?= e($evRegs ? 'Bu etkinlikte ' . count($evRegs) . ' kayıt var. Etkinlik; kayıtları, yorumları ve ilgilenenleriyle birlikte kalıcı olarak silinecek. Emin misiniz?' : 'Bu etkinliği silmek istediğinize emin misiniz?') ?>"><?= hidden($tok, 'etkinlik-sil', ['id' => $ev['id']]) ?><button class="btn btn--danger">Etkinliği sil</button></form>
    </div>
    <p class="hint">Kopya; tekrar eden atölyeler için en hızlı yoldur: tarihleri değiştirip yayına alın. Yapılmayacak bir etkinliği silmek yerine "İptal edildi" yapmanız, kayıtlı kişilerin bilgilendirilmesi için daha doğrudur.</p>
  </section>
<?php endif; ?>
