<?php
/** @var string $tok */ /** @var array $orders */
$codes = discount_codes();
usort($codes, fn($a, $b) => strcmp($b['created'] ?? '', $a['created'] ?? ''));
$used = [];
foreach ($orders as $o) if (($o['discount_code'] ?? '') !== '' && $o['status'] !== 'iptal') $used[$o['discount_code']] = ($used[$o['discount_code']] ?? 0) + 1;
$edit = null;
foreach ($codes as $d) if ($d['code'] === ($_GET['kod'] ?? '')) $edit = $d;
?>
<div class="bar"><h1>İndirim kodları</h1><a class="btn btn--ghost" href="./?s=urunler">Ürünler</a></div>
<p class="hint">Müşteri sipariş formuna kodu yazınca ürün tutarından belirlediğiniz oranda indirim yapılır (kargo ücretine uygulanmaz). Kod, son geçerlilik gününün sonuna kadar kullanılabilir.</p>

<form method="post" class="card" id="kod">
  <?= hidden($tok, 'indirim', ['old' => $edit['code'] ?? '']) ?>
  <h2><?= $edit ? e($edit['code']) . ' kodunu düzenle' : 'Yeni indirim kodu' ?></h2>
  <div class="grid3">
    <label>Kod <span class="opt">(harf ve rakam, ör. YAZ20)</span><input name="code" value="<?= e($edit['code'] ?? '') ?>" maxlength="30" required pattern="[A-Za-z0-9_-]{3,30}" style="text-transform:uppercase" autocomplete="off"></label>
    <label>İndirim oranı (%)<input type="number" name="pct" value="<?= e((string) ($edit['pct'] ?? '')) ?>" min="1" max="100" step="1" required></label>
    <label>Son geçerlilik günü<input type="date" name="until" value="<?= e($edit['until'] ?? date('Y-m-d', strtotime('+30 days'))) ?>" min="<?= date('Y-m-d') ?>" required></label>
  </div>
  <label class="check"><input type="checkbox" name="active" value="1"<?= ($edit['active'] ?? true) ? ' checked' : '' ?>> Kod kullanılabilir</label>
  <div><button class="btn"><?= $edit ? 'Kaydet' : 'Kodu oluştur' ?></button><?php if ($edit): ?> <a class="btn btn--ghost" href="./?s=indirimler">Vazgeç</a><?php endif; ?></div>
</form>

<section class="card card--flush">
  <div class="card__head card__head--pad"><h2><?= count($codes) ?> kod</h2></div>
  <?php if (!$codes): ?><p class="hint pad">Henüz indirim kodu yok.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl tbl--left">
    <thead><tr><th>Kod</th><th>İndirim</th><th>Son gün</th><th>Durum</th><th>Kullanım</th><th>İşlem</th></tr></thead>
    <tbody>
      <?php foreach ($codes as $d): $st = discount_state($d); ?>
        <tr class="<?= $st !== 'aktif' ? 'is-off' : '' ?>">
          <td><strong class="mono-s"><?= e($d['code']) ?></strong></td>
          <td>%<?= (float) $d['pct'] ?></td>
          <td><?= e(tr_date($d['until'])) ?></td>
          <td><?= pill(['aktif' => 'Geçerli', 'doldu' => 'Süresi doldu', 'kapali' => 'Kapalı'][$st], ['aktif' => 'on', 'doldu' => 'warn', 'kapali' => 'off'][$st]) ?></td>
          <td><?php $n = $used[$d['code']] ?? 0; ?><?= $n ? '<a href="' . e(panel_url(['s' => 'siparisler', 'q' => $d['code']])) . '">' . $n . ' sipariş</a>' : '–' ?></td>
          <td class="tbl__act">
            <a class="btn btn--ghost btn--sm" href="./?s=indirimler&amp;kod=<?= e(urlencode($d['code'])) ?>#kod">Düzenle</a>
            <form method="post" data-confirm="<?= e($d['code']) ?> kodu silinecek. Bu kodla verilmiş siparişler etkilenmez. Emin misiniz?"><?= hidden($tok, 'indirim-sil', ['code' => $d['code']]) ?><button class="btn btn--ghost btn--sm">Sil</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
