<?php
// Etkinlikler, mekanlar ve yazılar eklendikçe kendiliğinden güncellenen site haritası.
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/events.php';
$urls = [];
foreach (['/', '/etkinlikler/', '/takvim/', '/mekanlar/', '/kurumsal/', '/hakkimizda/', '/iletisim/', '/katilim-kosullari/', '/gizlilik/', '/mesafeli-satis/'] as $u) $urls[$u] = '';
foreach (events_all() as $ev) if (event_visible($ev)) $urls[event_url($ev)] = (string) ($ev['updated'] ?? '') ?: (string) ($ev['created'] ?? '');
foreach (venues() as $v) $urls[venue_url($v)] = '';
require_once __DIR__ . '/inc/media.php';
$albums = gallery_albums();
if ($albums) $urls['/galeri/'] = '';
foreach ($albums as $a) $urls['/galeri/' . rawurlencode($a['slug']) . '/'] = '';
if (data_exists(DATA . '/blog.json')) {
  require_once __DIR__ . '/inc/blog.php';
  if (blog_published()) $urls['/blog/'] = '';
  foreach (blog_published() as $p) $urls['/blog/' . rawurlencode($p['slug']) . '/'] = (string) ($p['updated'] ?? '') ?: (string) ($p['date'] ?? '');
}
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($urls as $u => $lm) {
  $ts = $lm !== '' ? strtotime($lm) : false;
  echo '  <url><loc>', htmlspecialchars(site_url($u), ENT_XML1), '</loc>', $ts ? '<lastmod>' . date('c', $ts) . '</lastmod>' : '', "</url>\n";
}
echo "</urlset>\n";
