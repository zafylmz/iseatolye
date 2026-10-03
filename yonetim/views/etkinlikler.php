<?php
/** @var array $regs */ /** @var string $tok */
$fq = trim((string) ($_GET['q'] ?? ''));
$fst = (string) ($_GET['durum'] ?? '');
$fwhen = (string) ($_GET['zaman'] ?? 'gelecek');
$events = events_all();
$counts = ['gelecek' => 0, 'gecmis' => 0, 'tum' => count($events)];
foreach ($events as $ev) $counts[event_is_past($ev) ? 'gecmis' : 'gelecek']++;
$list = array_values(array_filter($events, function ($ev) use ($fq, $fst, $fwhen) {
  if ($fst !== '' && ($ev['status'] ?? '') !== $fst) return false;
  if ($fwhen === 'gelecek' && event_is_past($ev)) return false;
  if ($fwhen === 'gecmis' && !event_is_past($ev)) return false;
  if ($fq !== '' && !str_contains(mb_strtolower($ev['title'] . ' ' . ($ev['summary'] ?? '')), mb_strtolower($fq))) return false;
  return true;
}));
usort($list, fn($a, $b) => $fwhen === 'gecmis' ? event_sort_ts($b) <=> event_sort_ts($a) : event_sort_ts($a) <=> event_sort_ts($b));
$q = fn(array $x) => panel_url(['s' => 'etkinlikler', 'q' => $fq, 'durum' => $fst, 'zaman' => $fwhen] + $x);
?>
<div class="bar"><h1>Etkinlikler</h1><a class="btn" href="./?s=etkinlik">Yeni etkinlik</a></div>
<div class="toolbar">
  <nav class="seg" aria-label="Zaman">
    <?php foreach (['gelecek' => 'Yaklaşan', 'gecmis' => 'Geçmiş', 'tum' => 'Tümü'] as $k => $l): ?><a href="<?= e(panel_url(['s' => 'etkinlikler', 'q' => $fq, 'durum' => $fst, 'zaman' => $k])) ?>"<?= $fwhen === $k ? ' aria-current="page"' : '' ?>><?= $l ?> <small><?= $counts[$k] ?></small></a><?php endforeach; ?>
  </nav>
  <form class="toolbar__search" method="get">
    <input type="hidden" name="s" value="etkinlikler"><input type="hidden" name="zaman" value="<?= e($fwhen) ?>">
    <select name="durum" onchange="this.form.submit()"><option value="">Tüm durumlar</option><?php foreach (EVENT_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $fst === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <input type="search" name="q" value="<?= e($fq) ?>" placeholder="Etkinlik ara">
    <button class="btn btn--ghost">Ara</button>
  </form>
</div>

<?php if (!$list): ?><section class="card"><p class="hint">Bu filtreye uyan etkinlik yok.</p></section><?php endif; ?>
<ul class="plist">
  <?php foreach ($list as $ev):
    $evRegs = regs_of_event($ev['id'], $regs);
    $seats = array_sum(array_map(fn($r) => holds_seat($r) ? (int) $r['seats'] : 0, $evRegs));
    $pend = count(array_filter($evRegs, fn($r) => $r['status'] === 'beklemede'));
    $wait = count(array_filter($evRegs, fn($r) => $r['status'] === 'yedek'));
    $sessions = event_sessions($ev);
    $cap = is_package($ev) ? (int) ($ev['capacity'] ?? 0) : array_sum(array_map(fn($s) => (int) ($s['capacity'] ?? 0), array_filter($sessions, fn($s) => ($s['status'] ?? '') !== 'iptal')));
    $venues = array_unique(array_map(fn($s) => session_place($s, false), $sessions)); ?>
    <li class="card">
      <?php if (!empty($ev['cover'])): ?><img class="thumb" src="<?= e($ev['cover']) ?>" alt=""><?php else: ?><span class="thumb thumb--empty">İA</span><?php endif; ?>
      <div class="plist__info">
        <strong><?= e($ev['title']) ?><?= !empty($ev['pinned']) ? ' <span class="pin" title="Üstte sabit">📌</span>' : '' ?></strong>
        <span><?= pill(EVENT_STATUS[$ev['status'] ?? 'taslak'] ?? '', event_tone($ev['status'] ?? '')) ?> <?= e(event_when($ev)) ?><?= count($sessions) > 1 ? ' · ' . count($sessions) . ($ev['package'] ? ' buluşma' : ' tarih') : '' ?></span>
        <span><?= e(implode(', ', $venues) ?: 'Mekan seçilmedi') ?> · <?= e(price_short($ev)) ?></span>
        <span><b><?= $seats ?></b><?= $cap > 0 ? ' / ' . $cap : '' ?> kişi kayıtlı<?= $pend ? ' · <b>' . $pend . ' onay bekliyor</b>' : '' ?><?= $wait ? ' · ' . $wait . ' yedek' : '' ?> · <?= count(interests_of($ev['id'])) ?> düşünüyor · <?= comment_count($ev['id']) ?> yorum</span>
      </div>
      <div class="plist__act">
        <a class="btn btn--ghost" href="./?s=katilimlar&amp;etkinlik=<?= e(urlencode($ev['id'])) ?>">Katılımlar</a>
        <?php if (($ev['status'] ?? '') !== 'taslak'): ?><a class="btn btn--ghost" href="<?= e(event_url($ev)) ?>" target="_blank" rel="noopener">Gör</a><?php endif; ?>
        <a class="btn" href="./?s=etkinlik&amp;id=<?= e(urlencode($ev['id'])) ?>">Düzenle</a>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
<p class="hint">Geçmiş etkinlikler sitede "Geçmiş etkinlikler" altında arşiv olarak kalır; yorumlar ve katılımcı profilleri için silmeniz gerekmez.</p>
