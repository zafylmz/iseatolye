<?php /** @var array $p */ $big = $big ?? false; ?>
<a class="post-card<?= $big ? ' post-card--big' : '' ?><?= empty($p['cover']) ? ' post-card--text' : '' ?>" href="/blog/<?= e($p['slug']) ?>/" data-reveal>
  <?php if (!empty($p['cover'])): ?><div class="post-card__media"><img src="<?= e($p['cover']) ?>" alt="" width="1600" height="1000" loading="<?= $big ? 'eager' : 'lazy' ?>" decoding="async"></div><?php endif; ?>
  <div class="post-card__body">
    <p class="post-meta"><?php if (trim($p['category'] ?? '') !== ''): ?><span class="post-meta__cat"><?= e($p['category']) ?></span><?php endif; ?><time datetime="<?= e($p['date']) ?>"><?= e(tr_date($p['date'])) ?></time><span><?= reading_minutes($p['body'] ?? '') ?> dk okuma</span></p>
    <h<?= $big ? 2 : 3 ?> class="post-card__title"><?= e($p['title']) ?></h<?= $big ? 2 : 3 ?>>
    <?php if (trim($p['excerpt'] ?? '') !== ''): ?><p class="post-card__excerpt"><?= e($p['excerpt']) ?></p><?php endif; ?>
    <span class="post-card__more">Yazıyı oku<?= icon('sag') ?></span>
  </div>
</a>
