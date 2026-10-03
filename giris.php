<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$donus = safe_return((string) ($_GET['donus'] ?? $_POST['donus'] ?? '/hesabim/'));
if (current_user()) { header('Location: ' . $donus); exit; }
member_session();
$err = '';
$login = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $login = trim((string) ($_POST['login'] ?? ''));
  if (!member_csrf_ok()) $err = 'Oturum süresi doldu. Tekrar deneyin.';
  elseif (!rate_hit('giris:' . client_hash(), 10) || !rate_hit('giris-u:' . substr(hash('sha256', mb_strtolower($login)), 0, 16), 20)) $err = 'Çok fazla deneme yaptınız. Lütfen bir süre sonra tekrar deneyin.';
  else {
    $u = str_contains($login, '@') ? user_by('email', $login) : user_by('username', $login);
    if ($u && password_verify((string) ($_POST['password'] ?? ''), (string) $u['hash'])) {
      if (($u['status'] ?? 'aktif') !== 'aktif') $err = 'Bu üyelik askıya alınmış. Bizimle iletişime geçin.';
      else { user_login($u, !empty($_POST['remember'])); header('Location: ' . $donus, true, 303); exit; }
    } else { sleep(1); $err = 'E-posta/kullanıcı adı ya da şifre hatalı.'; }
  }
}
$title = 'Giriş yap · ' . $c['brand']['name'];
$noindex = true;
$flash = member_flash();
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap auth">
    <form class="auth__box" method="post">
      <p class="label">Üye girişi</p>
      <h1 class="h2">Tekrar hoş geldiniz</h1>
      <?php if ($flash): ?><p class="notice notice--<?= e($flash[1]) ?>"><?= e($flash[0]) ?></p><?php endif; ?>
      <?php if ($err): ?><p class="notice notice--err" role="alert"><?= e($err) ?></p><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e(member_csrf()) ?>"><input type="hidden" name="donus" value="<?= e($donus) ?>">
      <label class="field">E-posta ya da kullanıcı adı<input name="login" value="<?= e($login) ?>" required autocomplete="username" autofocus></label>
      <label class="field">Şifre<input type="password" name="password" required autocomplete="current-password"></label>
      <div class="auth__row"><label class="check"><input type="checkbox" name="remember" value="1" checked> Beni hatırla</label><a href="/sifremi-unuttum/">Şifremi unuttum</a></div>
      <button class="btn btn--block">Giriş yap</button>
      <p class="auth__alt">Hesabınız yok mu? <a href="/uye-ol/?donus=<?= e(rawurlencode($donus)) ?>">Ücretsiz üye olun</a></p>
    </form>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
