<?php
/** @var array $regs */
$fq = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sira'] ?? 'yeni');
$users = users_all();
$regCount = []; $lastReg = [];
foreach ($regs as $r) if (($r['user'] ?? '') !== '' && $r['status'] !== 'iptal') { $regCount[$r['user']] = ($regCount[$r['user']] ?? 0) + 1; $lastReg[$r['user']] = max($lastReg[$r['user']] ?? '', $r['created']); }
$cmCount = [];
foreach (json_read(COMMENTS_FILE) as $list) foreach ($list as $cm) if (($cm['user'] ?? '') !== '') $cmCount[$cm['user']] = ($cmCount[$cm['user']] ?? 0) + 1;
$list = array_values(array_filter($users, fn($u) => $fq === '' || str_contains(mb_strtolower($u['name'] . ' ' . $u['email'] . ' ' . ($u['phone'] ?? '') . ' ' . $u['username']), mb_strtolower($fq))));
usort($list, match ($sort) {
  'katilim' => fn($a, $b) => ($regCount[$b['id']] ?? 0) <=> ($regCount[$a['id']] ?? 0),
  'ad' => fn($a, $b) => strcoll(mb_strtolower($a['name']), mb_strtolower($b['name'])),
  'giris' => fn($a, $b) => strcmp($b['last_login'] ?? '', $a['last_login'] ?? ''),
  default => fn($a, $b) => strcmp($b['created'] ?? '', $a['created'] ?? ''),
});
$withReg = count(array_filter($users, fn($u) => isset($regCount[$u['id']])));
?>
<div class="bar"><h1>Üyeler <span class="count"><?= count($users) ?></span></h1></div>
<p class="hint"><?= $withReg ?> üye en az bir etkinliğe kayıt oldu. Üyeler kendi profillerini, şifrelerini ve hesaplarını sitedeki "Hesabım" sayfasından yönetir.</p>
<form class="toolbar" method="get">
  <input type="hidden" name="s" value="uyeler">
  <nav class="seg"><?php foreach (['yeni' => 'En yeni', 'katilim' => 'En çok katılan', 'giris' => 'Son giriş', 'ad' => 'A–Z'] as $k => $l): ?><a href="<?= e(panel_url(['s' => 'uyeler', 'q' => $fq, 'sira' => $k])) ?>"<?= $sort === $k ? ' aria-current="page"' : '' ?>><?= $l ?></a><?php endforeach; ?></nav>
  <div class="toolbar__search"><input type="search" name="q" value="<?= e($fq) ?>" placeholder="Ad, e-posta, telefon"><button class="btn btn--ghost">Ara</button></div>
</form>
<?php if (!$list): ?><section class="card"><p class="hint"><?= $users ? 'Aramaya uyan üye yok.' : 'Henüz üye yok. Ziyaretçiler sitede "Üye ol" ile kayıt olduğunda burada görünür.' ?></p></section><?php else: ?>
<section class="card card--flush">
  <div class="tbl-wrap"><table class="tbl tbl--left">
    <thead><tr><th>Üye</th><th>İletişim</th><th>Katılım</th><th>Yorum</th><th>Üyelik</th></tr></thead>
    <tbody>
      <?php foreach ($list as $u): ?>
        <tr class="<?= ($u['status'] ?? 'aktif') !== 'aktif' ? 'is-off' : '' ?>">
          <td><a class="person" href="./?s=uye&amp;id=<?= e(urlencode($u['id'])) ?>"><?= avatar($u, 'sm') ?><span><strong><?= e($u['name']) ?></strong><small>@<?= e($u['username']) ?><?= ($u['status'] ?? 'aktif') !== 'aktif' ? ' · askıda' : '' ?></small></span></a></td>
          <td><?= e($u['email']) ?><small><?= e($u['phone'] ?? '') ?></small></td>
          <td><?= $regCount[$u['id']] ?? 0 ?></td>
          <td><?= $cmCount[$u['id']] ?? 0 ?></td>
          <td><?= e(tr_date(substr($u['created'] ?? '', 0, 10))) ?><small><?= !empty($u['last_login']) ? 'son giriş ' . e(time_ago($u['last_login'])) : '' ?></small></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
