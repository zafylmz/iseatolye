      <div class="bar"><h1>Blog yazıları</h1><a class="btn" href="./?s=yazi">Yeni yazı</a></div>
      <p class="hint">Taslaklar sitede görünmez. Yayındaki yazılar <a href="/blog/" target="_blank" rel="noopener">/blog/</a> sayfasında en yeni tarihli en üstte listelenir.</p>
      <?php $posts = blog_all(); usort($posts, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? '')); $allComments = json_read(BLOG_COMMENTS_FILE); $allLikes = json_read(BLOG_LIKES_FILE); ?>
      <?php if (!$posts): ?><section class="card"><p class="hint">Henüz yazı yok. "Yeni yazı" ile ilk yazınızı ekleyin.</p></section><?php endif; ?>
      <ul class="plist">
        <?php foreach ($posts as $p): $cms = $allComments[$p['slug']] ?? []; $pend = count(array_filter($cms, fn($x) => ($x['status'] ?? '') !== 'approved')); ?>
          <li class="card">
            <?php if (!empty($p['cover'])): ?><img class="thumb" src="<?= e($p['cover']) ?>" alt=""><?php else: ?><span class="thumb thumb--empty">Aa</span><?php endif; ?>
            <div class="plist__info">
              <strong><?= e($p['title']) ?></strong>
              <span><span class="pill<?= !empty($p['published']) ? ' pill--on' : '' ?>"><?= !empty($p['published']) ? 'Yayında' : 'Taslak' ?></span> <?= e(tr_date($p['date'] ?? '')) ?><?= trim($p['category'] ?? '') !== '' ? ' · ' . e($p['category']) : '' ?> · ♥ <?= (int) ($allLikes[$p['slug']]['count'] ?? 0) ?> · <?= count($cms) ?> yorum<?= $pend ? ' (<b>' . $pend . ' onay bekliyor</b>)' : '' ?></span>
            </div>
            <div class="plist__act">
              <?php if (!empty($p['published'])): ?><a class="btn btn--ghost" href="/blog/<?= e($p['slug']) ?>/" target="_blank" rel="noopener">Gör</a><?php endif; ?>
              <a class="btn btn--ghost" href="./?s=yazi&amp;slug=<?= e(urlencode($p['slug'])) ?>">Düzenle</a>
              <form method="post" onsubmit="return confirm('Bu yazıyı, yorumları ve beğenileriyle birlikte silmek istediğinize emin misiniz?')"><input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="yazi-sil"><input type="hidden" name="slug" value="<?= e($p['slug']) ?>"><button class="btn btn--danger">Sil</button></form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

