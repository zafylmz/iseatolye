<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/blog.php';
$c = content();
$slug = (string) ($_GET['slug'] ?? '');
$post = blog_find($slug);
if (!$post || empty($post['published'])) {
  http_response_code(404);
  include __DIR__ . '/404.php';
  exit;
}
$all = blog_published();
$idx = array_search($slug, array_column($all, 'slug'), true);
$newer = $idx > 0 ? $all[$idx - 1] : null;
$older = $all[$idx + 1] ?? null;
$cats = blog_categories($all);
$bodyHtml = blog_render($post['body'] ?? '', $toc);
$comments = blog_comments($slug);
$likes = blog_like_count($slug);
$liked = blog_liked($slug);
$open = ($post['comments'] ?? true) !== false;
$ts = time();
$site = site_url();

$title = $post['title'] . ' · ' . $c['brand']['name'];
$description = trim($post['excerpt'] ?? '') ?: mb_substr(blog_plain($post['body'] ?? ''), 0, 160);
$image = $post['cover'] ?: '/og.jpg';
$ogType = 'article';
$ld = [
  '@context' => 'https://schema.org', '@type' => 'BlogPosting',
  'headline' => $post['title'], 'description' => $description,
  'datePublished' => $post['date'], 'dateModified' => $post['updated'] ?? $post['date'],
  'image' => $site . $image, 'mainEntityOfPage' => $site . '/blog/' . $slug . '/',
  'author' => ['@type' => 'Organization', 'name' => $c['brand']['name'], 'url' => $site . '/'],
];
$headExtra = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
include __DIR__ . '/inc/header.php';
?>
  <div class="read-progress" aria-hidden="true"><span></span></div>
  <div class="wrap post-layout">
    <article class="post" aria-labelledby="post-title">
      <a class="back" href="/blog/"><?= icon('sol') ?>Tüm yazılar</a>
      <p class="post-meta"><?php if (trim($post['category'] ?? '') !== ''): ?><a class="post-meta__cat" href="/blog/?kategori=<?= e(blog_slug($post['category'])) ?>"><?= e($post['category']) ?></a><?php endif; ?><time datetime="<?= e($post['date']) ?>"><?= e(tr_date($post['date'])) ?></time><span><?= reading_minutes($post['body'] ?? '') ?> dk okuma</span></p>
      <h1 id="post-title"><?= e($post['title']) ?></h1>
      <?php if (trim($post['excerpt'] ?? '') !== ''): ?><p class="post__lead"><?= e($post['excerpt']) ?></p><?php endif; ?>
      <div class="post__author">
        <span class="avatar avatar--sm avatar--brand" aria-hidden="true">İ</span>
        <span><strong><?= e($c['brand']['name']) ?></strong><span>Atölyeden notlar</span></span>
      </div>
      <?php if (!empty($post['cover'])): ?><figure class="post__cover"><img src="<?= e($post['cover']) ?>" alt="" width="1600" height="1000"></figure><?php endif; ?>
      <div class="post__body" data-post-body><?= $bodyHtml ?></div>

      <div class="post__actions">
        <form class="like" method="post" action="/blog-api.php" data-like>
          <input type="hidden" name="action" value="like"><input type="hidden" name="slug" value="<?= e($slug) ?>">
          <button type="submit" class="like__btn" aria-pressed="<?= $liked ? 'true' : 'false' ?>" aria-label="Yazıyı beğen">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.3-9.2C1.4 8 3.4 4.5 6.9 4.5c2 0 3.6 1.1 5.1 3 1.5-1.9 3.1-3 5.1-3 3.5 0 5.5 3.5 4.2 6.8-1.8 4.6-9.3 9.2-9.3 9.2z"/></svg>
            <span data-like-count><?= $likes ?></span>
          </button>
          <span class="like__hint" data-like-hint><?= $liked ? 'Beğendiniz, teşekkürler.' : 'Beğendiyseniz kalbe dokunun.' ?></span>
        </form>
        <div class="share">
          <span>Paylaş</span>
          <a href="https://wa.me/?text=<?= rawurlencode($post['title'] . ' ' . $site . '/blog/' . $slug . '/') ?>" target="_blank" rel="noopener">WhatsApp</a>
          <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= rawurlencode($site . '/blog/' . $slug . '/') ?>" target="_blank" rel="noopener">LinkedIn</a>
          <a href="https://x.com/intent/post?url=<?= rawurlencode($site . '/blog/' . $slug . '/') ?>&amp;text=<?= rawurlencode($post['title']) ?>" target="_blank" rel="noopener">X</a>
          <button type="button" data-copy="<?= e($site . '/blog/' . $slug . '/') ?>">Bağlantıyı kopyala</button>
        </div>
      </div>

      <?php if ($newer || $older): ?>
      <nav class="post-nav" aria-label="Önceki ve sonraki yazı">
        <?php if ($older): ?><a class="post-nav__item" href="/blog/<?= e($older['slug']) ?>/"><span><?= icon('sol') ?>Önceki yazı</span><strong><?= e($older['title']) ?></strong></a><?php else: ?><span></span><?php endif; ?>
        <?php if ($newer): ?><a class="post-nav__item post-nav__item--next" href="/blog/<?= e($newer['slug']) ?>/"><span>Sonraki yazı<?= icon('sag') ?></span><strong><?= e($newer['title']) ?></strong></a><?php endif; ?>
      </nav>
      <?php endif; ?>
    </article>

    <aside class="post-side" aria-label="Diğer yazılar">
      <div class="side-box">
        <label class="side-search">
          <span class="sr">Yazılarda ara</span>
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
          <input type="search" placeholder="Yazılarda ara" data-side-search autocomplete="off">
        </label>
        <p class="side-title">Tüm yazılar</p>
        <ol class="side-list" data-side-list>
          <?php foreach ($all as $p): ?>
            <li><a href="/blog/<?= e($p['slug']) ?>/"<?= $p['slug'] === $slug ? ' aria-current="page"' : '' ?>><strong><?= e($p['title']) ?></strong><span><?= e(tr_date($p['date'])) ?> · <?= reading_minutes($p['body'] ?? '') ?> dk</span></a></li>
          <?php endforeach; ?>
        </ol>
        <p class="side-empty" data-side-empty hidden>Aramanıza uygun yazı yok.</p>
      </div>
      <?php if (count($toc) > 1): ?>
      <div class="side-box side-box--toc">
        <p class="side-title">Bu yazıda</p>
        <ol class="toc" data-toc>
          <?php foreach ($toc as [$id, $text]): ?><li><a href="#<?= e($id) ?>"><?= e($text) ?></a></li><?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>
      <?php if (count($cats) > 1): ?>
      <div class="side-box">
        <p class="side-title">Kategoriler</p>
        <ul class="side-cats">
          <?php foreach ($cats as $k => $info): ?><li><a href="/blog/?kategori=<?= e($k) ?>"><?= e($info['name']) ?><span><?= $info['count'] ?></span></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </aside>

    <section class="comments" id="yorumlar" aria-labelledby="comments-title">
      <h2 id="comments-title">Yorumlar<?php if ($comments): ?> <span class="count"><?= count($comments) ?></span><?php endif; ?></h2>
      <?php if ($comments): ?>
      <ol class="comment-list">
        <?php foreach ($comments as $cm): ?>
          <li class="comment" id="yorum-<?= e($cm['id']) ?>">
            <div class="comment__head"><span class="comment__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($cm['name'], 0, 1))) ?></span><strong><?= e($cm['name']) ?></strong><time datetime="<?= e($cm['date']) ?>"><?= e(tr_date($cm['date'])) ?></time></div>
            <div class="comment__text"><?= paragraphs($cm['text']) ?></div>
            <?php if (trim($cm['reply'] ?? '') !== ''): ?>
              <div class="comment comment--reply">
                <div class="comment__head"><span class="comment__avatar comment__avatar--me" aria-hidden="true">İ</span><strong><?= e($c['brand']['name']) ?></strong><span class="comment__badge">Yazar</span></div>
                <div class="comment__text"><?= paragraphs($cm['reply']) ?></div>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php else: ?>
        <p class="muted">Henüz yorum yok. İlk yorumu siz yazın.</p>
      <?php endif; ?>

      <?php if ($open): ?>
      <form class="comment-form" method="post" action="/blog-api.php" data-guard-form novalidate>
        <h3>Yorum yazın</h3>
        <p class="comment-form__note">Yorumlar onaylandıktan sonra yayınlanır. E-posta adresiniz yayınlanmaz.</p>
        <input type="hidden" name="action" value="comment">
        <input type="hidden" name="slug" value="<?= e($slug) ?>">
        <?= guard_fields('blog|' . $slug) ?>
        <div class="comment-form__row">
          <label>Adınız<input name="name" required maxlength="60" autocomplete="name"></label>
          <label><span>E-posta <span class="opt">(isteğe bağlı)</span></span><input type="email" name="email" maxlength="120" autocomplete="email"></label>
        </div>
        <label>Yorumunuz<textarea name="text" rows="5" required maxlength="2000"></textarea></label>
        <div class="comment-form__foot">
          <button class="btn" type="submit">Yorumu gönder</button>
          <p class="comment-form__msg" role="status" data-form-msg></p>
        </div>
        <noscript><p class="comment-form__msg is-err">Yorum gönderebilmek için tarayıcınızda JavaScript açık olmalı.</p></noscript>
      </form>
      <?php else: ?>
        <p class="muted">Bu yazı yorumlara kapalı.</p>
      <?php endif; ?>
    </section>
  </div>
<?php include __DIR__ . '/inc/footer.php'; ?>
