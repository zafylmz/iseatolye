<?php
// Katılım kaydı (bilet) sayfası: kod, durum, ödeme bilgisi, iptal.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$r = reg_by_code((string) ($_GET['kod'] ?? ''));
$me = current_user();
$keyOk = $r && hash_equals(reg_key($r), (string) ($_GET['k'] ?? ''));
if (!$r || (!$keyOk && (!$me || ($r['user'] ?? '') !== $me['id']))) {
  if ($r && !$me && ($r['user'] ?? '') !== '') require_user();
  http_response_code(404); include __DIR__ . '/404.php'; exit;
}
$ev = event_by_id($r['event']);
if (!$ev) { http_response_code(404); include __DIR__ . '/404.php'; exit; }
$sessions = is_package($ev) ? event_sessions($ev, false) : array_filter([session_by_id($ev, $r['session'])]);
$s = reset($sessions) ?: null;
$v = venue($s['venue'] ?? '');
$new = !empty($_GET['yeni']);
$flash = $me ? member_flash() : null;
$canCancel = $r['status'] !== 'iptal' && $s && session_ts($s) > time();
$st = $r['status'];
$title = 'Kaydım · ' . $r['code'] . ' · ' . $c['brand']['name'];
$noindex = true;
$tok = member_csrf();
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap ticket-page">
    <a class="back" href="<?= $me ? '/hesabim/' : e(event_url($ev)) ?>"><?= icon('sol') ?><?= $me ? 'Etkinliklerim' : 'Etkinliğe dön' ?></a>
    <?php if ($new): ?>
      <div class="done" role="status">
        <span class="done__ico"><?= icon($st === 'yedek' ? 'saat' : 'onay') ?></span>
        <h1 class="h2"><?= $st === 'yedek' ? 'Yedek listeye alındınız' : ($st === 'onayli' ? 'Kaydınız onaylandı' : 'Kaydınızı aldık') ?></h1>
        <p class="muted"><?= $st === 'yedek' ? 'Yer açılırsa size e-posta ile haber vereceğiz.' : ($st === 'beklemede' ? (!$r['paid'] && $r['total'] > 0 && $r['method'] !== 'yerinde' ? 'Ödemeniz onaylandığında kaydınız kesinleşecek. Yeriniz şimdiden ayrıldı.' : 'Onaylandığında size bilgi vereceğiz. Yeriniz şimdiden ayrıldı.') : e((string) setting('reg_success_note', ''))) ?></p>
      </div>
    <?php endif; ?>
    <?php if ($flash): ?><p class="notice notice--<?= e($flash[1]) ?>"><?= e($flash[0]) ?></p><?php endif; ?>

    <div class="ticket">
      <div class="ticket__main">
        <p class="label">Katılım kaydı</p>
        <h2 class="h2"><a href="<?= e(event_url($ev)) ?>"><?= e($ev['title']) ?></a></h2>
        <ul class="ticket__meta">
          <?php foreach ($sessions as $x): ?><li><?= icon('takvim') ?><?= e(session_when($x)) ?><?= is_package($ev) && trim($x['note'] ?? '') !== '' ? ' · ' . e($x['note']) : '' ?></li><?php endforeach; ?>
          <li><?= icon('konum') ?><?= e(session_place($s)) ?><?php if ($m = map_url($v)): ?> · <a href="<?= e($m) ?>" target="_blank" rel="noopener">Harita</a><?php endif; ?></li>
          <li><?= icon('bilet') ?><?= e($r['ticket_name']) ?> × <?= (int) $r['qty'] ?><?= $r['total'] > 0 ? ' · ' . money($r['total']) : ' · Ücretsiz' ?></li>
          <li><?= icon('kisi') ?><?= e($r['name']) ?><?= trim($r['others'] ?? '') !== '' ? ' · ' . e(str_replace("\n", ', ', $r['others'])) : '' ?></li>
        </ul>
      </div>
      <div class="ticket__stub">
        <span class="label">Kod</span>
        <strong class="ticket__code"><?= e($r['code']) ?></strong>
        <span class="pill pill--<?= e($st) ?>"><?= e(REG_STATUS[$st] ?? $st) ?></span>
        <?php if ($r['total'] > 0 && $st !== 'iptal'): ?><span class="small muted"><?= $r['paid'] ? 'Ödeme alındı' : 'Ödeme bekleniyor' ?></span><?php endif; ?>
      </div>
    </div>

    <?php if ($st !== 'iptal' && $st !== 'yedek' && !$r['paid'] && $r['total'] > 0): ?>
      <section class="pay">
        <h2 class="h3">Ödeme</h2>
        <?php if ($r['method'] === 'havale'): ?>
          <?php if (trim((string) setting('bank_iban', '')) !== ''): ?>
            <dl class="pay__rows">
              <?php if (trim((string) setting('bank_name', '')) !== ''): ?><div><dt>Banka</dt><dd><?= e(setting('bank_name')) ?></dd></div><?php endif; ?>
              <div><dt>Alıcı</dt><dd><?= e(setting('bank_holder', '')) ?></dd></div>
              <div><dt>IBAN</dt><dd><code><?= e(setting('bank_iban')) ?></code> <button type="button" class="copy" data-copy="<?= e(preg_replace('/\s+/', '', (string) setting('bank_iban'))) ?>">Kopyala</button></dd></div>
              <div><dt>Tutar</dt><dd><?= money($r['total']) ?></dd></div>
              <div><dt>Açıklama</dt><dd><code><?= e($r['code']) ?></code></dd></div>
            </dl>
            <p class="muted small"><?= e((string) setting('payment_note', '')) ?></p>
            <?php if (($hh = (int) setting('hold_hours', 48)) > 0): ?><p class="muted small">Ödemeniz kayıttan sonraki <?= $hh ?> saat içinde ulaşmazsa yeriniz başka katılımcılara açılabilir.</p><?php endif; ?>
          <?php else: ?>
            <p class="muted">Hesap bilgilerini size WhatsApp ya da e-posta ile ileteceğiz.</p>
          <?php endif; ?>
        <?php elseif ($r['method'] === 'link' && trim($ev['pay_link'] ?? '') !== ''): ?>
          <p class="muted">Ödemenizi güvenli ödeme sayfasından yapabilirsiniz. Açıklamaya katılım kodunuzu yazın.</p>
          <a class="btn" href="<?= e($ev['pay_link']) ?>" target="_blank" rel="noopener">Ödeme sayfasına git<?= icon('dis') ?></a>
        <?php elseif ($r['method'] === 'yerinde'): ?>
          <p class="muted">Ücreti etkinlik günü nakit ya da kartla ödeyebilirsiniz.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <div class="ticket-page__act">
      <?php if ($st !== 'iptal' && $s && session_ts($s, true) > time()): ?>
        <a class="btn btn--ghost" href="<?= e(event_url($ev) . 'takvim.ics?o=' . rawurlencode($s['id'])) ?>"><?= icon('indir') ?>Takvime ekle</a>
        <a class="btn btn--ghost" href="<?= e(gcal_url($ev, $s)) ?>" target="_blank" rel="noopener"><?= icon('takvim') ?>Google Takvim</a>
      <?php endif; ?>
      <button class="btn btn--ghost" type="button" onclick="window.print()">Yazdır</button>
      <?php if (trim($c['contact']['whatsapp'] ?? '') !== ''): ?><a class="btn btn--ghost" href="<?= e(wa_href($c['contact']['whatsapp'], 'Merhaba, ' . $r['code'] . ' kodlu kaydım hakkında yazıyorum.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?>Soru sor</a><?php endif; ?>
    </div>

    <?php if ($canCancel): ?>
      <details class="cancel">
        <summary>Kaydımı iptal etmek istiyorum</summary>
        <?php $pol = trim($ev['cancel_policy'] ?? '') ?: (string) setting('cancel_policy', ''); if ($pol !== ''): ?><p class="muted small"><?= e($pol) ?></p><?php endif; ?>
        <form method="post" action="/uye-api.php" onsubmit="return confirm('Kaydınız iptal edilecek. Emin misiniz?')">
          <input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="bilet-iptal"><input type="hidden" name="code" value="<?= e($r['code']) ?>"><input type="hidden" name="k" value="<?= e(reg_key($r)) ?>">
          <button class="btn btn--danger btn--sm">Kaydı iptal et</button>
        </form>
      </details>
    <?php endif; ?>
    <?php if (!$me && ($r['user'] ?? '') === ''): ?><p class="muted small">Bu sayfanın adresini saklayın; kaydınızı buradan görebilirsiniz. Bir sonraki sefer için <a href="/uye-ol/">üye olabilirsiniz</a>.</p><?php endif; ?>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
