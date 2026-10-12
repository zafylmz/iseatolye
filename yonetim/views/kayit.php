<?php
/** @var array $regs */ /** @var string $tok */
$id = (string) ($_GET['id'] ?? '');
$r = null;
foreach ($regs as $x) if ($x['id'] === $id) $r = $x;
if (!$r): ?>
  <section class="card"><p class="hint">Kayıt bulunamadı. <a href="./?s=katilimlar">Katılımlara dönün.</a></p></section>
<?php return; endif;
$ev = event_by_id($r['event']);
$u = $r['user'] ? user_by_id($r['user']) : null;
$back = panel_url(['s' => 'katilimlar', 'etkinlik' => $r['event']]);
?>
<div class="bar">
  <div><a class="back" href="<?= e($back) ?>">← <?= e($ev['title'] ?? 'Katılımlar') ?></a><h1><?= e($r['name']) ?> <span class="mono-s"><?= e($r['code']) ?></span></h1></div>
  <div class="bar__act"><a class="btn btn--ghost" href="<?= e(ticket_url($r, true)) ?>" target="_blank" rel="noopener">Bilet sayfası ↗</a></div>
</div>

<div class="grid2 grid2--top grid2--wide">
  <form method="post" class="card">
    <?= hidden($tok, 'kayit', ['id' => $r['id']]) ?>
    <h2>Kayıt</h2>
    <div class="grid2">
      <label>Durum<select name="status"><?php foreach (REG_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <?php if ($ev && !is_package($ev)): ?>
        <label>Tarih<select name="session"><?php foreach (event_sessions($ev) as $s): ?><option value="<?= e($s['id']) ?>"<?= $r['session'] === $s['id'] ? ' selected' : '' ?>><?= e(session_when($s, false) . ' · ' . session_place($s, false)) ?></option><?php endforeach; ?></select></label>
      <?php endif; ?>
      <label>Adet (<?= e($r['ticket_name']) ?>)<input type="number" name="qty" value="<?= (int) $r['qty'] ?>" min="1" max="50"></label>
      <label>Ad soyad<input name="name" value="<?= e($r['name']) ?>"></label>
      <label>Telefon<input name="phone" value="<?= e($r['phone']) ?>"></label>
      <?php if (!$r['user']): ?><label>E-posta<input type="email" name="email" value="<?= e($r['email']) ?>"></label><?php endif; ?>
    </div>
    <label>Gelecek diğer kişiler<input name="others" value="<?= e($r['others'] ?? '') ?>"></label>
    <div class="checks">
      <label class="check"><input type="checkbox" name="paid" value="1"<?= $r['paid'] ? ' checked' : '' ?>> Ödeme alındı</label>
      <label class="check"><input type="checkbox" name="checked_in" value="1"<?= $r['checked_in'] ? ' checked' : '' ?>> Etkinliğe geldi</label>
    </div>
    <?php if ((float) $r['total'] > 0): $iv = $r['invoice'] ?? []; ?>
      <fieldset class="fieldset">
        <legend>Fatura <?= trim((string) ($r['inv_no'] ?? '')) !== '' ? pill('Kesildi', 'on') : ($r['paid'] ? pill('Kesilecek', 'warn') : pill('Ödeme bekleniyor')) ?></legend>
        <div class="grid2">
          <label>Fatura no<input name="inv_no" value="<?= e($r['inv_no'] ?? '') ?>" maxlength="40"></label>
          <label>Fatura tarihi<input type="date" name="inv_date" value="<?= e(($r['inv_date'] ?? '') ?: date('Y-m-d')) ?>"></label>
          <label>Şirket unvanı <span class="opt">(boşsa bireysel: <?= e($r['name']) ?>)</span><input name="inv_title" value="<?= e($iv['title'] ?? '') ?>"></label>
          <label>Vergi dairesi<input name="inv_tax_office" value="<?= e($iv['tax_office'] ?? '') ?>"></label>
          <label>Vergi no / T.C. kimlik no<input name="inv_tax_no" value="<?= e($iv['tax_no'] ?? '') ?>"></label>
          <label>Fatura adresi<input name="inv_address" value="<?= e($iv['address'] ?? '') ?>"></label>
        </div>
      </fieldset>
    <?php endif; ?>
    <label>Panel notu <span class="opt">(katılımcı görmez)</span><textarea name="admin_note" rows="3"><?= e($r['admin_note'] ?? '') ?></textarea></label>
    <label class="check"><input type="checkbox" name="notify" value="1" checked> Durum, ödeme ya da tarih değişirse kişiye e-posta gönder</label>
    <div><button class="btn">Kaydet</button></div>
  </form>

  <div>
    <section class="card">
      <h2>Bilgiler</h2>
      <dl class="dl">
        <div><dt>Etkinlik</dt><dd><?= $ev ? '<a href="./?s=etkinlik&amp;id=' . e(urlencode($ev['id'])) . '">' . e($ev['title']) . '</a>' : 'Silinmiş' ?></dd></div>
        <div><dt>Tarih</dt><dd><?= e(reg_session_label($r, $ev)) ?></dd></div>
        <div><dt>Bilet</dt><dd><?= e($r['ticket_name']) ?> × <?= (int) $r['qty'] ?> · <?= (int) $r['seats'] ?> kişi</dd></div>
        <div><dt>Tutar</dt><dd><?= $r['total'] > 0 ? e(money($r['total'])) . ' · ' . e(PAY_METHODS[$r['method']] ?? 'Yöntem seçilmedi') : 'Ücretsiz' ?></dd></div>
        <?php if (trim((string) ($r['pay_ref'] ?? '')) !== ''): ?><div><dt>Kart ödemesi no</dt><dd><code><?= e($r['pay_ref']) ?></code></dd></div><?php endif; ?>
        <div><dt>E-posta</dt><dd><?= $r['email'] !== '' ? '<a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>' : '–' ?></dd></div>
        <div><dt>Telefon</dt><dd><?= $r['phone'] !== '' ? '<a href="' . e(wa_href($r['phone'], 'Merhaba ' . $r['name'] . ', ' . ($ev['title'] ?? '') . ' kaydınız hakkında yazıyorum.')) . '" target="_blank" rel="noopener">' . e($r['phone']) . ' (WhatsApp)</a>' : '–' ?></dd></div>
        <div><dt>Üyelik</dt><dd><?= $u ? '<a href="./?s=uye&amp;id=' . e(urlencode($u['id'])) . '">' . e($u['name']) . '</a>' : 'Üye değil' ?></dd></div>
        <div><dt>Oluşturma</dt><dd><?= e(tr_datetime($r['created'])) ?> · <?= ($r['source'] ?? '') === 'panel' ? 'panelden' : 'siteden' ?></dd></div>
        <div><dt>Güncelleme</dt><dd><?= e(tr_datetime($r['updated'] ?? $r['created'])) ?></dd></div>
      </dl>
      <?php if (trim($r['note'] ?? '') !== ''): ?><p class="quote-note"><b>Katılımcının notu:</b> <?= e($r['note']) ?></p><?php endif; ?>
    </section>
    <section class="card card--danger">
      <form method="post" data-confirm="Bu kayıt kalıcı olarak silinecek. Yer açmak için iptal etmeniz genelde yeterlidir. Emin misiniz?"><?= hidden($tok, 'kayit-sil', ['id' => $r['id'], 'back' => $back]) ?><button class="btn btn--danger">Kaydı sil</button></form>
      <p class="hint">Silmek kaydı geçmişten de kaldırır. Gelmeyecek biri için "İptal edildi" seçin.</p>
    </section>
  </div>
</div>
