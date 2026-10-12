<?php
/** @var string $tok */ /** @var array $orders */
$fp = (string) ($_GET['urun'] ?? '');
$fst = (string) ($_GET['durum'] ?? '');
$fq = trim((string) ($_GET['q'] ?? ''));
$finv = !empty($_GET['fatura']);
$prod = $fp !== '' ? product_by_id($fp) : null;
if (!$prod && !array_filter($orders, fn($o) => ($o['product'] ?? '') === $fp)) $fp = '';
$self = panel_url(['s' => 'siparisler', 'urun' => $fp, 'durum' => $fst, 'q' => $fq, 'fatura' => $finv ? '1' : '']);
$rows = array_values(array_filter($orders, function ($o) use ($fp, $fst, $fq, $finv) {
  if ($fp !== '' && ($o['product'] ?? '') !== $fp) return false;
  if ($fst !== '' && $o['status'] !== $fst) return false;
  if ($finv && !(!empty($o['paid']) && $o['status'] !== 'iptal' && trim((string) ($o['inv_no'] ?? '')) === '')) return false;
  if ($fq !== '' && !str_contains(mb_strtolower($o['name'] . ' ' . $o['email'] . ' ' . $o['phone'] . ' ' . $o['code'] . ' ' . $o['product_name']), mb_strtolower($fq))) return false;
  return true;
}));
usort($rows, fn($a, $b) => strcmp($b['created'], $a['created']));
$counts = array_count_values(array_column($orders, 'status'));
$invDue = count(array_filter($orders, fn($o) => !empty($o['paid']) && $o['status'] !== 'iptal' && trim((string) ($o['inv_no'] ?? '')) === ''));
$paidSum = array_sum(array_map(fn($o) => !empty($o['paid']) && $o['status'] !== 'iptal' ? (float) $o['total'] : 0, $rows));
?>
<div class="bar"><h1><?= $prod ? e($prod['name']) . ' siparişleri' : 'Siparişler' ?></h1><a class="btn btn--ghost" href="./?s=urunler">Ürünler</a></div>

<nav class="seg" aria-label="Durum">
  <a href="<?= e(panel_url(['s' => 'siparisler', 'urun' => $fp, 'q' => $fq])) ?>"<?= $fst === '' && !$finv ? ' aria-current="page"' : '' ?>>Tümü <small><?= count($orders) ?></small></a>
  <?php foreach (ORDER_STATUS as $k => $l): ?><a href="<?= e(panel_url(['s' => 'siparisler', 'urun' => $fp, 'q' => $fq, 'durum' => $k])) ?>"<?= $fst === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> <small><?= (int) ($counts[$k] ?? 0) ?></small></a><?php endforeach; ?>
  <a href="<?= e(panel_url(['s' => 'siparisler', 'urun' => $fp, 'q' => $fq, 'fatura' => '1'])) ?>"<?= $finv ? ' aria-current="page"' : '' ?>>Fatura kesilecek <small><?= $invDue ?></small></a>
</nav>
<form class="toolbar toolbar--filters" method="get">
  <input type="hidden" name="s" value="siparisler"><input type="hidden" name="durum" value="<?= e($fst) ?>"><?php if ($finv): ?><input type="hidden" name="fatura" value="1"><?php endif; ?>
  <select name="urun" onchange="this.form.submit()"><option value="">Tüm ürünler</option><?php foreach (products_all() as $x): ?><option value="<?= e($x['id']) ?>"<?= $fp === $x['id'] ? ' selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select>
  <input type="search" name="q" value="<?= e($fq) ?>" placeholder="Ad, e-posta, telefon, kod">
  <button class="btn btn--ghost">Filtrele</button>
</form>

