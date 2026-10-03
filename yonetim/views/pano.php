<?php
/** @var array $c */ /** @var array $regs */ /** @var int $pendingRegs */ /** @var int $pendingCm */ /** @var int $unread */
$events = events_all();
$users = users_all();
$now = time();
// Yaklaşan oturumlar (30 gün)
$soon = [];
foreach ($events as $ev) {
  if (!in_array($ev['status'] ?? '', ['yayinda', 'ertelendi'], true)) continue;
  $list = is_package($ev) ? array_slice(event_sessions($ev, false), 0, 1) : event_sessions($ev, false);
  foreach ($list as $s) if (session_ts($s, true) >= $now && session_ts($s) <= $now + 45 * 86400) $soon[] = ['ev' => $ev, 's' => $s];
}
usort($soon, fn($a, $b) => session_ts($a['s']) <=> session_ts($b['s']));
$active = array_filter($regs, fn($r) => in_array($r['status'], SEAT_STATUSES, true));
$unpaid = array_filter($active, fn($r) => !$r['paid'] && $r['total'] > 0);
$waiting = array_filter($regs, fn($r) => $r['status'] === 'yedek');
$newUsers = array_filter($users, fn($u) => strtotime($u['created'] ?? '') >= $now - 30 * 86400);
$month = date('Y-m');
$revenue = array_sum(array_map(fn($r) => $r['paid'] ? $r['total'] : 0, array_filter($active, fn($r) => str_starts_with($r['created'], $month))));
$latest = $regs;
usort($latest, fn($a, $b) => strcmp($b['created'], $a['created']));
$latest = array_slice($latest, 0, 8);
$st = stats_summary(7);
$nf = fn($n) => number_format((int) $n, 0, ',', '.');
$drafts = array_filter($events, fn($e) => ($e['status'] ?? '') === 'taslak');
?>
<div class="bar"><h1>Merhaba</h1><span class="hint"><?= e(tr_date(date('Y-m-d'), true)) ?></span></div>

<?php $todo = array_filter([
  $pendingRegs ? ['./?s=katilimlar&durum=beklemede', $pendingRegs . ' kayıt onay ya da ödeme bekliyor'] : null,
  $waiting ? ['./?s=katilimlar&durum=yedek', count($waiting) . ' kişi yedek listede'] : null,
  $pendingCm ? ['./?s=yorumlar', $pendingCm . ' yorum onay bekliyor'] : null,
  $unread ? ['./?s=mesajlar', $unread . ' okunmamış mesaj'] : null,
  trim((string) setting('bank_iban', '')) === '' ? ['./?s=ayarlar#odeme', 'Havale için banka ve IBAN bilgisi girilmedi'] : null,
  $drafts ? ['./?s=etkinlikler&durum=taslak', count($drafts) . ' taslak etkinlik yayında değil'] : null,
]); ?>
<?php if ($todo): ?>
  <section class="card card--todo">
    <h2>Sizi bekleyenler</h2>
    <ul class="todo"><?php foreach ($todo as [$href, $text]): ?><li><a href="<?= e($href) ?>"><?= e($text) ?><span aria-hidden="true">→</span></a></li><?php endforeach; ?></ul>
  </section>
<?php endif; ?>

<section class="kpis">
  <div class="kpi"><span>Yaklaşan tarih</span><strong><?= count($soon) ?></strong><small>önümüzdeki 45 gün</small></div>
  <div class="kpi"><span>Aktif kayıt</span><strong><?= $nf(array_sum(array_column($active, 'seats'))) ?></strong><small>kişi · <?= count($unpaid) ?> ödeme bekliyor</small></div>
  <div class="kpi"><span>Üye</span><strong><?= $nf(count($users)) ?></strong><small>son 30 günde +<?= count($newUsers) ?></small></div>
  <div class="kpi"><span>Bu ay tahsil edilen</span><strong><?= e(money($revenue)) ?></strong><small>ziyaretçi (7 gün): <?= $nf($st['visitors']) ?></small></div>
</section>

