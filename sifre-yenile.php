<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
$c = content();
member_session();
$tok = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$entry = json_read(RESETS_FILE)[hash('sha256', $tok)] ?? null;
$valid = $tok !== '' && $entry && ($entry['exp'] ?? 0) > time() && user_by_id($entry['user']);
$err = '';
if ($valid && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $p1 = (string) ($_POST['p1'] ?? ''); $p2 = (string) ($_POST['p2'] ?? '');
  if (!member_csrf_ok()) $err = 'Oturum süresi doldu. Tekrar deneyin.';
  elseif (mb_strlen($p1) < 8) $err = 'Şifre en az 8 karakter olmalı.';
  elseif ($p1 !== $p2) $err = 'Şifreler aynı değil.';
  else {
    json_update(USERS_FILE, function (array &$d) use ($entry, $p1) { foreach ($d['users'] as &$u) if ($u['id'] === $entry['user']) { $u['hash'] = password_hash($p1, PASSWORD_DEFAULT); $u['tokens'] = []; } }, ['users' => []]);
    json_update(RESETS_FILE, function (array &$d) use ($tok) { unset($d[hash('sha256', $tok)]); });
    member_flash('Şifreniz yenilendi. Yeni şifrenizle giriş yapabilirsiniz.');
    header('Location: /giris/', true, 303);
    exit;
  }
}
$title = 'Yeni şifre · ' . $c['brand']['name'];
$noindex = true;
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap auth">
    <form class="auth__box" method="post">
      <p class="label">Şifre yenileme</p>
      <h1 class="h2">Yeni şifrenizi belirleyin</h1>
      <?php if (!$valid): ?>
        <p class="notice notice--err">Bu bağlantının süresi dolmuş ya da geçersiz.</p>
        <a class="btn btn--block" href="/sifremi-unuttum/">Yeni bağlantı iste</a>
      <?php else: ?>
        <?php if ($err): ?><p class="notice notice--err"><?= e($err) ?></p><?php endif; ?>
        <input type="hidden" name="csrf" value="<?= e(member_csrf()) ?>"><input type="hidden" name="t" value="<?= e($tok) ?>">
        <label class="field">Yeni şifre<input type="password" name="p1" minlength="8" required autocomplete="new-password"></label>
        <label class="field">Yeni şifre (tekrar)<input type="password" name="p2" minlength="8" required autocomplete="new-password"></label>
        <button class="btn btn--block">Şifreyi kaydet</button>
      <?php endif; ?>
    </form>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
