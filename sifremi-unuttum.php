<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
$c = content();
member_session();
$sent = false; $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!member_csrf_ok()) $err = 'Oturum süresi doldu. Tekrar deneyin.';
  elseif (!rate_hit('sifre:' . client_hash(), 5, 20)) $err = 'Çok fazla deneme yaptınız. Biraz sonra tekrar deneyin.';
  else {
    $u = user_by('email', trim((string) ($_POST['email'] ?? '')));
    if ($u && ($u['status'] ?? 'aktif') === 'aktif') {
      $tok = bin2hex(random_bytes(24));
      json_update(RESETS_FILE, function (array &$d) use ($u, $tok) {
        foreach ($d as $k => $x) if (($x['exp'] ?? 0) < time() || $x['user'] === $u['id']) unset($d[$k]);
        $d[hash('sha256', $tok)] = ['user' => $u['id'], 'exp' => time() + 3600];
      });
      send_mail($u['email'], 'Şifre yenileme', "Merhaba " . $u['name'] . ",\n\nŞifrenizi yenilemek için aşağıdaki bağlantıyı 1 saat içinde açın:\n" . site_url('/sifre-yenile/?t=' . $tok) . "\n\nBu isteği siz yapmadıysanız bu e-postayı yok sayabilirsiniz.");
    }
    $sent = true;
  }
}
$title = 'Şifremi unuttum · ' . $c['brand']['name'];
$noindex = true;
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap auth">
    <form class="auth__box" method="post">
      <p class="label">Şifre yenileme</p>
      <h1 class="h2">Şifrenizi mi unuttunuz?</h1>
      <?php if ($sent): ?>
        <p class="notice notice--ok">Bu e-posta ile bir üyelik varsa şifre yenileme bağlantısı gönderdik. Gelen kutunuzu (ve gereksiz klasörünü) kontrol edin.</p>
        <a class="btn btn--ghost btn--block" href="/giris/">Girişe dön</a>
      <?php else: ?>
        <p class="muted">Üye olurken kullandığınız e-posta adresini yazın, size yenileme bağlantısı gönderelim.</p>
        <?php if ($err): ?><p class="notice notice--err"><?= e($err) ?></p><?php endif; ?>
        <input type="hidden" name="csrf" value="<?= e(member_csrf()) ?>">
        <label class="field">E-posta<input type="email" name="email" required autocomplete="email" autofocus></label>
        <button class="btn btn--block">Bağlantı gönder</button>
        <p class="auth__alt"><a href="/giris/">Girişe dön</a></p>
      <?php endif; ?>
    </form>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