<section class="card">
  <div class="card__head"><h2>Yaklaşan etkinlikler</h2><a href="./?s=etkinlikler">Tümü</a></div>
  <?php if (!$soon): ?><p class="hint">Önümüzdeki 45 günde yayında bir etkinlik yok. <a href="./?s=etkinlik">Yeni etkinlik ekleyin.</a></p><?php endif; ?>
  <ul class="fill-list">
    <?php foreach (array_slice($soon, 0, 10) as ['ev' => $ev, 's' => $s]):
      $sid = is_package($ev) ? '*' : $s['id'];
      $cap = capacity_of($ev, $s['id']); $taken = seats_taken($ev, $s['id'], $regs);
      $wait = count(array_filter($regs, fn($r) => $r['event'] === $ev['id'] && $r['status'] === 'yedek' && ($r['session'] === $sid)));
      $pct = $cap > 0 ? min(100, round($taken / $cap * 100)) : 0; ?>
      <li>
        <span class="date-chip"><b><?= (int) substr($s['date'], 8, 2) ?></b><?= e(TR_MONTHS_SHORT[(int) substr($s['date'], 5, 2)]) ?></span>
        <div class="fill-list__info">
          <a href="./?s=katilimlar&amp;etkinlik=<?= e(urlencode($ev['id'])) ?>&amp;oturum=<?= e(urlencode($sid)) ?>"><strong><?= e($ev['title']) ?></strong></a>
          <span class="hint"><?= e(($s['start'] ?? '') . ' · ' . session_place($s, false)) ?><?= is_package($ev) ? ' · ' . count(event_sessions($ev, false)) . ' buluşma' : '' ?><?= ($ev['status'] ?? '') === 'ertelendi' ? ' · Ertelendi' : '' ?></span>
        </div>
        <div class="fill">
          <span><b><?= $taken ?></b><?= $cap > 0 ? ' / ' . $cap : ' kişi' ?><?= $wait ? ' · ' . $wait . ' yedek' : '' ?></span>
          <?php if ($cap > 0): ?><i class="fill__bar<?= $pct >= 100 ? ' is-full' : '' ?>"><i style="width:<?= $pct ?>%"></i></i><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="card">
  <div class="card__head"><h2>Son kayıtlar</h2><a href="./?s=katilimlar">Tümü</a></div>
  <?php if (!$latest): ?><p class="hint">Henüz kayıt yok. Siteden katılım geldikçe burada görünür.</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl tbl--left">
    <thead><tr><th>Kişi</th><th>Etkinlik</th><th>Durum</th><th>Tutar</th><th>Zaman</th></tr></thead>
    <tbody>
      <?php foreach ($latest as $r): $ev = event_by_id($r['event']); ?>
        <tr>
          <td><a href="./?s=kayit&amp;id=<?= e(urlencode($r['id'])) ?>"><strong><?= e($r['name']) ?></strong></a><small><?= e($r['code']) ?> · <?= (int) $r['seats'] ?> kişi</small></td>
          <td><?= e($ev['title'] ?? 'Silinmiş etkinlik') ?><small><?= e(reg_session_label($r, $ev)) ?></small></td>
          <td><?= pill(REG_STATUS[$r['status']] ?? $r['status'], reg_tone($r['status'])) ?></td>
          <td><?= $r['total'] > 0 ? e(money($r['total'])) . ($r['paid'] ? '' : '<small>ödenmedi</small>') : 'Ücretsiz' ?></td>
          <td><?= e(time_ago($r['created'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<div class="grid2 grid2--top">
  <section class="card">
    <div class="card__head"><h2>En çok ilgi görenler</h2></div>
    <?php
      $int = json_read(INTERESTS_FILE);
      $rows = [];
      foreach ($events as $ev) if (!event_is_past($ev) && ($ev['status'] ?? '') === 'yayinda') $rows[] = [$ev, count($int[$ev['id']] ?? []), count(attendee_ids($ev)), comment_count($ev['id'])];
      usort($rows, fn($a, $b) => ($b[1] + $b[2]) <=> ($a[1] + $a[2]));
    ?>
    <?php if (!$rows): ?><p class="hint">Henüz veri yok.</p><?php else: ?>
      <ul class="meter">
        <?php foreach (array_slice($rows, 0, 6) as [$ev, $ni, $na, $nc]): ?>
          <li><span><?= e($ev['title']) ?><small><?= $na ?> kayıtlı üye · <?= $nc ?> yorum</small></span><b><?= $ni ?> düşünüyor</b></li>
        <?php endforeach; ?>
      </ul>
      <p class="hint">"Katılmayı düşünüyorum" diyen üyeler kesin kayıt değildir; hatırlatma duyurusu için iyi bir listedir.</p>
    <?php endif; ?>
  </section>
  <section class="card">
    <div class="card__head"><h2>Yeni üyeler</h2><a href="./?s=uyeler">Tümü</a></div>
    <?php $nu = $users; usort($nu, fn($a, $b) => strcmp($b['created'] ?? '', $a['created'] ?? '')); ?>
    <?php if (!$nu): ?><p class="hint">Henüz üye yok.</p><?php else: ?>
      <ul class="people">
        <?php foreach (array_slice($nu, 0, 6) as $u): ?><li><?= avatar($u, 'sm') ?><a href="./?s=uye&amp;id=<?= e(urlencode($u['id'])) ?>"><?= e($u['name']) ?></a><span class="hint"><?= e(time_ago($u['created'] ?? '')) ?></span></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
