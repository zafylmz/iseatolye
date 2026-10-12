<?php
/** @var string $tok */ /** @var array $orders */
$id = (string) ($_GET['id'] ?? '');
$o = null;
foreach ($orders as $x) if ($x['id'] === $id) $o = $x;
if (!$o): ?>
  <section class="card"><p class="hint">Sipariş bulunamadı. <a href="./?s=siparisler">Siparişlere dönün.</a></p></section>
<?php return; endif;
$p = product_by_id($o['product']);
$u = ($o['user'] ?? '') !== '' ? user_by_id($o['user']) : null;
$iv = $o['invoice'] ?? [];
$back = './?s=siparisler';
?>
<div class="bar">
  <div><a class="back" href="<?= e($back) ?>">← Siparişler</a><h1><?= e($o['name']) ?> <span class="mono-s"><?= e($o['code']) ?></span></h1></div>
  <div class="bar__act"><a class="btn btn--ghost" href="<?= e(order_url($o, true)) ?>" target="_blank" rel="noopener">Sipariş sayfası ↗</a></div>
</div>

<div class="grid2 grid2--top grid2--wide">
  <form method="post" class="card">
    <?= hidden($tok, 'siparis', ['id' => $o['id']]) ?>
    <h2>Sipariş</h2>
    <div class="grid2">
      <label>Durum<select name="status"><?php foreach (ORDER_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $o['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label id="kargo">Kargo takip no ya da bağlantısı<input name="cargo" value="<?= e($o['cargo'] ?? '') ?>" maxlength="200" placeholder="Ör. Yurtiçi 123456789"></label>
      <label>Ad soyad<input name="name" value="<?= e($o['name']) ?>"></label>
      <label>Telefon<input name="phone" value="<?= e($o['phone']) ?>"></label>
      <?php if (($o['user'] ?? '') === ''): ?><label>E-posta<input type="email" name="email" value="<?= e($o['email']) ?>"></label><?php endif; ?>
    </div>
    <?php if ($o['delivery'] === 'kargo'): ?><label>Teslimat adresi<textarea name="address" rows="2"><?= e($o['address']) ?></textarea></label><?php endif; ?>
    <div class="checks"><label class="check"><input type="checkbox" name="paid" value="1"<?= !empty($o['paid']) ? ' checked' : '' ?>> Ödeme alındı</label></div>
    <fieldset class="fieldset">
      <legend>Fatura <?= trim((string) ($o['inv_no'] ?? '')) !== '' ? pill('Kesildi', 'on') : (!empty($o['paid']) ? pill('Kesilecek', 'warn') : pill('Ödeme bekleniyor')) ?></legend>
      <div class="grid2">
        <label>Fatura no<input name="inv_no" value="<?= e($o['inv_no'] ?? '') ?>" maxlength="40"></label>
        <label>Fatura tarihi<input type="date" name="inv_date" value="<?= e(($o['inv_date'] ?? '') ?: date('Y-m-d')) ?>"></label>
        <label>Şirket unvanı <span class="opt">(boşsa bireysel: <?= e($o['name']) ?>)</span><input name="inv_title" value="<?= e($iv['title'] ?? '') ?>"></label>
        <label>Vergi dairesi<input name="inv_tax_office" value="<?= e($iv['tax_office'] ?? '') ?>"></label>
        <label>Vergi no / T.C. kimlik no<input name="inv_tax_no" value="<?= e($iv['tax_no'] ?? '') ?>"></label>
        <label>Fatura adresi<input name="inv_address" value="<?= e($iv['address'] ?? '') ?>"></label>
      </div>
    </fieldset>
    <label>Panel notu <span class="opt">(müşteri görmez)</span><textarea name="admin_note" rows="3"><?= e($o['admin_note'] ?? '') ?></textarea></label>
    <label class="check"><input type="checkbox" name="notify" value="1" checked> Ödeme ya da durum değişirse müşteriye e-posta gönder (kargo takip no e-postada yer alır)</label>
    <div><button class="btn">Kaydet</button></div>
  </form>

  <div>
    <section class="card">
      <h2>Bilgiler</h2>
      <dl class="dl">
        <div><dt>Ürün</dt><dd><?= $p ? '<a href="./?s=urun&amp;id=' . e(urlencode($p['id'])) . '">' . e($o['product_name']) . '</a>' : e($o['product_name']) . ' (silinmiş)' ?></dd></div>
        <div><dt>Adet</dt><dd><?= (int) $o['qty'] ?> × <?= e(money($o['unit_price'])) ?> = <?= e(money($o['subtotal'])) ?></dd></div>
        <?php if ((float) ($o['discount'] ?? 0) > 0): ?><div><dt>İndirim</dt><dd><?= e($o['discount_code']) ?> (%<?= (float) $o['discount_pct'] ?>): -<?= e(money($o['discount'])) ?></dd></div><?php endif; ?>
        <div><dt>Teslimat</dt><dd><?= e(DELIVERY[$o['delivery']] ?? '') ?><?= $o['delivery'] === 'kargo' ? ' · ' . ((float) $o['shipping'] > 0 ? e(money($o['shipping'])) : 'ücretsiz') : '' ?></dd></div>
        <div><dt>Toplam</dt><dd><b><?= e(money($o['total'])) ?></b> · <?= e(ORDER_PAY_METHODS[$o['method']] ?? $o['method']) ?></dd></div>
        <?php if (trim((string) ($o['pay_ref'] ?? '')) !== ''): ?><div><dt>Kart ödemesi no</dt><dd><code><?= e($o['pay_ref']) ?></code></dd></div><?php endif; ?>
        <div><dt>Stok</dt><dd><?= !empty($o['stock_taken']) ? 'Stoktan düşüldü' : ($o['status'] === 'iptal' ? 'Ayrılmıyor (iptal)' : (order_holds($o) ? 'Ödeme bekleniyor, ürün ayrıldı' : 'Ödeme süresi doldu, ürün ayrılmıyor')) ?></dd></div>
        <div><dt>E-posta</dt><dd><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a></dd></div>
        <div><dt>Telefon</dt><dd><a href="<?= e(wa_href($o['phone'], 'Merhaba ' . $o['name'] . ', ' . $o['code'] . ' kodlu siparişiniz hakkında yazıyorum.')) ?>" target="_blank" rel="noopener"><?= e($o['phone']) ?> (WhatsApp)</a></dd></div>
        <div><dt>Üyelik</dt><dd><?= $u ? '<a href="./?s=uye&amp;id=' . e(urlencode($u['id'])) . '">' . e($u['name']) . '</a>' : 'Üye değil' ?></dd></div>
        <div><dt>Oluşturma</dt><dd><?= e(tr_datetime($o['created'])) ?></dd></div>
        <div><dt>Güncelleme</dt><dd><?= e(tr_datetime($o['updated'] ?? $o['created'])) ?></dd></div>
      </dl>
      <?php if (trim($o['note'] ?? '') !== ''): ?><p class="quote-note"><b>Müşterinin notu:</b> <?= e($o['note']) ?></p><?php endif; ?>
    </section>
    <section class="card card--danger">
      <form method="post" data-confirm="Bu sipariş kalıcı olarak silinecek. Vazgeçilen bir sipariş için durumunu &quot;İptal edildi&quot; yapmanız genelde yeterlidir. Emin misiniz?"><?= hidden($tok, 'siparis-sil', ['id' => $o['id']]) ?><button class="btn btn--danger">Siparişi sil</button></form>
      <p class="hint">Silmek siparişi geçmişten de kaldırır. Vazgeçilen sipariş için "İptal edildi" seçin; ürün stoğa geri eklenir.</p>
    </section>
  </div>
</div>
