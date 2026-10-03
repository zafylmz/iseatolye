<?php
// Blog RSS akışı: /blog/rss.xml
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/blog.php';
$c = content();
$base = site_url();
$x = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= $x($c['brand']['name'] . ' · Blog') ?></title>
  <link><?= $base ?>/blog/</link>
  <atom:link href="<?= $base ?>/blog/rss.xml" rel="self" type="application/rss+xml"/>
  <description>Atölyelerden notlar ve duyurular.</description>
  <language>tr</language>
<?php foreach (array_slice(blog_published(), 0, 20) as $p): $url = $base . '/blog/' . $p['slug'] . '/'; ?>
  <item>
    <title><?= $x($p['title']) ?></title>
    <link><?= $x($url) ?></link>
    <guid><?= $x($url) ?></guid>
    <pubDate><?= date(DATE_RSS, strtotime($p['date']) ?: time()) ?></pubDate>
    <description><?= $x(trim($p['excerpt'] ?? '') ?: mb_substr(blog_plain($p['body'] ?? ''), 0, 300)) ?></description>
  </item>
<?php endforeach; ?>
</channel>
</rss>
