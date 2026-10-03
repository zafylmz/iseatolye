<?php
/** @var array $c */ /** @var string $tok */
$st = $c['settings']; $ct = $c['contact'];
$socials = $c['socials'] ?? [];
?>
<div class="bar"><h1>Ayarlar</h1><nav class="jump jump--inline"><a href="#iletisim">İletişim</a><a href="#kayit">Kayıt</a><a href="#odeme">Ödeme</a><a href="#eposta">E-posta</a><a href="#metinler">Metinler</a></nav></div>
<form method="post">
  <?= hidden($tok, 'ayarlar') ?>
  <section class="card" id="iletisim">
    <h2>İletişim</h2>
    <label>İletişim bölümü başlığı<input name="contact_heading" value="<?= e($ct['heading'] ?? '') ?>"></label>
    <div class="grid3">
      <label>Telefon<input name="contact_phone" value="<?= e($ct['phone'] ?? '') ?>"></label>
      <label>WhatsApp numarası<input name="contact_whatsapp" value="<?= e($ct['whatsapp'] ?? '') ?>" placeholder="905075428798"></label>
      <label>E-posta<input type="email" name="contact_email" value="<?= e($ct['email'] ?? '') ?>"></label>
      <label>Adres<input name="contact_address" value="<?= e($ct['address'] ?? '') ?>"></label>
      <label class="span2">Çalışma saatleri<input name="contact_hours" value="<?= e($ct['hours'] ?? '') ?>"></label>
    </div>
    <p class="sub">Sosyal medya</p>
    <div class="rows" data-rows="socials">
      <?php foreach ($socials as $i => $s): ?><div class="rrow rrow--inline" data-row><input name="socials[<?= $i ?>][label]" value="<?= e($s['label']) ?>" aria-label="Ad"><input name="socials[<?= $i ?>][url]" value="<?= e($s['url']) ?>" aria-label="Bağlantı"><button type="button" class="btn btn--ghost btn--sm" data-remove>Kaldır</button></div><?php endforeach; ?>
    </div>
    <template data-tpl="socials"><div class="rrow rrow--inline" data-row><input name="socials[__i__][label]" placeholder="Instagram" aria-label="Ad"><input name="socials[__i__][url]" placeholder="https://" aria-label="Bağlantı"><button type="button" class="btn btn--ghost btn--sm" data-remove>Kaldır</button></div></template>
    <div><button type="button" class="btn btn--ghost" data-add="socials">+ Bağlantı ekle</button></div>
  </section>

  <section class="card" id="kayit">
    <h2>Üyelik ve kayıt</h2>
    <div class="checks checks--col">
      <label class="check"><input type="checkbox" name="require_login" value="1"<?= !empty($st['require_login']) ? ' checked' : '' ?>> Etkinliğe katılmak için üyelik gereksin <span class="opt">(kapalıysa misafir olarak da kayıt olunabilir)</span></label>
      <label class="check"><input type="checkbox" name="comment_moderation" value="1"<?= !empty($st['comment_moderation']) ? ' checked' : '' ?>> Etkinlik yorumları ben onaylayınca yayınlansın</label>
    </div>
    <label>Kayıt sonrası teşekkür notu<textarea name="reg_success_note" rows="2"><?= e($st['reg_success_note'] ?? '') ?></textarea></label>
  </section>

  <section class="card" id="odeme">
    <h2>Havale / EFT bilgileri</h2>
    <p class="hint">Havale seçen katılımcıya kayıt sonrası ve e-postada gösterilir. Açıklamaya katılım kodunu yazması istenir.</p>
    <div class="grid3">
      <label>Banka<input name="bank_name" value="<?= e($st['bank_name'] ?? '') ?>"></label>
      <label>Alıcı adı<input name="bank_holder" value="<?= e($st['bank_holder'] ?? '') ?>"></label>
      <label>IBAN<input name="bank_iban" value="<?= e($st['bank_iban'] ?? '') ?>" placeholder="TR00 0000 0000 0000 0000 0000 00"></label>
    </div>
    <label>Ödeme notu<textarea name="payment_note" rows="2"><?= e($st['payment_note'] ?? '') ?></textarea></label>
    <label>Ödenmeyen kayıtlar kaç saat yer tutsun <span class="opt">(havale ve ödeme bağlantısı; 0 = süresiz)</span><input type="number" name="hold_hours" min="0" max="720" value="<?= (int) ($st['hold_hours'] ?? 48) ?>"></label>
    <label>Bilet satışlarında KDV oranı (%) <span class="opt">(Faturalar sayfasında matrah ve KDV bu orana göre hesaplanır)</span><input type="number" name="vat_rate" min="0" max="30" value="<?= (int) ($st['vat_rate'] ?? 20) ?>"></label>
    <p class="hint">Süre dolan ve ödemesi işaretlenmeyen kayıtlar silinmez, ama kontenjandan düşer ve yer başkasına açılır. Kötü niyetli sahte kayıtlarla etkinliğin "doldu" görünmesini önler.</p>
  </section>

  <section class="card" id="eposta">
    <h2>E-posta</h2>
    <label class="check"><input type="checkbox" name="mail_enabled" value="1"<?= !empty($st['mail_enabled']) ? ' checked' : '' ?>> Bilgilendirme e-postaları gönderilsin (kayıt, onay, şifre sıfırlama)</label>
    <div class="grid2">
      <label>Gönderen adresi <span class="opt">(alan adınızdan olmalı, ör. bilgi@iseatolye.com.tr)</span><input type="email" name="mail_from" value="<?= e($st['mail_from'] ?? '') ?>"></label>
      <label>Bildirimler hangi adrese gelsin <span class="opt">(boşsa iletişim e-postası)</span><input type="email" name="notify_email" value="<?= e($st['notify_email'] ?? '') ?>"></label>
    </div>
    <p class="hint">Gönderen adresini cPanel'de "E-posta Hesapları" bölümünden oluşturun; yoksa e-postalar istenmeyen klasörüne düşebilir.</p>
  </section>

  <section class="card" id="metinler">
    <h2>Metinler</h2>
    <label>Genel iptal ve iade koşulu<textarea name="cancel_policy" rows="3"><?= e($st['cancel_policy'] ?? '') ?></textarea></label>
    <label>Katılım koşulları sayfası <span class="opt">(## ile ara başlık)</span><textarea name="terms" rows="12" class="mono"><?= e($st['terms'] ?? '') ?></textarea></label>
    <label>Site adresi<input name="site_url" value="<?= e($st['site_url'] ?? '') ?>"></label>
  </section>
  <div class="actions"><button class="btn">Ayarları kaydet</button></div>
</form>
<?php require_once ROOT . '/inc/smtp.php'; $sm = smtp_config() ?? []; $mailHost = preg_replace('/^www\./', '', strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'iseatolye.com.tr')))); ?>
<form class="card" method="post" autocomplete="off" id="smtp">
  <?= hidden($tok, 'smtp') ?>
  <h2>E-posta hesabı (SMTP) <?= $sm ? pill('Bağlı', 'on') : (php_mail_ok() ? pill('İsteğe bağlı', 'warn') : pill('Gerekli', 'off')) ?></h2>
  <p class="hint"><?= php_mail_ok() ? 'Sunucunun kendi gönderimi açık, ama bir e-posta hesabıyla göndermek iletilerin spam klasörüne düşmesini azaltır.' : 'Sunucunuzda PHP mail() kapalı. Sitenin e-posta gönderebilmesi için cPanel\'de açtığınız bir e-posta hesabının bilgilerini girin.' ?> E-postalar bu hesaptan gönderilir.</p>
  <div class="grid2">
    <label>Giden sunucu<input name="smtp_host" value="<?= e($sm['host'] ?? 'mail.' . $mailHost) ?>" required></label>
    <label>Port<select name="smtp_port"><?php foreach (['465' => '465 (SSL, önerilen)', '587' => '587 (STARTTLS)'] as $v => $l): ?><option value="<?= $v ?>"<?= (string) ($sm['port'] ?? '465') === $v ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    <label>E-posta adresi (kullanıcı adı)<input type="email" name="smtp_user" value="<?= e($sm['user'] ?? '') ?>" placeholder="iletisim@<?= e($mailHost) ?>" required></label>
    <label>Şifre <span class="opt"><?= $sm ? '(değiştirmeyecekseniz boş bırakın)' : '' ?></span><input type="password" name="smtp_pass" autocomplete="new-password"<?= $sm ? '' : ' required' ?>></label>
  </div>
  <div class="actions"><button class="btn">Kaydet ve deneme e-postası gönder</button><?php if ($sm): ?> <button class="btn btn--ghost" name="kaldir" value="1" formnovalidate>SMTP ayarını kaldır</button><?php endif; ?></div>
</form>
<section class="card">
  <h2>E-posta denemesi</h2>
  <form method="post"><?= hidden($tok, 'deneme-epostasi') ?><button class="btn btn--ghost">Kendime deneme e-postası gönder</button></form>
</section>
