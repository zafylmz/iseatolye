<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$donus = safe_return((string) ($_GET['donus'] ?? $_POST['donus'] ?? '/hesabim/'));
$verifyErr = false;
// E-postadaki doğrulama bağlantısı: üyelik açılır, giriş yapılır, hoş geldin e-postası gider
if (($t = (string) ($_GET['t'] ?? '')) !== '') {
  if ($res = verify_user_token($t)) {
    [$u, $to] = $res;
    user_login($u, true);
    send_mail($u['email'], $c['brand']['name'] . ' topluluğuna hoş geldiniz', "Merhaba " . $u['name'] . ",\n\nÜyeliğiniz oluşturuldu. Artık etkinliklere birkaç adımda katılabilir, yorum yazabilir ve katılmayı düşündüğünüz etkinlikleri işaretleyebilirsiniz.\n\nProfiliniz: " . site_url(user_url($u)) . "\nYaklaşan etkinlikler: " . site_url('/etkinlikler/'));
    member_flash('Hoş geldiniz ' . explode(' ', $u['name'])[0] . '! E-postanız doğrulandı, üyeliğiniz açıldı.');
    header('Location: ' . ($to === '/hesabim/' ? '/hesabim/?s=profil&yeni=1' : $to), true, 303);
    exit;
  }
  $verifyErr = true;
}
if (current_user()) { header('Location: ' . $donus); exit; }
member_session();
$errors = [];
$pending = '';
$old = ['name' => '', 'email' => '', 'phone' => ''];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  foreach ($old as $k => $_) $old[$k] = trim((string) ($_POST[$k] ?? ''));
  $pass = (string) ($_POST['password'] ?? '');
  $g = guard_check('uye-ol');
  if ($g === 'bot') { header('Location: /'); exit; }
  if ($g !== '') $errors[] = $g;
  elseif (!member_csrf_ok()) $errors[] = 'Oturum süresi doldu. Tekrar deneyin.';
  // Form doğrulaması geçmeyen istekler e-postanın kayıtlı olup olmadığını öğrenemez; kontroller de sayılır
  elseif (!rate_hit('uyeol-e:' . client_hash(), 20)) $errors[] = 'Kısa sürede çok fazla deneme yaptınız. Biraz sonra tekrar deneyin.';
  if (!$errors) {
    if (mb_strlen($old['name']) < 3 || mb_strlen($old['name']) > 60 || preg_match('#https?://|www\.|[a-z0-9-]\.[a-z]{2,}\b|[/@<>]#iu', $old['name'])) $errors[] = 'Adınızı ve soyadınızı yazın.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta adresi yazın.';
    elseif (($ex = user_by('email', $old['email'])) && user_verified($ex)) $errors[] = 'Bu e-posta ile zaten bir üyelik var. <a href="/giris/">Giriş yapın</a> ya da <a href="/sifremi-unuttum/">şifrenizi yenileyin</a>.';
    if ($old['phone'] !== '' && !preg_match('/^\+?[0-9 ()-]{10,20}$/', $old['phone'])) $errors[] = 'Telefon numarası geçerli görünmüyor.';
    if (mb_strlen($pass) < 8) $errors[] = 'Şifre en az 8 karakter olmalı.';
    if (empty($_POST['kvkk'])) $errors[] = 'Aydınlatma metnini ve topluluk kurallarını onaylayın.';
  }
  if (!$errors && !rate_hit('uyeol:' . client_hash(), 5, 10)) $errors[] = 'Kısa sürede çok fazla deneme yaptınız. Biraz sonra tekrar deneyin.';
  if (!$errors) {
    $u = [
      'id' => 'u' . new_id(5), 'username' => unique_username($old['name']), 'name' => $old['name'], 'email' => mb_strtolower($old['email']),
      'phone' => $old['phone'], 'hash' => password_hash($pass, PASSWORD_DEFAULT), 'bio' => '', 'city' => '', 'avatar' => '', 'instagram' => '',
      'show_events' => true, 'status' => 'aktif', 'created' => date('Y-m-d H:i'), 'last_login' => '', 'tokens' => [], 'verified' => false,
    ];
    $ok = json_update(USERS_FILE, function (array &$d) use ($u) {
      $d['users'] ??= [];
      // Doğrulanmamış eski kayıt (aynı e-posta ya da süresi geçmiş) yenisine yer açar: adresin sahibi her zaman üye olabilir
      $keep = [];
      foreach ($d['users'] as $x) {
        if (!user_verified($x) && (mb_strtolower($x['email']) === $u['email'] || ($x['verify']['exp'] ?? 0) < time() - 7 * 86400)) continue;
        if (mb_strtolower($x['email']) === $u['email']) return false;
        $keep[] = $x;
      }
      $keep[] = $u;
      $d['users'] = $keep;
      return true;
    }, ['users' => []]);
    if (!$ok) $errors[] = 'Bu e-posta ile zaten bir üyelik var.';
    else {
      send_verify_mail($u, $donus);
      $pending = $u['email'];
    }
  }
}
$title = 'Üye ol · ' . $c['brand']['name'];
$noindex = true;
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap auth auth--wide">
    <div class="auth__intro">
      <p class="label">Ücretsiz üyelik</p>
      <h1 class="h2">Atölye topluluğuna katılın</h1>
      <ul class="checks">
        <li><?= icon('tik') ?>Etkinliklere birkaç adımda kaydolun, kayıtlarınızı tek yerde görün</li>
        <li><?= icon('tik') ?>Katılmayı düşündüğünüz etkinlikleri işaretleyin</li>
        <li><?= icon('tik') ?>Yorum yazın, diğer katılımcıların profillerine göz atın</li>
        <li><?= icon('tik') ?>Katıldığınız etkinlikler profilinizde birikir</li>
      </ul>
    </div>
    <?php if ($pending !== ''): ?>
    <div class="auth__box">
      <p class="label">Son bir adım</p>
      <h2 class="h2">E-postanızı doğrulayın</h2>
      <p class="notice notice--ok"><b><?= e($pending) ?></b> adresine bir doğrulama bağlantısı gönderdik. Üyeliğiniz bağlantıyı açtığınızda başlar. Gelen kutunuzu (ve gereksiz klasörünü) kontrol edin.</p>
      <p class="muted small">Bağlantı <?= VERIFY_HOURS ?> saat geçerli. E-posta gelmediyse <a href="/giris/">giriş yapmayı</a> deneyin, yeni bir bağlantı gönderilir.</p>
    </div>
    <?php else: ?>
    <form class="auth__box" method="post" data-guard-form novalidate>
      <?php if ($verifyErr): ?><p class="notice notice--err" role="alert">Doğrulama bağlantısı geçersiz ya da süresi dolmuş. Giriş yapmayı deneyin, size yeni bir bağlantı gönderilir; ya da aşağıdan yeniden üye olun.</p><?php endif; ?>
      <?php if ($errors): ?><div class="notice notice--err" role="alert"><?php foreach ($errors as $er): ?><p><?= $er ?></p><?php endforeach; ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e(member_csrf()) ?>"><input type="hidden" name="donus" value="<?= e($donus) ?>">
      <?= guard_fields('uye-ol') ?>
      <label class="field">Ad soyad<input name="name" value="<?= e($old['name']) ?>" required maxlength="60" autocomplete="name"></label>
      <label class="field">E-posta<input type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email"></label>
      <label class="field">Telefon <span class="opt-l">(isteğe bağlı, kayıtlarda otomatik dolar)</span><input type="tel" name="phone" value="<?= e($old['phone']) ?>" autocomplete="tel"></label>
      <label class="field">Şifre <span class="opt-l">(en az 8 karakter)</span><input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
      <label class="consent"><input type="checkbox" name="kvkk" value="1"<?= !empty($_POST['kvkk']) ? ' checked' : '' ?> required><span><a href="/gizlilik/" target="_blank">Aydınlatma metnini</a> ve <a href="/katilim-kosullari/" target="_blank">topluluk kurallarını</a> okudum, kabul ediyorum.</span></label>
      <button class="btn btn--block" type="submit">Üye ol</button>
      <p class="cform__msg" role="status" data-form-msg></p>
      <p class="auth__alt">Zaten üye misiniz? <a href="/giris/?donus=<?= e(rawurlencode($donus)) ?>">Giriş yapın</a></p>
    </form>
    <?php endif; ?>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
