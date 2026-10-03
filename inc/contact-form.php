<?php
// İletişim / kurumsal teklif formu. $formTopic: 'genel' | 'kurumsal'
$formTopic = $formTopic ?? 'genel';
$fs = $formState ?? ['ok' => false, 'errors' => [], 'old' => []];
$o = $fs['old'] + ['name' => current_user()['name'] ?? '', 'email' => current_user()['email'] ?? '', 'phone' => current_user()['phone'] ?? '', 'company' => '', 'people' => '', 'date' => '', 'text' => '', 'topic' => $formTopic];
?>
<form class="form-card cform-contact" method="post" action="/iletisim/" data-guard-form novalidate id="form">
  <?php if ($fs['ok']): ?>
    <div class="done done--inline"><span class="done__ico"><?= icon('onay') ?></span><h3 class="h3">Mesajınız bize ulaştı</h3><p class="muted">En kısa sürede size dönüş yapacağız. Teşekkür ederiz.</p></div>
  <?php else: ?>
    <?php if ($fs['errors']): ?><div class="notice notice--err" role="alert"><?php foreach ($fs['errors'] as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div><?php endif; ?>
    <?= guard_fields('iletisim') ?>
    <div class="seg-radio" role="radiogroup" aria-label="Konu">
      <?php foreach (['genel' => 'Genel soru', 'kurumsal' => 'Kurumsal etkinlik', 'ozel' => 'Özel gün / grup'] as $k => $l): ?><label><input type="radio" name="topic" value="<?= $k ?>"<?= $o['topic'] === $k ? ' checked' : '' ?> data-topic><span><?= $l ?></span></label><?php endforeach; ?>
    </div>
    <div class="grid2">
      <label class="field">Ad soyad<input name="name" value="<?= e($o['name']) ?>" required maxlength="60" autocomplete="name"></label>
      <label class="field">E-posta<input type="email" name="email" value="<?= e($o['email']) ?>" required autocomplete="email"></label>
      <label class="field">Telefon <span class="opt-l">(isteğe bağlı)</span><input type="tel" name="phone" value="<?= e($o['phone']) ?>" autocomplete="tel"></label>
      <label class="field" data-corp>Kurum / şirket<input name="company" value="<?= e($o['company']) ?>" maxlength="80" autocomplete="organization"></label>
      <label class="field" data-corp>Tahmini kişi sayısı<input name="people" value="<?= e($o['people']) ?>" maxlength="20" inputmode="numeric"></label>
      <label class="field" data-corp>Tarih tercihi<input name="date" value="<?= e($o['date']) ?>" maxlength="60" placeholder="Ör. Kasım ortası, hafta içi"></label>
    </div>
    <label class="field">Mesajınız<textarea name="text" rows="5" required maxlength="3000"><?= e($o['text']) ?></textarea></label>
    <label class="consent"><input type="checkbox" name="kvkk" value="1" required><span>Bilgilerimin bu talebe dönüş yapmak için işlenmesini kabul ediyorum. <a href="/gizlilik/" target="_blank">Aydınlatma metni</a></span></label>
    <div class="form-card__foot"><button class="btn" type="submit">Gönder</button><p class="cform__msg" role="status" data-form-msg></p></div>
  <?php endif; ?>
</form>
