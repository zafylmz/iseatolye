<?php
// İSE ATÖLYE yönetim paneli: etkinlikler, katılımlar, üyeler, içerik ve ayarlar.
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_once ROOT . '/inc/blog.php';
require_once ROOT . '/inc/stats.php';
require_once ROOT . '/inc/geo.php';

header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// Panel sahibinin tarayıcısını sayaçta saymamak için çerez (site geneli, 1 yıl).
function skip_cookie(bool $on): void {
  setcookie(STATS_SKIP_COOKIE, $on ? '1' : '', ['expires' => $on ? time() + 31536000 : time() - 3600, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => https()]);
}

$view = preg_replace('/[^a-z-]/', '', (string) ($_GET['s'] ?? 'pano')) ?: 'pano';
$action = (string) ($_POST['action'] ?? '');

// ---------- Giriş, ilk kurulum, çıkış ----------
if (!has_password()) {
  if ($action === 'setup') {
    check_csrf();
    $key = setup_key();
    if ($key === '') { flash('data klasörüne yazılamıyor. Dosya Yöneticisi\'nde data klasörünün izinlerini 755 yapın.', 'err'); redirect('./'); }
    if (!rate_hit('kurulum:' . client_hash(), 10)) { flash('Çok fazla deneme yapıldı. Bir saat sonra tekrar deneyin.', 'err'); redirect('./'); }
    if (!hash_equals($key, strtoupper(trim((string) ($_POST['key'] ?? ''))))) { sleep(1); flash('Kurulum anahtarı hatalı. data/kurulum-anahtari.txt dosyasındaki 8 karakteri yazın.', 'err'); redirect('./'); }
    $p1 = (string) ($_POST['p1'] ?? ''); $p2 = (string) ($_POST['p2'] ?? '');
    if (mb_strlen($p1) < 12) { flash('Panel şifresi en az 12 karakter olmalı.', 'err'); redirect('./'); }
    if ($p1 !== $p2) { flash('Şifreler aynı değil.', 'err'); redirect('./'); }
    try { save_password($p1); } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); redirect('./'); }
    @unlink(SETUP_KEY_FILE);
    session_regenerate_id(true);
    $_SESSION['ok'] = true;
    skip_cookie(true);
    flash('Şifreniz oluşturuldu. Panele hoş geldiniz.');
    redirect('./');
  }
  setup_key();
  $view = 'setup';
} elseif (!logged_in()) {
  if ($action === 'login') {
    check_csrf();
    // Bir saatte 10 hatalı denemeden sonra giriş geçici olarak kapanır. Deneme hakkı şifre kontrolünden önce ayrılır,
    // böylece aynı anda gönderilen istekler sınırı aşamaz.
    if (!rate_hit('panel:' . client_hash(), 10)) { flash('Çok fazla hatalı deneme yapıldı. Bir saat sonra tekrar deneyin.', 'err'); redirect('./'); }
    if (password_verify((string) ($_POST['password'] ?? ''), password_hash_stored())) {
      // Başarılı girişte bu adresin deneme kaydı silinir
      json_update(RATE_FILE, function (array &$r) { unset($r['panel:' . client_hash()]); });
      session_regenerate_id(true);
      $_SESSION['ok'] = true;
      $_SESSION['pv'] = auth_version();
      if (!isset($_COOKIE[STATS_SKIP_COOKIE])) skip_cookie(true);
      redirect('./' . (preg_match('/^[a-z-]+$/', (string) ($_POST['s'] ?? '')) ? '?s=' . $_POST['s'] : ''));
    }
    sleep(2); // kaba kuvvet denemelerini yavaşlatır
    flash('Şifre hatalı.', 'err');
    redirect('./');
  }
  $view = 'login';
} elseif ($view === 'cikis') {
  $_SESSION = [];
  session_destroy();
  redirect('./');
}

// ---------- Kaydetme işlemleri ----------
if (logged_in() && $action !== '') {
  check_csrf();
  try {
    require __DIR__ . '/actions.php';
  } catch (RuntimeException $ex) {
    flash($ex->getMessage(), 'err');
    $_SESSION['old_post'] = $action === 'etkinlik' ? $_POST : null;
    redirect('./?' . http_build_query($_GET));
  }
}

$c = content();
$tok = csrf();

// Başlıksız çıktılar: CSV ve yazdırılabilir yoklama listesi
if (logged_in() && in_array($view, ['csv', 'yoklama'], true)) {
  require __DIR__ . '/views/' . $view . '.php';
  exit;
}
// Görsel seçici penceresinin içeriği
if (logged_in() && $view === 'galeri' && !empty($_GET['parca'])) {
  header('Cache-Control: no-store');
  require __DIR__ . '/views/galeri.php';
  exit;
}

$f = flash();
$views = ['pano', 'etkinlikler', 'etkinlik', 'katilimlar', 'kayit', 'mekanlar', 'uyeler', 'uye', 'yorumlar', 'mesajlar', 'blog', 'yazi', 'galeri', 'sayfalar', 'ayarlar', 'istatistik', 'kontrol', 'sifre', 'faturalar'];
if (!in_array($view, [...$views, 'setup', 'login'], true)) $view = 'pano';

// Rozetler: bekleyen işler
$regs = regs_all();
$pendingRegs = count(array_filter($regs, fn($r) => $r['status'] === 'beklemede'));
$pendingCm = 0;
foreach (json_read(COMMENTS_FILE) as $list) foreach ($list as $cm) if (($cm['status'] ?? '') === 'beklemede') $pendingCm++;
foreach (json_read(BLOG_COMMENTS_FILE) as $list) foreach ($list as $cm) if (($cm['status'] ?? '') !== 'approved') $pendingCm++;
$unread = count(array_filter(json_read(MESSAGES_FILE, ['messages' => []])['messages'] ?? [], fn($m) => empty($m['read'])));
$badge = fn(int $n) => $n ? ' <span class="badge">' . $n . '</span>' : '';

