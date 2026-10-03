<?php
/** @var array $regs */ /** @var string $tok */
// Fatura takibi: ödemesi alınan kayıtlar, alıcı bilgileri ve kesilen faturaların numaraları.
$tab = in_array($_GET['d'] ?? '', ['kesildi', 'iptal'], true) ? $_GET['d'] : 'kesilecek';
$fm = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['ay'] ?? '')) ? $_GET['ay'] : '';
$paidRegs = array_filter($regs, fn($r) => (float) $r['total'] > 0 && (!empty($r['paid']) || trim((string) ($r['inv_no'] ?? '')) !== ''));
$lists = [
  'kesilecek' => array_filter($paidRegs, 'invoice_due'),
  'kesildi' => array_filter($paidRegs, fn($r) => trim((string) ($r['inv_no'] ?? '')) !== '' && $r['status'] !== 'iptal'),
  'iptal' => array_filter($paidRegs, fn($r) => trim((string) ($r['inv_no'] ?? '')) !== '' && $r['status'] === 'iptal'),
];
$rows = array_values(array_filter($lists[$tab], fn($r) => $fm === '' || str_starts_with((string) ($tab === 'kesildi' ? ($r['inv_date'] ?: $r['created']) : $r['created']), $fm)));
usort($rows, fn($a, $b) => strcmp($b['created'], $a['created']));
$sum = array_sum(array_map(fn($r) => (float) $r['total'], $rows));
[$net, $vat, $rate] = vat_split($sum);
$months = [];
foreach ($paidRegs as $r) $months[substr((string) (($r['inv_date'] ?? '') ?: $r['created']), 0, 7)] = true;
krsort($months);
$self = fn(array $q) => panel_url(['s' => 'faturalar', 'd' => $tab, 'ay' => $fm] + $q);
?>
<div class="bar">
  <h1>Faturalar</h1>
  <div class="bar__act">
    <a class="btn btn--ghost" href="<?= e(panel_url(['s' => 'csv', 'tur' => 'fatura', 'd' => $tab, 'ay' => $fm])) ?>">Excel (CSV) indir</a>
    <a class="btn btn--ghost" href="https://earsivportal.efatura.gov.tr/intragiris.html" target="_blank" rel="noopener">e-Arşiv portalı ↗</a>
  </div>
</div>
<p class="hint">Ödemesi alınan her ücretli kayıt burada "Kesilecek" listesine düşer. Faturayı e-Arşiv portalında ya da muhasebe programınızda kesin, sonra fatura numarasını buraya yazın. Tutarlar KDV dahildir; KDV oranı (%<?= e((string) $rate) ?>) Ayarlar'dan değiştirilebilir. Oranı mali müşavirinize teyit ettirin.</p>

<nav class="seg">
  <?php foreach (['kesilecek' => 'Kesilecek', 'kesildi' => 'Kesildi', 'iptal' => 'Kesildi, kayıt iptal'] as $k => $l): ?>
    <a href="<?= e(panel_url(['s' => 'faturalar', 'd' => $k, 'ay' => $fm])) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> (<?= count($lists[$k]) ?>)</a>
  <?php endforeach; ?>
</nav>

<form class="toolbar toolbar--filters" method="get">
  <input type="hidden" name="s" value="faturalar"><input type="hidden" name="d" value="<?= e($tab) ?>">
  <select name="ay" onchange="this.form.submit()"><option value="">Tüm aylar</option><?php foreach (array_keys($months) as $m): if ($m === '') continue; ?><option value="<?= e($m) ?>"<?= $fm === $m ? ' selected' : '' ?>><?= e(TR_MONTHS[(int) substr($m, 5, 2)] . ' ' . substr($m, 0, 4)) ?></option><?php endforeach; ?></select>
  <span class="hint"><?= count($rows) ?> kayıt · Toplam <?= e(money($sum)) ?> (matrah <?= e(money($net)) ?> + KDV <?= e(money($vat)) ?>)</span>
</form>

<?php if ($tab === 'iptal' && $rows): ?><p class="notice">Bu kayıtların faturası kesilmiş ama kayıt sonradan iptal edilmiş. Ücret iade edildiyse e-Arşiv portalında faturayı iptal edin ya da iade faturası düzenleyin.</p><?php endif; ?>

<section class="card card--flush">
  <?php if (!$rows): ?>
    <p class="hint" style="padding:18px"><?= $tab === 'kesilecek' ? 'Faturası kesilecek kayıt yok. Bir kaydın ödemesini "alındı" işaretlediğinizde burada görünür.' : 'Bu listede kayıt yok.' ?></p>
  <?php else: ?>
  <div class="tbl-wrap"><table class="tbl tbl--left">
    <thead><tr><th>Kayıt</th><th>Alıcı</th><th>Etkinlik</th><th>Tutar</th><th>Fatura</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $ev = event_by_id($r['event']); $corp = invoice_corporate($r); [$n, $v] = vat_split((float) $r['total']);
      $copy = $corp ? $r['invoice']['title'] . "\n" . $r['invoice']['tax_office'] . ' VD · ' . $r['invoice']['tax_no'] . "\n" . $r['invoice']['address'] : $r['name'];
      $item = ($ev['title'] ?? 'Etkinlik') . ' katılım bedeli (' . $r['ticket_name'] . ' × ' . (int) $r['qty'] . ')'; ?>
      <tr>
        <td><a href="./?s=kayit&amp;id=<?= e(urlencode($r['id'])) ?>"><strong class="mono-s"><?= e($r['code']) ?></strong></a><br><small class="muted"><?= e(tr_datetime($r['created'])) ?></small></td>
        <td>
          <?= $corp ? pill('Kurumsal', 'warn') : pill('Bireysel') ?>
          <strong><?= e($corp ? $r['invoice']['title'] : $r['name']) ?></strong>
          <?php if ($corp): ?><br><small><?= e($r['invoice']['tax_office']) ?> VD · <?= e($r['invoice']['tax_no']) ?></small><br><small class="muted"><?= e($r['invoice']['address']) ?></small><?php else: ?><br><small class="muted"><?= e($r['email']) ?></small><?php endif; ?>
          <br><button type="button" class="btn btn--ghost btn--sm" data-copy="<?= e($copy) ?>">Alıcıyı kopyala</button>
        </td>
        <td><?= e($ev['title'] ?? 'Silinmiş') ?><br><small class="muted"><?= e($r['ticket_name']) ?> × <?= (int) $r['qty'] ?></small><br><button type="button" class="btn btn--ghost btn--sm" data-copy="<?= e($item) ?>">Hizmet adını kopyala</button></td>
        <td><strong><?= e(money($r['total'])) ?></strong><br><small class="muted">Matrah <?= e(money($n)) ?> · KDV <?= e(money($v)) ?></small></td>
        <td>
          <form method="post" class="inv-form">
            <?= hidden($tok, 'fatura', ['id' => $r['id'], 'back' => $self([])]) ?>
            <input name="inv_no" value="<?= e($r['inv_no'] ?? '') ?>" placeholder="Fatura no" maxlength="40" aria-label="Fatura numarası">
            <input type="date" name="inv_date" value="<?= e(($r['inv_date'] ?? '') ?: date('Y-m-d')) ?>" aria-label="Fatura tarihi">
            <button class="btn btn--sm"><?= trim((string) ($r['inv_no'] ?? '')) === '' ? 'Kesildi' : 'Güncelle' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
