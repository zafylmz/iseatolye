<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$past = !empty($_GET['gecmis']);
$cat = (string) ($_GET['kategori'] ?? '');
$ven = (string) ($_GET['mekan'] ?? '');
$when = (string) ($_GET['zaman'] ?? '');
$free = !empty($_GET['ucretsiz']);
$q = trim((string) ($_GET['q'] ?? ''));
if (!category($cat)) $cat = '';
if (!venue($ven)) $ven = '';
if (!in_array($when, ['hafta', 'haftasonu', 'ay', 'gelecek-ay'], true)) $when = '';

$norm = fn($s) => mb_strtolower(strtr($s, ['İ' => 'i', 'I' => 'ı']));
$inRange = function (array $ev) use ($when): bool {
  if ($when === '') return true;
  $today = strtotime('today');
  [$from, $to] = match ($when) {
    'hafta' => [$today, strtotime('sunday this week 23:59:59')],
    'haftasonu' => [strtotime('saturday this week'), strtotime('sunday this week 23:59:59')],
    'ay' => [$today, strtotime('last day of this month 23:59:59')],
    'gelecek-ay' => [strtotime('first day of next month 00:00'), strtotime('last day of next month 23:59:59')],
  };
  foreach (event_sessions($ev, false) as $s) { $t = session_ts($s); if ($t >= $from && $t <= $to) return true; }
  return false;
};
$filter = function (array $ev) use ($cat, $ven, $free, $q, $norm, $inRange): bool {
  if ($cat !== '' && ($ev['category'] ?? '') !== $cat) return false;
  if ($ven !== '' && !in_array($ven, array_column($ev['sessions'] ?? [], 'venue'), true)) return false;
  if ($free && !event_is_free($ev)) return false;
  if ($q !== '' && !str_contains($norm($ev['title'] . ' ' . ($ev['summary'] ?? '')), $norm($q))) return false;
  return $inRange($ev);
};
$list = $past ? array_values(array_filter(past_events(), $filter)) : upcoming_events(0, $filter);
$filtered = $cat || $ven || $when || $free || $q !== '';
// Filtreli ve arama sonuçları dizine eklenmez (sonsuz kopya sayfa olmasın); geçmiş etkinlikler kendi adresinde
if ($filtered) $noindex = true;
elseif ($past) $canonical = '/etkinlikler/?gecmis=1';
$title = ($past ? 'Geçmiş etkinlikler' : 'Etkinlikler') . ' · ' . $c['brand']['name'];
$description = 'Silivri ve çevresindeki atölyeler, yoga ve iyi oluş buluşmaları, sosyal etkinlikler. Tarih, mekân ve kontenjan bilgisiyle.';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head">
    <p class="label">Etkinlikler</p>
    <h1 class="display display--md"><?= $past ? 'Geçmiş etkinlikler' : 'Yaklaşan etkinlikler' ?></h1>
    <p class="lead"><?= $past ? 'Birlikte ürettiğimiz, paylaştığımız günler.' : 'Size uygun atölyeyi seçin, yerinizi birkaç adımda ayırtın.' ?></p>
  </section>
  <section class="wrap page-body">
    <div class="ftabs">
      <nav class="tabs" aria-label="Etkinlik zamanı">
        <a href="/etkinlikler/"<?= !$past ? ' aria-current="page"' : '' ?>>Yaklaşan</a>
        <a href="/etkinlikler/?gecmis=1"<?= $past ? ' aria-current="page"' : '' ?>>Geçmiş</a>
        <a href="/takvim/"><?= icon('takvim') ?>Takvim görünümü</a>
      </nav>
    </div>
    <form class="filters" method="get" action="/etkinlikler/" data-autosubmit>
      <?php if ($past): ?><input type="hidden" name="gecmis" value="1"><?php endif; ?>
      <label class="filters__search"><span class="sr">Etkinlik ara</span><?= icon('ara') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Etkinlik ara"></label>
      <label><span class="sr">Kategori</span><select name="kategori"><option value="">Tüm kategoriler</option><?php foreach (categories() as $k): ?><option value="<?= e($k['id']) ?>"<?= $cat === $k['id'] ? ' selected' : '' ?>><?= e($k['name']) ?></option><?php endforeach; ?></select></label>
      <label><span class="sr">Mekan</span><select name="mekan"><option value="">Tüm mekanlar</option><?php foreach (venues() as $v): ?><option value="<?= e($v['id']) ?>"<?= $ven === $v['id'] ? ' selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?></select></label>
      <?php if (!$past): ?><label><span class="sr">Zaman</span><select name="zaman"><option value="">Her zaman</option><?php foreach (['hafta' => 'Bu hafta', 'haftasonu' => 'Bu hafta sonu', 'ay' => 'Bu ay', 'gelecek-ay' => 'Gelecek ay'] as $k => $l): ?><option value="<?= $k ?>"<?= $when === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label><?php endif; ?>
      <label class="filters__check"><input type="checkbox" name="ucretsiz" value="1"<?= $free ? ' checked' : '' ?>> Yalnızca ücretsiz</label>
      <button class="btn btn--ghost btn--sm filters__go">Filtrele</button>
      <?php if ($filtered): ?><a class="filters__clear" href="/etkinlikler/<?= $past ? '?gecmis=1' : '' ?>">Filtreleri temizle</a><?php endif; ?>
    </form>
    <?php if ($list): ?>
      <p class="result-count"><?= count($list) ?> etkinlik</p>
      <div class="egrid"><?php foreach ($list as $ev) include __DIR__ . '/inc/event-card.php'; ?></div>
    <?php else: ?>
      <div class="empty"><p><?= $filtered ? 'Bu seçimlere uygun etkinlik bulunamadı.' : ($past ? 'Henüz geçmiş etkinlik yok.' : 'Şu an planlanmış etkinlik yok; yenileri çok yakında.') ?></p><?php if ($filtered): ?><a class="btn btn--ghost" href="/etkinlikler/">Tüm etkinlikleri göster</a><?php endif; ?></div>
    <?php endif; ?>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
