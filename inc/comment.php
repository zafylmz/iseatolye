<?php
/** @var array $cm */ /** @var array $sub */
$__u = !empty($cm['admin']) ? null : user_by_id($cm['user'] ?? '');
$__mine = $me && !empty($cm['user']) && $cm['user'] === $me['id'];
?>
<li class="cm<?= !empty($cm['admin']) ? ' cm--team' : '' ?>" id="yorum-<?= e($cm['id']) ?>">
  <div class="cm__head">
    <?php if (!empty($cm['admin'])): ?><span class="avatar avatar--sm avatar--brand" aria-hidden="true">İ</span><strong><?= e($c['brand']['name']) ?></strong><span class="tag">Ekip</span>
    <?php elseif ($__u): ?><a href="<?= e(user_url($__u)) ?>"><?= avatar($__u, 'sm') ?></a><a href="<?= e(user_url($__u)) ?>"><strong><?= e($__u['name']) ?></strong></a><?php if (in_array($__u['id'], $attendedIds ?? [], true)): ?><span class="tag"><?= $past ? 'Katıldı' : 'Katılıyor' ?></span><?php endif; ?>
    <?php else: ?><?= avatar(null, 'sm') ?><strong>Eski üye</strong><?php endif; ?>
    <time datetime="<?= e($cm['date']) ?>"><?= e(time_ago($cm['date'])) ?></time>
  </div>
  <div class="cm__text"><?= paragraphs($cm['text']) ?></div>
  <?php if ($me && empty($cm['parent'])): ?>
    <div class="cm__act"><button type="button" data-reply="<?= e($cm['id']) ?>" data-reply-name="<?= e(!empty($cm['admin']) ? $c['brand']['name'] : display_name($__u)) ?>">Yanıtla</button><?php if ($__mine): ?><form method="post" action="/uye-api.php" onsubmit="return confirm('Yorumunuzu silmek istiyor musunuz?')"><input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="yorum-sil"><input type="hidden" name="event" value="<?= e($ev['id']) ?>"><input type="hidden" name="id" value="<?= e($cm['id']) ?>"><button>Sil</button></form><?php endif; ?></div>
  <?php elseif ($__mine): ?>
    <div class="cm__act"><form method="post" action="/uye-api.php" onsubmit="return confirm('Yorumunuzu silmek istiyor musunuz?')"><input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="yorum-sil"><input type="hidden" name="event" value="<?= e($ev['id']) ?>"><input type="hidden" name="id" value="<?= e($cm['id']) ?>"><button>Sil</button></form></div>
  <?php endif; ?>
  <?php if (!empty($sub)): ?>
    <ol class="clist clist--sub"><?php foreach ($sub as $__r) { $__keep = $cm; $cm = $__r; $__sub = $sub; $sub = []; include __FILE__; $cm = $__keep; $sub = $__sub; } ?></ol>
  <?php endif; ?>
</li>
