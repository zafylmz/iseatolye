<?php
// Ortak üst bölüm. Sayfa dosyasında $title, $description, $image, $ogType, $headExtra, $bodyClass tanımlanabilir.
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/events.php';
$c = content();
$brand = $c['brand']['name'] ?? 'İSE ATÖLYE';
$title = $title ?? ($brand . ' · ' . ($c['brand']['tagline'] ?? ''));
$description = $description ?? ($c['brand']['description'] ?? '');
$image = $image ?? (($c['brand']['og_image'] ?? '') ?: '/og.jpg');
$path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$me = current_user();
$nav = [
  '/etkinlikler/' => 'Etkinlikler',
  '/takvim/' => 'Takvim',
  '/galeri/' => 'Galeri',
  '/mekanlar/' => 'Mekanlar',
  '/kurumsal/' => 'Kurumsal',
  '/hakkimizda/' => 'Hakkımızda',
  '/blog/' => 'Blog',
  '/iletisim/' => 'İletişim',
];
if (!is_file(DATA . '/blog.json') || !array_filter(json_read(DATA . '/blog.json')['posts'] ?? [], fn($p) => !empty($p['published']))) unset($nav['/blog/']);
// Galeri, gösterilecek albüm varsa menüde görünür
$hasGallery = (bool) array_filter(catalog()['events'] ?? [], fn($ev) => !empty($ev['gallery']) && ($ev['status'] ?? '') !== 'taslak') || (bool) array_filter(json_read(DATA . '/medya.json')['albums'] ?? [], fn($a) => !empty($a['public']));
if (!$hasGallery) unset($nav['/galeri/']);
$isOn = fn($href) => $href === '/etkinlikler/' ? (str_starts_with($path, '/etkinlik')) : str_starts_with($path, rtrim($href, '/'));
?><!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <link rel="canonical" href="<?= e(site_url($path)) ?>">
  <meta name="theme-color" content="#faf6f0">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
  <meta property="og:site_name" content="<?= e($brand) ?>">
  <meta property="og:locale" content="tr_TR">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:image" content="<?= e(site_url($image)) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php endif; ?>
  <link rel="preload" href="/assets/fonts/montserrat-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/cormorant-garamond-latin-500-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('/assets/style.css')) ?>">
  <?php if (isset($nav['/blog/'])): ?><link rel="alternate" type="application/rss+xml" title="<?= e($brand) ?> · Blog" href="/blog/rss.xml"><?php endif; ?>
  <?= $headExtra ?? '' ?>
  <script>document.documentElement.classList.add('js')</script>
</head>
<body<?= !empty($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
  <a class="skip" href="#icerik">İçeriğe geç</a>
  <?php if (trim($c['announcement']['text'] ?? '') !== '' && !empty($c['announcement']['on'])): ?>
    <div class="announce"><div class="wrap"><?php if (trim($c['announcement']['url'] ?? '') !== ''): ?><a href="<?= e($c['announcement']['url']) ?>"><?= e($c['announcement']['text']) ?><?= icon('sag') ?></a><?php else: ?><span><?= e($c['announcement']['text']) ?></span><?php endif; ?></div></div>
  <?php endif; ?>
  <header class="header">
    <div class="wrap header__bar">
      <a class="brand" href="/" aria-label="<?= e($brand) ?>, ana sayfa"><?php include __DIR__ . '/logo.php'; ?></a>
      <nav class="nav" id="menu" aria-label="Ana menü">
        <?php foreach ($nav as $href => $label): ?>
          <a href="<?= $href ?>"<?= $isOn($href) ? ' aria-current="page"' : '' ?>><?= $label ?></a>
        <?php endforeach; ?>
        <div class="nav__member">
          <?php if ($me): ?>
            <a href="/hesabim/">Hesabım</a><a href="<?= e(user_url($me)) ?>">Profilim</a><a href="/cikis/">Çıkış yap</a>
          <?php else: ?>
            <a class="btn btn--sm" href="/uye-ol/">Üye ol</a><a class="btn btn--ghost btn--sm" href="/giris/">Giriş yap</a>
          <?php endif; ?>
        </div>
      </nav>
      <div class="header__end">
        <?php if ($me): ?>
          <div class="usermenu">
            <button class="usermenu__btn" type="button" aria-expanded="false" aria-controls="usermenu"><?= avatar($me, 'sm') ?><span><?= e(explode(' ', $me['name'])[0]) ?></span></button>
            <div class="usermenu__panel" id="usermenu" hidden>
              <a href="/hesabim/"><?= icon('bilet') ?>Etkinliklerim</a>
              <a href="<?= e(user_url($me)) ?>"><?= icon('kisi') ?>Profilim</a>
              <a href="/hesabim/?s=profil"><?= icon('ayar') ?>Hesap ayarları</a>
              <a href="/cikis/"><?= icon('cikis') ?>Çıkış yap</a>
            </div>
          </div>
        <?php else: ?>
          <a class="header__login" href="/giris/">Giriş yap</a>
          <a class="btn btn--sm" href="/uye-ol/">Üye ol</a>
        <?php endif; ?>
        <button class="menu-btn" type="button" aria-controls="menu" aria-expanded="false" aria-label="Menüyü aç"><span></span><span></span></button>
      </div>
    </div>
  </header>
  <main id="icerik">
