<?php
// Mağaza sipariş sayfası (/siparis/KOD/?k=...): durum, ödeme bilgisi, kargo takibi.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/shop.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/paytr.php';
$c = content();
$o = order_by_code((string) ($_GET['kod'] ?? ''));
$me = current_user();
$keyOk = $o && hash_equals(order_key($o), (string) ($_GET['k'] ?? ''));
if (!$o || (!$keyOk && (!$me || ($o['user'] ?? '') !== $me['id']))) {
  if ($o && !$me && ($o['user'] ?? '') !== '') require_user();
  http_response_code(404); include __DIR__ . '/404.php'; exit;
}
$p = product_by_id($o['product']);
$st = $o['status'];
$new = !empty($_GET['yeni']);
$unpaid = $st !== 'iptal' && empty($o['paid']);
$title = 'Siparişim · ' . $o['code'] . ' · ' . $c['brand']['name'];
$noindex = true;
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap ticket-page">
    <a class="back" href="/magaza/"><?= icon('sol') ?>Mağaza</a>
    <?php if ($new): ?>
      <div class="done" role="status">
        <span class="done__ico"><?= icon('onay') ?></span>
        <h1 class="h2">Siparişinizi aldık</h1>
        <p class="muted"><?= $unpaid ? 'Ödemeniz ulaşınca siparişinizi hazırlamaya başlayacağız. Ürününüz şimdiden ayrıldı.' : 'Siparişinizi hazırlamaya başlıyoruz.' ?> Sipariş bilgileriniz e-posta adresinize de gönderildi.</p>
      </div>
    <?php endif; ?>
    <?php $pm = (string) ($_GET['odeme'] ?? ''); if ($pm === 'ok' && !empty($o['paid'])): ?><p class="notice notice--ok" role="status"><?= icon('onay') ?>Ödemeniz alındı, siparişinizi hazırlamaya başlıyoruz. Teşekkür ederiz!</p>
    <?php elseif ($pm === 'ok'): ?><p class="notice notice--ok" role="status"><?= icon('bilgi') ?>Ödemeniz işleniyor. Onay birkaç saniye içinde gelir; sayfayı yenileyerek kontrol edebilirsiniz. Onaylanınca size e-posta da göndereceğiz.</p>
    <?php elseif ($pm === 'hata' && $unpaid): ?><p class="notice notice--err" role="alert"><?= icon('bilgi') ?>Ödeme tamamlanamadı, kartınızdan ücret alınmadı. Aşağıdan tekrar deneyebilirsiniz.</p>
    <?php elseif ($pm === 'tukendi' && $unpaid): ?><p class="notice notice--err" role="alert"><?= icon('bilgi') ?>Ödeme süresi dolduğu için ürün başka siparişlere açıldı ve stokta kalmadı. Kartınızdan ücret alınmadı.</p>
    <?php elseif ($pm === 'kapali' && $unpaid): ?><p class="notice notice--warn" role="alert"><?= icon('bilgi') ?>Kartla ödeme şu an kullanılamıyor. Lütfen biraz sonra tekrar deneyin<?= trim((string) setting('bank_iban', '')) !== '' ? ' ya da havale ile ödeyin' : '' ?>.</p><?php endif; ?>

    <div class="ticket">
      <div class="ticket__main">
        <p class="label">Sipariş</p>
        <h2 class="h2"><?php if ($p && product_visible($p)): ?><a href="<?= e(product_url($p)) ?>"><?= e($o['product_name']) ?></a><?php else: ?><?= e($o['product_name']) ?><?php endif; ?></h2>
        <ul class="ticket__meta">
          <li><?= icon('canta') ?><?= (int) $o['qty'] ?> adet × <?= money($o['unit_price']) ?></li>
          <?php if ((float) ($o['discount'] ?? 0) > 0): ?><li><?= icon('onay') ?>İndirim kodu <?= e($o['discount_code']) ?> (%<?= (float) $o['discount_pct'] ?>): -<?= money($o['discount']) ?></li><?php endif; ?>
          <li><?= icon('kargo') ?><?= e(DELIVERY[$o['delivery']] ?? '') ?><?= $o['delivery'] === 'kargo' ? ' · ' . ((float) $o['shipping'] > 0 ? money($o['shipping']) : 'ücretsiz') : '' ?></li>
          <?php if ($o['delivery'] === 'kargo'): ?><li><?= icon('konum') ?><?= nl2br(e($o['address'])) ?></li><?php endif; ?>
          <li><?= icon('kisi') ?><?= e($o['name']) ?> · <?= e($o['phone']) ?></li>
          <?php if (trim((string) ($o['cargo'] ?? '')) !== ''): ?><li><?= icon('kargo') ?>Kargo takip: <?= preg_match('#^https?://#i', $o['cargo']) ? '<a href="' . e($o['cargo']) . '" target="_blank" rel="noopener">Kargoyu takip et</a>' : '<b>' . e($o['cargo']) . '</b>' ?></li><?php endif; ?>
        </ul>
      </div>
      <div class="ticket__stub">
        <span class="label">Kod</span>
        <strong class="ticket__code"><?= e($o['code']) ?></strong>
        <span class="pill pill--<?= e(['hazirlaniyor' => 'onayli', 'kargoda' => 'onayli', 'teslim' => 'onayli'][$st] ?? $st) ?>"><?= e(ORDER_STATUS[$st] ?? $st) ?></span>
        <span class="small muted"><?= money($o['total']) ?> · <?= !empty($o['paid']) ? 'Ödeme alındı' : ($st === 'iptal' ? 'İptal' : 'Ödeme bekleniyor') ?></span>
      </div>
    </div>

    <?php if ($unpaid): ?>
      <section class="pay">
        <h2 class="h3">Ödeme</h2>
        <?php if (paytr_on()): ?>
          <div class="pay__card">
            <p class="muted"><?= $o['method'] === 'kart' ? 'Kartla ödemeniz henüz tamamlanmadı.' : 'İsterseniz ücreti hemen kartınızla da ödeyebilirsiniz.' ?> Ödeme PayTR güvenli ödeme sayfasında yapılır.</p>
            <a class="btn" href="<?= e(order_pay_url($o)) ?>">Kartla öde · <?= money($o['total']) ?></a>
          </div>
        <?php endif; ?>
        <?php if ($o['method'] === 'havale' && trim((string) setting('bank_iban', '')) !== ''): ?>
          <dl class="pay__rows">
            <?php if (trim((string) setting('bank_name', '')) !== ''): ?><div><dt>Banka</dt><dd><?= e(setting('bank_name')) ?></dd></div><?php endif; ?>
            <div><dt>Alıcı</dt><dd><?= e(setting('bank_holder', '')) ?></dd></div>
            <div><dt>IBAN</dt><dd><code><?= e(setting('bank_iban')) ?></code> <button type="button" class="copy" data-copy="<?= e(preg_replace('/\s+/', '', (string) setting('bank_iban'))) ?>">Kopyala</button></dd></div>
            <div><dt>Tutar</dt><dd><?= money($o['total']) ?></dd></div>
            <div><dt>Açıklama</dt><dd><code><?= e($o['code']) ?></code></dd></div>
          </dl>
          <p class="muted small">Havale açıklamasına sipariş kodunuzu (<?= e($o['code']) ?>) yazın. Ödemeniz ulaşınca siparişinizi hazırlamaya başlar, size e-posta ile haber veririz.</p>
          <?php if (($hh = (int) setting('hold_hours', 48)) > 0): ?><p class="muted small">Ödemeniz siparişten sonraki <?= $hh ?> saat içinde ulaşmazsa ürün başka siparişlere açılabilir.</p><?php endif; ?>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <div class="ticket-page__act">
      <button class="btn btn--ghost" type="button" onclick="window.print()">Yazdır</button>
      <?php if (trim($c['contact']['whatsapp'] ?? '') !== ''): ?><a class="btn btn--ghost" href="<?= e(wa_href($c['contact']['whatsapp'], 'Merhaba, ' . $o['code'] . ' kodlu siparişim hakkında yazıyorum.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?>Soru sor</a><?php endif; ?>
    </div>
    <p class="muted small">Bu sayfanın adresini saklayın; siparişinizin durumunu buradan görebilirsiniz. Teslimattan sonraki 14 gün içinde cayma hakkınızı kullanmak için bize yazmanız yeterli (<a href="/mesafeli-satis/#urun-satisi">ayrıntılar</a>).</p>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
