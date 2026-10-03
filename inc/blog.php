<?php
// Blog yardımcıları: yazılar, yorumlar ve beğeniler data/ altındaki ayrı JSON dosyalarında tutulur.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const BLOG_FILE = DATA . '/blog.json';
const BLOG_COMMENTS_FILE = DATA . '/blog-comments.json';
const BLOG_LIKES_FILE = DATA . '/blog-likes.json';
const COMMENT_MAX_LINKS = 1;
const COMMENT_GAP = 60;
const COMMENT_PER_HOUR = 5;

function blog_all(): array {
  static $posts = null;
  if ($posts === null) $posts = json_read(BLOG_FILE, ['posts' => []])['posts'] ?? [];
  return $posts;
}

function blog_published(): array {
  $list = array_values(array_filter(blog_all(), fn($p) => !empty($p['published'])));
  usort($list, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
  return $list;
}

function blog_find(string $slug): ?array {
  foreach (blog_all() as $p) if (($p['slug'] ?? '') === $slug) return $p;
  return null;
}

function blog_slug(string $s): string { return slugify($s, ''); }
function blog_render(string $body, ?array &$toc = null): string { return md($body, $toc); }
function blog_plain(string $body): string { return md_plain($body); }

function blog_categories(array $posts): array {
  $out = [];
  foreach ($posts as $p) {
    $name = trim($p['category'] ?? '');
    if ($name === '') continue;
    $k = blog_slug($name);
    $out[$k] ??= ['name' => $name, 'count' => 0];
    $out[$k]['count']++;
  }
  return $out;
}

function reading_minutes(string $body): int {
  $words = count(preg_split('/\s+/u', trim(strip_tags($body))) ?: []);
  return max(1, (int) ceil($words / 180));
}

function blog_comments(string $slug, bool $approvedOnly = true): array {
  $all = json_read(BLOG_COMMENTS_FILE)[$slug] ?? [];
  return array_values(array_filter($all, fn($c) => !$approvedOnly || ($c['status'] ?? '') === 'approved'));
}

function blog_like_count(string $slug): int {
  return (int) (json_read(BLOG_LIKES_FILE)[$slug]['count'] ?? 0);
}

function liked_cookie(): array {
  return array_filter(explode(',', (string) ($_COOKIE['ise_begeni'] ?? '')), fn($s) => preg_match('/^[a-z0-9-]{1,120}$/', $s));
}

function blog_liked(string $slug): bool {
  if (in_array($slug, liked_cookie(), true)) return true;
  return in_array(client_hash(), json_read(BLOG_LIKES_FILE)[$slug]['ips'] ?? [], true);
}
