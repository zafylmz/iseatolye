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
// Tek bir kanonik adres: küçük harf, sonda eğik çizgi (aynı sayfanın /Etkinlikler, /etkinlikler gibi kopyaları tek adreste birleşir)
$canonPhp = ['index' => '/', 'etkinlikler' => '/etkinlikler/', 'takvim' => '/takvim/', 'galeri' => '/galeri/', 'mekanlar' => '/mekanlar/', 'blog' => '/blog/', 'kurumsal' => '/kurumsal/', 'hakkimizda' => '/hakkimizda/', 'iletisim' => '/iletisim/', 'gizlilik' => '/gizlilik/', 'katilim-kosullari' => '/katilim-kosullari/', 'mesafeli-satis' => '/mesafeli-satis/'];
$canon = $canonical ?? (str_ends_with($path, '.php') ? ($canonPhp[basename(strtolower($path), '.php')] ?? strtolower($path)) : rtrim(strtolower($path), '/') . '/');
$canonUrl = site_url($canon);
$me = current_user();
$nav = [
  '/etkinlikler/' => 'Etkinlikler',
  '/takvim/' => 'Takvim',
  '/galeri/' => 'Galeri',
  '/kurumsal/' => 'Kurumsal',
  '/hakkimizda/' => 'Hakkımızda',
  '/mekanlar/' => 'Mekanlar',
  '/blog/' => 'Blog',
  '/iletisim/' => 'İletişim',
];
if (!data_exists(DATA . '/blog.json') || !array_filter(json_read(DATA . '/blog.json')['posts'] ?? [], fn($p) => !empty($p['published']))) unset($nav['/blog/']);
// Üst menü sade kalsın: ilk dört bağlantı görünür, diğerleri "Hakkımızda" açılır listesinde (telefonda ikinci grup)
$navMore = array_intersect_key([
  '/hakkimizda/' => 'Biz kimiz, nasıl çalışıyoruz',
  '/mekanlar/' => 'Atölyelerin yapıldığı yerler',
  '/blog/' => 'Yazılar, ipuçları ve duyurular',
  '/iletisim/' => 'Soru, öneri ve iş birlikleri',
], $nav);
$navMain = array_diff_key($nav, $navMore);
$isOn = fn($href) => $href === '/etkinlikler/' ? (str_starts_with($path, '/etkinlik')) : str_starts_with($path, rtrim($href, '/'));
$moreOn = (bool) array_filter(array_keys($navMore), $isOn);
?><!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <link rel="canonical" href="<?= e($canonUrl) ?>">
  <meta name="theme-color" content="#faf6f0">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
  <meta property="og:site_name" content="<?= e($brand) ?>">
  <meta property="og:locale" content="tr_TR">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:url" content="<?= e($canonUrl) ?>">
  <meta property="og:image" content="<?= e(str_starts_with($image, 'http') ? $image : site_url($image)) ?>">
  <meta property="og:image:alt" content="<?= e($imageAlt ?? $title) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($title) ?>">
  <meta name="twitter:description" content="<?= e($description) ?>">
  <meta name="twitter:image" content="<?= e(str_starts_with($image, 'http') ? $image : site_url($image)) ?>">
  <?= $ogExtra ?? '' ?>
  <?php if (!empty($noindex)): ?><meta name="robots" content="noindex, follow"><?php endif; ?>
  <link rel="preload" href="/assets/fonts/montserrat-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/montserrat-latin-ext-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
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
        <div class="nav__main">
          <?php foreach ($navMain as $href => $label): ?>
            <a class="nav__link" href="<?= $href ?>"<?= $isOn($href) ? ' aria-current="page"' : '' ?>><?= $label ?><?= icon('sag') ?></a>
          <?php endforeach; ?>
        </div>
        <div class="nav__group">
          <button class="nav__more<?= $moreOn ? ' is-on' : '' ?>" type="button" aria-expanded="false" aria-controls="nav-more">Hakkımızda<svg viewBox="0 0 12 12" width="10" height="10" aria-hidden="true"><path d="M2.5 4.5L6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.4"/></svg></button>
          <div class="nav__drop" id="nav-more">
            <p class="nav__label">İse Atölye</p>
            <?php foreach ($navMore as $href => $hint): ?>
              <a href="<?= $href ?>"<?= $isOn($href) ? ' aria-current="page"' : '' ?>><strong><?= $nav[$href] ?></strong><span><?= $hint ?></span></a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="nav__member">
          <?php if ($me): ?>
            <p class="nav__label">Hesabım</p>
            <div class="nav__me"><?= avatar($me) ?><span><strong><?= e($me['name']) ?></strong><span><?= e($me['email'] ?? '') ?></span></span></div>
            <div class="nav__acts">
              <a href="/hesabim/"><?= icon('bilet') ?>Etkinliklerim</a>
              <a href="<?= e(user_url($me)) ?>"><?= icon('kisi') ?>Profilim</a>
              <a href="/hesabim/?s=profil"><?= icon('ayar') ?>Hesap ayarları</a>
              <a href="/cikis/"><?= icon('cikis') ?>Çıkış yap</a>
            </div>
          <?php else: ?>
            <p class="nav__note">Üye olun; katıldığınız etkinlikleri, biletlerinizi ve takviminizi tek yerden yönetin.</p>
            <div class="nav__btns"><a class="btn" href="/uye-ol/">Üye ol</a><a class="btn btn--ghost" href="/giris/">Giriş yap</a></div>
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
