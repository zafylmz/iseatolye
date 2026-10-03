<?php
// Yazdırılabilir yoklama listesi: etkinlik günü kapıda işaretlemek için.
$ev = event_by_id((string) ($_GET['etkinlik'] ?? ''));
$sid = (string) ($_GET['oturum'] ?? '');
if (!$ev) { http_response_code(404); exit('Etkinlik bulunamadı.'); }
$s = $sid === '*' || is_package($ev) ? (event_sessions($ev, false)[0] ?? null) : session_by_id($ev, $sid);
$rows = array_values(array_filter(regs_of_event($ev['id']), fn($r) => in_array($r['status'], ['onayli', 'beklemede'], true) && (is_package($ev) || $r['session'] === $sid)));
usort($rows, fn($a, $b) => strcoll(mb_strtolower($a['name']), mb_strtolower($b['name'])));
$wait = array_values(array_filter(regs_of_event($ev['id']), fn($r) => $r['status'] === 'yedek' && (is_package($ev) || $r['session'] === $sid)));
$total = array_sum(array_column($rows, 'seats'));
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Yoklama · <?= e($ev['title']) ?></title>
<style>
  @font-face{font-family:Montserrat;font-weight:100 900;src:url(/assets/fonts/montserrat-latin-wght-normal.woff2) format('woff2'),url(/assets/fonts/montserrat-latin-ext-wght-normal.woff2) format('woff2')}
  body{font:13px/1.45 Montserrat,system-ui,sans-serif;color:#2c2118;margin:32px;background:#fff}
  h1{font-size:20px;margin:0 0 4px}.meta{color:#6f5f52;margin:0 0 18px}
  table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px 6px;border-bottom:1px solid #ddd2c4;vertical-align:top}
  th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#6f5f52}
  .box{width:18px;height:18px;border:1.5px solid #8b5e3c;border-radius:4px;display:inline-block}.box.on{background:#8b5e3c}
  small{display:block;color:#6f5f52}.tag{font-size:11px;padding:1px 6px;border-radius:99px;background:#f1e5d7}
  .bar{display:flex;justify-content:space-between;align-items:flex-start;gap:20px}
  button{font:inherit;font-weight:600;padding:9px 18px;border-radius:99px;border:1px solid #8b5e3c;background:#8b5e3c;color:#fff;cursor:pointer}
  button:hover{background:#714a2d}
  h2{font-size:14px;margin:28px 0 8px}
  @media print{body{margin:12mm}button{display:none}}
</style></head><body>
<div class="bar">
  <div><h1><?= e($ev['title']) ?></h1><p class="meta"><?= e(is_package($ev) ? event_when($ev) : ($s ? session_when($s) : '')) ?> · <?= e(session_place($s)) ?> · <?= count($rows) ?> kayıt, <?= $total ?> kişi</p></div>
  <button onclick="print()">Yazdır</button>
</div>
<table>
  <thead><tr><th style="width:28px"></th><th>Ad soyad</th><th>Telefon</th><th>Bilet</th><th>Kişi</th><th>Ödeme</th><th>Not</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><span class="box<?= $r['checked_in'] ? ' on' : '' ?>"></span></td>
      <td><strong><?= e($r['name']) ?></strong><?php if (trim($r['others'] ?? '') !== ''): ?><small>+ <?= e($r['others']) ?></small><?php endif; ?><small><?= e($r['code']) ?><?= $r['status'] === 'beklemede' ? ' · <span class="tag">onay bekliyor</span>' : '' ?></small></td>
      <td><?= e($r['phone']) ?></td>
      <td><?= e($r['ticket_name']) ?> × <?= (int) $r['qty'] ?></td>
      <td><?= (int) $r['seats'] ?></td>
      <td><?= $r['total'] <= 0 ? 'Ücretsiz' : ($r['paid'] ? 'Ödendi' : '<b>' . e(money($r['total'])) . '</b> alınacak') ?></td>
      <td><?= e(trim(($r['note'] ?? '') . ' ' . ($r['admin_note'] ?? ''))) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7">Kayıt yok.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php if ($wait): ?>
  <h2>Yedek liste</h2>
  <table><tbody><?php foreach ($wait as $i => $r): ?><tr><td style="width:28px"><?= $i + 1 ?>.</td><td><?= e($r['name']) ?></td><td><?= e($r['phone']) ?></td><td><?= (int) $r['seats'] ?> kişi</td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
</body></html>