<section class="card card--flush">
  <div class="card__head card__head--pad"><h2><?= count($rows) ?> sipariş</h2><span class="hint">Tahsil edilen <b><?= e(money($paidSum)) ?></b></span></div>
  <?php if (!$rows): ?><p class="hint pad">Bu filtreye uyan sipariş yok.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl tbl--left">
    <thead><tr><th>Kişi</th><th>Ürün</th><th>Teslimat</th><th>Durum</th><th>Ödeme</th><th>İşlem</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $o):
        $quick = function (string $do, string $label, string $cls = 'btn--ghost') use ($tok, $o, $self) {
          return '<form method="post">' . hidden($tok, 'siparis-hizli', ['id' => $o['id'], 'do' => $do, 'back' => $self, 'notify' => '1']) . '<button class="btn btn--sm ' . $cls . '">' . e($label) . '</button></form>';
        }; ?>
        <tr class="<?= $o['status'] === 'iptal' ? 'is-off' : '' ?>">
          <td><a href="./?s=siparis&amp;id=<?= e(urlencode($o['id'])) ?>"><strong><?= e($o['name']) ?></strong></a><?= ($o['user'] ?? '') !== '' ? ' <span class="tag">üye</span>' : '' ?><small><?= e($o['phone']) ?> · <?= e($o['email']) ?></small><small class="mono-s"><?= e($o['code']) ?> · <?= e(time_ago($o['created'])) ?></small><?php if (trim($o['note'] ?? '') !== ''): ?><small class="note">“<?= e($o['note']) ?>”</small><?php endif; ?></td>
          <td><?= e($o['product_name']) ?><small><?= (int) $o['qty'] ?> adet × <?= e(money($o['unit_price'])) ?></small></td>
          <td><?= e($o['delivery'] === 'kargo' ? 'Kargo' : 'Atölyeden') ?><?php if (trim((string) ($o['cargo'] ?? '')) !== ''): ?><small><?= e($o['cargo']) ?></small><?php endif; ?></td>
          <td><?= pill(ORDER_STATUS[$o['status']] ?? $o['status'], ['beklemede' => 'warn', 'hazirlaniyor' => 'info', 'kargoda' => 'on', 'teslim' => 'on', 'iptal' => 'off'][$o['status']] ?? '') ?><?= order_hold_expired($o) ? ' <small class="muted" title="Ödeme süresi doldu; ürün ayırması kalktı. Ödeme gelirse ödendi işaretleyin.">süre doldu</small>' : '' ?></td>
          <td><?= e(money($o['total'])) ?><small class="<?= !empty($o['paid']) ? 'ok' : 'warn' ?>"><?= !empty($o['paid']) ? 'Ödendi' : 'Bekliyor' ?> · <?= e(ORDER_PAY_METHODS[$o['method']] ?? $o['method']) ?></small><?php if (!empty($o['test_paid']) && empty($o['paid'])): ?><small class="muted">test ödemesi</small><?php endif; ?></td>
          <td class="tbl__act">
            <?php if ($o['status'] === 'beklemede' && empty($o['paid'])): ?><?= $quick('odendi', 'Ödendi', '') ?>
            <?php elseif ($o['status'] === 'hazirlaniyor'): ?><?= $o['delivery'] === 'kargo' ? '<a class="btn btn--sm" href="./?s=siparis&amp;id=' . e(urlencode($o['id'])) . '#kargo">Kargoya ver</a>' : $quick('teslim', 'Teslim edildi', '') ?>
            <?php elseif ($o['status'] === 'kargoda'): ?><?= $quick('teslim', 'Teslim edildi') ?><?php endif; ?>
            <a class="btn btn--ghost btn--sm" href="./?s=siparis&amp;id=<?= e(urlencode($o['id'])) ?>">Aç</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<p class="hint">Kartla ödenen siparişler kendiliğinden "Hazırlanıyor" olur. Havale siparişinde ödeme gelince "Ödendi"ye basın. Durum değiştiğinde müşteriye bilgi e-postası gider. İptal edilen siparişin ürünü stoğa geri eklenir; kartla ödenmiş bir siparişin iadesini PayTR panelinden yapın.</p>
