<?php
/** @var string $tok */ /** @var array $c */
$tab = (string) ($_GET['t'] ?? 'etkinlik');
$blogPending = 0;
foreach (json_read(BLOG_COMMENTS_FILE) as $list) foreach ($list as $cm) if (($cm['status'] ?? '') !== 'approved') $blogPending++;
$rows = [];
foreach (json_read(COMMENTS_FILE) as $eid => $list) foreach ($list as $cm) $rows[] = ['eid' => $eid] + $cm;
usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));
$evPending = count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'beklemede'));
?>
<div class="bar">
  <h1>Yorumlar</h1>
  <nav class="seg"><a href="./?s=yorumlar"<?= $tab !== 'blog' ? ' aria-current="page"' : '' ?>>Etkinlik yorumları<?= $evPending ? ' (' . $evPending . ')' : '' ?></a><a href="./?s=yorumlar&amp;t=blog"<?= $tab === 'blog' ? ' aria-current="page"' : '' ?>>Blog yorumları<?= $blogPending ? ' (' . $blogPending . ')' : '' ?></a></nav>
</div>
<?php if ($tab === 'blog') { require __DIR__ . '/blog-yorumlar.php'; return; } ?>
<?php
$f = (string) ($_GET['f'] ?? ($evPending ? 'bekleyen' : 'tum'));
$fe = (string) ($_GET['etkinlik'] ?? '');
if ($f === 'bekleyen') $rows = array_values(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'beklemede'));
if ($fe !== '') $rows = array_values(array_filter($rows, fn($r) => $r['eid'] === $fe));
$self = panel_url(['s' => 'yorumlar', 'f' => $f, 'etkinlik' => $fe]);
$byId = [];
foreach ($rows as $r) $byId[$r['id']] = $r;
?>
<div class="toolbar">
  <nav class="seg"><a href="<?= e(panel_url(['s' => 'yorumlar', 'f' => 'bekleyen'])) ?>"<?= $f === 'bekleyen' ? ' aria-current="page"' : '' ?>>Onay bekleyen (<?= $evPending ?>)</a><a href="<?= e(panel_url(['s' => 'yorumlar', 'f' => 'tum'])) ?>"<?= $f !== 'bekleyen' ? ' aria-current="page"' : '' ?>>Tümü</a></nav>
  <p class="hint"><?= setting('comment_moderation', false) ? 'Yeni yorumlar sizin onayınızdan sonra yayınlanır.' : 'Üyelerin yorumları hemen yayınlanır; uygunsuz olanları gizleyebilirsiniz. Onaylı yayını Ayarlar\'dan açabilirsiniz.' ?></p>
</div>
<?php if (!$rows): ?><section class="card"><p class="hint"><?= $f === 'bekleyen' ? 'Onay bekleyen yorum yok.' : 'Henüz yorum yok.' ?></p></section><?php endif; ?>
<?php foreach (array_slice($rows, 0, 150) as $r):
  $ev = event_by_id($r['eid']);
  $u = !empty($r['admin']) ? null : user_by_id($r['user'] ?? '');
  $st = $r['status'] ?? 'yayinda';
  $btn = fn(string $do, string $label, string $cls = 'btn--ghost') => '<form method="post"' . ($do === 'sil' ? ' data-confirm="Bu yorumu (ve yanıtlarını) silmek istediğinize emin misiniz?"' : '') . '>' . hidden($tok, 'yorum-etkinlik', ['event' => $r['eid'], 'id' => $r['id'], 'do' => $do, 'back' => $self]) . '<button class="btn btn--sm ' . $cls . '">' . $label . '</button></form>'; ?>
  <section class="card comment-admin<?= !empty($r['admin']) ? ' comment-admin--team' : '' ?>">
    <div class="comment-admin__head">
      <?php if (!empty($r['admin'])): ?><strong><?= e($c['brand']['name']) ?></strong><span class="tag">Ekip yanıtı</span>
      <?php elseif ($u): ?><a class="person" href="./?s=uye&amp;id=<?= e(urlencode($u['id'])) ?>"><?= avatar($u, 'sm') ?><strong><?= e($u['name']) ?></strong></a>
      <?php else: ?><strong>Eski üye</strong><?php endif; ?>
      <span class="hint"><?= e(tr_datetime($r['date'])) ?> · <a href="<?= e($ev ? event_url($ev) . '#yorum-' . $r['id'] : '#') ?>" target="_blank" rel="noopener"><?= e($ev['title'] ?? 'Silinmiş etkinlik') ?></a><?= ($r['parent'] ?? '') !== '' ? ' · yanıt' : '' ?></span>
      <?= pill(['yayinda' => 'Yayında', 'beklemede' => 'Onay bekliyor', 'gizli' => 'Gizli'][$st] ?? $st, ['yayinda' => 'on', 'beklemede' => 'warn', 'gizli' => 'off'][$st] ?? '') ?>
    </div>
    <?php if (($r['parent'] ?? '') !== '' && isset($byId[$r['parent']])): ?><p class="hint">Yanıtladığı: “<?= e(excerpt($byId[$r['parent']]['text'], 90)) ?>”</p><?php endif; ?>
    <div class="comment-admin__text"><?= nl2br(e($r['text'])) ?></div>
    <?php if (empty($r['admin'])): ?>
      <details class="comment-admin__reply"><summary>Ekip olarak yanıtla</summary>
        <form method="post"><?= hidden($tok, 'yorum-etkinlik', ['event' => $r['eid'], 'id' => $r['id'], 'do' => 'yanit', 'back' => $self]) ?><textarea name="reply" rows="2" required placeholder="Yanıtınız “<?= e($c['brand']['name']) ?>” adıyla, Ekip etiketiyle görünür."></textarea><button class="btn btn--sm">Yanıtı yayınla</button></form>
      </details>
    <?php endif; ?>
    <div class="plist__act plist__act--start">
      <?= $st !== 'yayinda' ? $btn('onayla', $st === 'gizli' ? 'Yeniden yayınla' : 'Onayla', '') : $btn('gizle', 'Gizle') ?>
      <?= $btn('sil', 'Sil', 'btn--danger') ?>
    </div>
  </section>
<?php endforeach; ?>
