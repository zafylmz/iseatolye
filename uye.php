<?php
// Herkese açık üye profili.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$u = user_by('username', (string) ($_GET['k'] ?? ''));
if (!$u || !user_public($u)) { http_response_code(404); include __DIR__ . '/404.php'; exit; }
$me = current_user();
$isMe = $me && $me['id'] === $u['id'];
$showEvents = ($u['show_events'] ?? true) || $isMe;

$going = []; $been = [];
foreach (user_regs($u['id']) as $r) {
  if (!in_array($r['status'], SEAT_STATUSES, true)) continue;
  $ev = event_by_id($r['event']);
  if (!$ev || !event_visible($ev) || isset($going[$ev['id']]) || isset($been[$ev['id']])) continue;
  if (event_is_past($ev)) { if ($r['status'] === 'onayli') $been[$ev['id']] = $ev; } else $going[$ev['id']] = $ev;
}
$going = array_values($going); $been = array_values($been);
usort($going, fn($a, $b) => event_sort_ts($a) <=> event_sort_ts($b));
$thinking = !$showEvents ? [] : array_values(array_filter(array_map(fn($id) => event_by_id((string) $id), array_keys(user_interests($u['id']))), fn($ev) => $ev && ($ev['status'] ?? '') === 'yayinda' && !event_is_past($ev)));
$myComments = [];
foreach (json_read(COMMENTS_FILE) as $eid => $list) {
  $ev = event_by_id($eid);
  if (!$ev || !event_visible($ev)) continue;
  foreach ($list as $cm) if (($cm['user'] ?? '') === $u['id'] && ($cm['status'] ?? '') === 'yayinda') $myComments[] = $cm + ['ev' => $ev];
}
usort($myComments, fn($a, $b) => strcmp($b['date'], $a['date']));
$title = $u['name'] . ' · ' . $c['brand']['name'];
$description = $u['name'] . ', ' . $c['brand']['name'] . ' topluluğu üyesi.';
$noindex = true;
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap profile">
    <header class="profile__head">
      <?= avatar($u, 'xl') ?>
      <div class="profile__who">
        <p class="label">Topluluk üyesi</p>
        <h1 class="h2"><?= e($u['name']) ?></h1>
        <p class="profile__meta"><span>@<?= e($u['username']) ?></span><?php if (trim($u['city'] ?? '') !== ''): ?><span><?= icon('konum') ?><?= e($u['city']) ?></span><?php endif; ?><span><?= e(TR_MONTHS[(int) date('n', strtotime($u['created']))] . ' ' . date('Y', strtotime($u['created']))) ?> tarihinden beri üye</span><?php if (trim($u['instagram'] ?? '') !== ''): ?><a href="https://www.instagram.com/<?= e($u['instagram']) ?>/" target="_blank" rel="noopener nofollow"><?= icon('instagram') ?><?= e($u['instagram']) ?></a><?php endif; ?></p>
        <?php if (trim($u['bio'] ?? '') !== ''): ?><p class="profile__bio"><?= nl2br(e($u['bio'])) ?></p><?php endif; ?>
        <?php if ($isMe): ?><a class="btn btn--ghost btn--sm" href="/hesabim/?s=profil">Profili düzenle</a><?php endif; ?>
      </div>
      <ul class="profile__stats">
        <?php if ($showEvents): ?><li><strong><?= count($been) ?></strong><span>katıldığı etkinlik</span></li><?php endif; ?>
        <li><strong><?= count($myComments) ?></strong><span>yorum</span></li>
        <li><strong><?= count($thinking) ?></strong><span>düşündüğü etkinlik</span></li>
      </ul>
    </header>
    <?php if ($isMe && !($u['show_events'] ?? true)): ?><p class="notice notice--warn">Katıldığınız ve düşündüğünüz etkinlikler başkalarına gizli. Bunu <a href="/hesabim/?s=profil">profil ayarlarından</a> değiştirebilirsiniz.</p><?php endif; ?>

    <?php if ($showEvents && $going): ?>
      <h2 class="h3 profile__h">Katılacağı etkinlikler</h2>
      <div class="egrid egrid--sm"><?php foreach ($going as $ev) include __DIR__ . '/inc/event-card.php'; ?></div>
    <?php endif; ?>
    <?php if ($thinking): ?>
      <h2 class="h3 profile__h">Katılmayı düşündükleri</h2>
      <div class="egrid egrid--sm"><?php foreach ($thinking as $ev) include __DIR__ . '/inc/event-card.php'; ?></div>
    <?php endif; ?>
    <?php if ($showEvents && $been): ?>
      <h2 class="h3 profile__h">Katıldığı etkinlikler</h2>
      <ul class="been"><?php foreach ($been as $ev): $s = next_session($ev); ?><li><a href="<?= e(event_url($ev)) ?>"><?php if (!empty($ev['cover'])): ?><img src="<?= e($ev['cover']) ?>" alt="" loading="lazy"><?php endif; ?><span><strong><?= e($ev['title']) ?></strong><small><?= e($s ? tr_date($s['date']) : '') ?></small></span></a></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <h2 class="h3 profile__h">Son yorumları</h2>
    <?php if ($myComments): ?>
      <ul class="pcomments"><?php foreach (array_slice($myComments, 0, 10) as $cm): ?><li><a class="label" href="<?= e(event_url($cm['ev'])) ?>#yorum-<?= e($cm['id']) ?>"><?= e($cm['ev']['title']) ?></a><p><?= e(mb_strlen($cm['text']) > 280 ? mb_substr($cm['text'], 0, 279) . '…' : $cm['text']) ?></p><time class="muted small"><?= e(time_ago($cm['date'])) ?></time></li><?php endforeach; ?></ul>
    <?php else: ?><p class="muted">Henüz yorum yazmamış.</p><?php endif; ?>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
