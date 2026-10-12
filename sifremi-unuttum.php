<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
$c = content();
member_session();
$sent = false; $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $email = trim((string) ($_POST['email'] ?? ''));
  $g = guard_check('sifre');
  // Bota başarılı gibi görünülür
  if ($g === 'bot') $sent = true;
  elseif ($g !== '') $err = $g;
  elseif (!member_csrf_ok()) $err = 'Oturum süresi doldu. Tekrar deneyin.';
  elseif (!rate_hit('sifre:' . client_hash(), 5, 20)) $err = 'Çok fazla deneme yaptınız. Biraz sonra tekrar deneyin.';
  else {
    $u = user_by('email', $email);
    // Aynı adrese saatte en fazla 3 yenileme e-postası gider (farklı yerlerden art arda istense bile)
    if ($u && ($u['status'] ?? 'aktif') === 'aktif' && rate_hit('sifre-e:' . substr(hash('sha256', mb_strtolower($email)), 0, 16), 3)) {
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
    <form class="auth__box" method="post"<?= $sent ? '' : ' data-guard-form' ?>>
      <p class="label">Şifre yenileme</p>
      <h1 class="h2">Şifrenizi mi unuttunuz?</h1>
      <?php if ($sent): ?>
        <p class="notice notice--ok">Bu e-posta ile bir üyelik varsa şifre yenileme bağlantısı gönderdik. Gelen kutunuzu (ve gereksiz klasörünü) kontrol edin.</p>
        <a class="btn btn--ghost btn--block" href="/giris/">Girişe dön</a>
      <?php else: ?>
        <p class="muted">Üye olurken kullandığınız e-posta adresini yazın, size yenileme bağlantısı gönderelim.</p>
        <?php if ($err): ?><p class="notice notice--err"><?= e($err) ?></p><?php endif; ?>
        <input type="hidden" name="csrf" value="<?= e(member_csrf()) ?>">
        <?= guard_fields('sifre') ?>
        <label class="field">E-posta<input type="email" name="email" required autocomplete="email" autofocus></label>
        <button class="btn btn--block" type="submit">Bağlantı gönder</button>
        <p class="cform__msg" role="status" data-form-msg></p>
        <p class="auth__alt"><a href="/giris/">Girişe dön</a></p>
      <?php endif; ?>
    </form>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