$nav = [
  'Genel' => ['pano' => 'Pano'],
  'Etkinlik' => ['etkinlikler' => 'Etkinlikler', 'katilimlar' => 'Katılımlar' . $badge($pendingRegs), 'faturalar' => 'Faturalar' . $badge(count(array_filter($regs, 'invoice_due'))), 'mekanlar' => 'Mekan ve eğitmenler'],
  'Topluluk' => ['uyeler' => 'Üyeler', 'yorumlar' => 'Yorumlar' . $badge($pendingCm), 'mesajlar' => 'Mesajlar' . $badge($unread)],
  'Site' => ['galeri' => 'Galeri', 'blog' => 'Blog', 'sayfalar' => 'Sayfalar', 'ayarlar' => 'Ayarlar', 'istatistik' => 'İstatistikler', 'kontrol' => 'Sistem kontrolü', 'sifre' => 'Şifre'],
];
$activeTab = ['etkinlik' => 'etkinlikler', 'kayit' => 'katilimlar', 'uye' => 'uyeler', 'yazi' => 'blog'][$view] ?? $view;
$titles = ['pano' => 'Pano', 'etkinlikler' => 'Etkinlikler', 'etkinlik' => 'Etkinlik', 'katilimlar' => 'Katılımlar', 'kayit' => 'Kayıt', 'mekanlar' => 'Mekan ve eğitmenler', 'uyeler' => 'Üyeler', 'uye' => 'Üye', 'yorumlar' => 'Yorumlar', 'mesajlar' => 'Mesajlar', 'blog' => 'Blog', 'yazi' => 'Yazı', 'galeri' => 'Galeri', 'sayfalar' => 'Sayfalar', 'ayarlar' => 'Ayarlar', 'istatistik' => 'İstatistikler', 'kontrol' => 'Sistem kontrolü', 'sifre' => 'Şifre', 'faturalar' => 'Faturalar', 'setup' => 'Kurulum', 'login' => 'Giriş'];
?><!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf" content="<?= e($tok) ?>">
  <title><?= e($titles[$view] ?? 'Panel') ?> · <?= e($c['brand']['name']) ?> paneli</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="preload" href="/assets/fonts/montserrat-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('/yonetim/panel.css')) ?>">
</head>
<body>
<?php if ($view === 'setup' || $view === 'login'): ?>
  <main class="auth">
    <form class="box" method="post">
      <span class="logo">İSE ATÖLYE</span>
      <h1><?= $view === 'setup' ? 'Panel şifresi oluşturun' : 'Yönetim paneli' ?></h1>
      <?php if ($f): ?><p class="flash flash--<?= e($f[1]) ?>"><?= e($f[0]) ?></p><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e($tok) ?>">
      <?php if ($view === 'setup'): ?>
        <p class="hint">İlk girişte bir şifre belirleyin. En az 8 karakter olmalı. Bu şifre yalnızca sizdedir; üyelerin şifrelerinden ayrıdır.</p>
        <p class="hint">Güvenlik için önce kurulum anahtarını yazın: cPanel Dosya Yöneticisi'nde <b>data/kurulum-anahtari.txt</b> dosyasını açın, içindeki 8 karakteri kopyalayın.</p>
        <input type="hidden" name="action" value="setup">
        <label>Kurulum anahtarı<input name="key" required autocomplete="off" maxlength="8" style="text-transform:uppercase"></label>
        <label>Şifre<input type="password" name="p1" minlength="8" required autocomplete="new-password"></label>
        <label>Şifre (tekrar)<input type="password" name="p2" minlength="8" required autocomplete="new-password"></label>
        <button class="btn">Şifreyi kaydet</button>
      <?php else: ?>
        <input type="hidden" name="action" value="login"><input type="hidden" name="s" value="<?= e(preg_replace('/[^a-z-]/', '', (string) ($_GET['s'] ?? ''))) ?>">
        <label>Şifre<input type="password" name="password" required autofocus autocomplete="current-password"></label>
        <button class="btn">Giriş yap</button>
      <?php endif; ?>
    </form>
  </main>
<?php else: ?>
  <div class="shell">
    <aside class="side" id="panel-menu">
      <a class="side__brand" href="./"><span class="logo">İSE ATÖLYE</span><small>Yönetim</small></a>
      <nav class="side__nav" aria-label="Panel menüsü">
        <?php foreach ($nav as $group => $items): ?>
          <p class="side__group"><?= e($group) ?></p>
          <?php foreach ($items as $k => $l): ?><a href="./?s=<?= $k ?>"<?= $activeTab === $k ? ' aria-current="page"' : '' ?>><?= $l ?></a><?php endforeach; ?>
        <?php endforeach; ?>
      </nav>
      <div class="side__end"><a href="/" target="_blank" rel="noopener">Siteyi aç ↗</a><a href="./?s=cikis">Çıkış</a></div>
    </aside>
    <div class="main">
      <header class="topbar">
        <button class="menu-toggle" type="button" aria-controls="panel-menu" aria-expanded="false">Menü</button>
        <span class="logo">İSE ATÖLYE</span>
        <a href="./?s=etkinlik" class="btn btn--sm">Yeni etkinlik</a>
      </header>
      <main class="content">
        <?php if ($f): ?><p class="flash flash--<?= e($f[1]) ?>"><?= e($f[0]) ?></p><?php endif; ?>
        <?php require __DIR__ . '/views/' . $view . '.php'; ?>
      </main>
    </div>
  </div>
  <script src="<?= e(asset('/yonetim/panel.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
