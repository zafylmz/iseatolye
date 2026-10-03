<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/blog.php';
$c = content();
$all = blog_published();
$cats = blog_categories($all);
$cat = (string) ($_GET['kategori'] ?? '');
if (!isset($cats[$cat])) $cat = '';
$posts = $cat === '' ? $all : array_values(array_filter($all, fn($p) => blog_slug($p['category'] ?? '') === $cat));
$title = ($cat ? $cats[$cat]['name'] . ' yazıları' : 'Blog') . ' · ' . $c['brand']['name'];
$description = 'Atölyelerden notlar, duyurular ve üretim üzerine yazılar.';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head blog-head">
    <p class="label">Blog</p>
    <h1><?= $cat ? e($cats[$cat]['name']) : 'Atölyeden notlar' ?></h1>
    <p class="blog-head__lead">Atölyelerden notlar, duyurular ve üretmek, yavaşlamak, birlikte olmak üzerine yazılar.</p>
  </section>
  <section class="wrap" style="padding-bottom:clamp(64px,9vw,112px)">
    <?php if ($cats): ?>
    <nav class="tabs" aria-label="Kategoriler">
      <a href="/blog/"<?= $cat === '' ? ' aria-current="page"' : '' ?>>Tüm yazılar<span class="count"><?= count($all) ?></span></a>
      <?php foreach ($cats as $k => $info): ?>
        <a href="/blog/?kategori=<?= e($k) ?>"<?= $cat === $k ? ' aria-current="page"' : '' ?>><?= e($info['name']) ?><span class="count"><?= $info['count'] ?></span></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php if (!$posts): ?>
      <p class="empty">Henüz yayınlanmış bir yazı yok.</p>
    <?php else: ?>
      <?php $p = $posts[0]; $big = true; include __DIR__ . '/inc/post-card.php'; $big = false; ?>
      <?php if (count($posts) > 1): ?>
      <div class="posts">
        <?php foreach (array_slice($posts, 1) as $p) include __DIR__ . '/inc/post-card.php'; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
