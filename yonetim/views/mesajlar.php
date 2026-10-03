<?php
/** @var string $tok */
$msgs = json_read(MESSAGES_FILE, ['messages' => []])['messages'] ?? [];
$labels = ['genel' => 'Genel soru', 'kurumsal' => 'Kurumsal talep', 'ozel' => 'Özel gün / grup'];
$f = (string) ($_GET['f'] ?? 'tum');
if ($f !== 'tum') $msgs = array_values(array_filter($msgs, fn($m) => ($m['topic'] ?? 'genel') === $f));
?>
<div class="bar"><h1>Mesajlar</h1>
  <nav class="seg"><a href="./?s=mesajlar"<?= $f === 'tum' ? ' aria-current="page"' : '' ?>>Tümü</a><?php foreach ($labels as $k => $l): ?><a href="./?s=mesajlar&amp;f=<?= $k ?>"<?= $f === $k ? ' aria-current="page"' : '' ?>><?= $l ?></a><?php endforeach; ?></nav>
</div>
<p class="hint">İletişim ve kurumsal formundan gelen mesajlar. Her mesaj ayrıca bildirim e-postanıza gönderilir.</p>
<?php if (!$msgs): ?><section class="card"><p class="hint">Mesaj yok.</p></section><?php endif; ?>
<?php foreach ($msgs as $m): $u = !empty($m['user']) ? user_by_id($m['user']) : null; ?>
  <section class="card comment-admin<?= empty($m['read']) ? ' is-unread' : '' ?>">
    <div class="comment-admin__head">
      <strong><?= e($m['name'] ?? '') ?></strong>
      <a href="mailto:<?= e($m['email'] ?? '') ?>?subject=<?= e(rawurlencode('Re: ' . ($labels[$m['topic'] ?? 'genel'] ?? 'Mesajınız'))) ?>"><?= e($m['email'] ?? '') ?></a>
      <?php if (!empty($m['phone'])): ?><a href="<?= e(wa_href($m['phone'])) ?>" target="_blank" rel="noopener"><?= e($m['phone']) ?></a><?php endif; ?>
      <?php if ($u): ?><a class="tag" href="./?s=uye&amp;id=<?= e(urlencode($u['id'])) ?>">üye</a><?php endif; ?>
      <span class="hint"><?= e(tr_datetime($m['date'] ?? '')) ?></span>
      <?= pill($labels[$m['topic'] ?? 'genel'] ?? 'Genel', ($m['topic'] ?? '') === 'kurumsal' ? 'info' : '') ?>
    </div>
    <?php $meta = array_filter(['Kurum' => $m['company'] ?? '', 'Kişi sayısı' => $m['people'] ?? '', 'Tarih tercihi' => $m['date_pref'] ?? '']); ?>
    <?php if ($meta): ?><p class="hint"><?= e(implode(' · ', array_map(fn($k, $v) => $k . ': ' . $v, array_keys($meta), $meta))) ?></p><?php endif; ?>
    <div class="comment-admin__text"><?= nl2br(e($m['text'] ?? '')) ?></div>
    <div class="plist__act plist__act--start">
      <a class="btn btn--sm" href="mailto:<?= e($m['email'] ?? '') ?>">E-postayla yanıtla</a>
      <form method="post"><?= hidden($tok, 'mesaj', ['id' => $m['id'], 'do' => empty($m['read']) ? 'okundu' : 'okunmadi']) ?><button class="btn btn--ghost btn--sm"><?= empty($m['read']) ? 'Okundu yap' : 'Okunmadı yap' ?></button></form>
      <form method="post" data-confirm="Mesaj silinsin mi?"><?= hidden($tok, 'mesaj', ['id' => $m['id'], 'do' => 'sil']) ?><button class="btn btn--danger btn--sm">Sil</button></form>
    </div>
  </section>
<?php endforeach; ?>
