<?php
// Üye paneli: etkinliklerim, düşündüklerim, profil, şifre, hesabı sil.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$me = require_user();
$view = (string) ($_GET['s'] ?? 'etkinlikler');
if (!in_array($view, ['etkinlikler', 'dusunduklerim', 'profil', 'guvenlik'], true)) $view = 'etkinlikler';

$update = function (callable $fn) use ($me) {
  json_update(USERS_FILE, function (array &$d) use ($fn, $me) { foreach ($d['users'] as &$u) if ($u['id'] === $me['id']) $fn($u); }, ['users' => []]);
};

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $a = (string) ($_POST['action'] ?? '');
  try {
    if (!member_csrf_ok()) throw new RuntimeException('Oturum süresi doldu. Tekrar deneyin.');
    if ($a === 'profil') {
      $name = trim((string) ($_POST['name'] ?? ''));
      $username = strtolower(trim((string) ($_POST['username'] ?? '')));
      $bio = trim(str_replace("\r\n", "\n", (string) ($_POST['bio'] ?? '')));
      $insta = trim((string) ($_POST['instagram'] ?? ''));
      if (mb_strlen($name) < 3 || mb_strlen($name) > 60) throw new RuntimeException('Adınızı ve soyadınızı yazın.');
      if (!preg_match('/^[a-z0-9]{3,20}$/', $username)) throw new RuntimeException('Kullanıcı adı 3-20 karakter olmalı; yalnızca küçük harf (Türkçe karakter olmadan) ve rakam.');
      if ($username !== $me['username'] && unique_username($username, $me['id']) !== $username) throw new RuntimeException('Bu kullanıcı adı alınmış, başka bir tane deneyin.');
      if (mb_strlen($bio) > 300) throw new RuntimeException('Hakkınızda yazısı en fazla 300 karakter olabilir.');
      $insta = ltrim(preg_replace('#^(https?://)?(www\.)?instagram\.com/#i', '', $insta), '@/');
      if ($insta !== '' && !preg_match('/^[A-Za-z0-9._]{1,30}$/', $insta)) throw new RuntimeException('Instagram kullanıcı adı geçerli görünmüyor.');
      $phone = trim((string) ($_POST['phone'] ?? ''));
      if ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{10,20}$/', $phone)) throw new RuntimeException('Telefon numarası geçerli görünmüyor.');
      $avatar = store_avatar($_FILES['avatar'] ?? []);
      $remove = !empty($_POST['remove_avatar']);
      if ($avatar || $remove) delete_upload($me['avatar'] ?? '');
      $update(function (array &$u) use ($name, $username, $bio, $insta, $phone, $avatar, $remove) {
        $u['name'] = $name; $u['username'] = $username; $u['bio'] = $bio; $u['instagram'] = $insta; $u['phone'] = $phone;
        $u['city'] = mb_substr(trim((string) ($_POST['city'] ?? '')), 0, 40);
        $u['show_events'] = !empty($_POST['show_events']);
        if ($remove) $u['avatar'] = '';
        if ($avatar) $u['avatar'] = $avatar;
      });
      member_flash('Profiliniz kaydedildi.');
      header('Location: /hesabim/?s=profil', true, 303); exit;
    }
    if ($a === 'sifre') {
      if (!rate_hit('sifre:' . $me['id'], 10) || !password_verify((string) ($_POST['old'] ?? ''), $me['hash'])) throw new RuntimeException('Mevcut şifreniz hatalı.');
      $p1 = (string) ($_POST['p1'] ?? '');
      if (mb_strlen($p1) < 8) throw new RuntimeException('Yeni şifre en az 8 karakter olmalı.');
      if ($p1 !== (string) ($_POST['p2'] ?? '')) throw new RuntimeException('Yeni şifreler aynı değil.');
      $hash = password_hash($p1, PASSWORD_DEFAULT);
      // Şifre değişince "beni hatırla" çerezleri ve diğer cihazlardaki oturumlar geçersiz olur
      $update(function (array &$u) use ($hash) { $u['hash'] = $hash; $u['tokens'] = []; });
      session_regenerate_id(true);
      $_SESSION['pw'] = pw_mark(['hash' => $hash]);
      setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
      member_flash('Şifreniz değiştirildi. Diğer cihazlardaki oturumlar kapatıldı.');
      header('Location: /hesabim/?s=guvenlik', true, 303); exit;
    }
    if ($a === 'eposta') {
      $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
      if (!rate_hit('sifre:' . $me['id'], 10) || !password_verify((string) ($_POST['password'] ?? ''), $me['hash'])) throw new RuntimeException('Şifreniz hatalı.');
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Geçerli bir e-posta adresi yazın.');
      if (($o = user_by('email', $email)) && $o['id'] !== $me['id']) throw new RuntimeException('Bu e-posta başka bir üyelikte kullanılıyor.');
      $update(function (array &$u) use ($email) { $u['email'] = $email; });
      member_flash('E-posta adresiniz güncellendi.');
      header('Location: /hesabim/?s=guvenlik', true, 303); exit;
    }
    if ($a === 'sil') {
      if (!rate_hit('sifre:' . $me['id'], 10) || !password_verify((string) ($_POST['password'] ?? ''), $me['hash'])) throw new RuntimeException('Şifreniz hatalı.');
      // Üye silinir; yorumları "Eski üye" olarak kalır, kayıtlardaki bağlantı kaldırılır, düşündükleri silinir.
      delete_upload($me['avatar'] ?? '');
      json_update(USERS_FILE, function (array &$d) use ($me) { $d['users'] = array_values(array_filter($d['users'], fn($u) => $u['id'] !== $me['id'])); }, ['users' => []]);
      if (data_exists(INTERESTS_FILE)) json_update(INTERESTS_FILE, function (array &$d) use ($me) { foreach ($d as $k => &$m) { unset($m[$me['id']]); if (!$m) unset($d[$k]); } });
      if (data_exists(REGS_FILE)) json_update(REGS_FILE, function (array &$d) use ($me) { foreach ($d['regs'] as &$r) if (($r['user'] ?? '') === $me['id']) { $r['user'] = ''; $r['name'] = 'Silinmiş üye'; $r['email'] = ''; $r['phone'] = ''; $r['others'] = ''; } }, ['regs' => []]);
      user_logout();
      header('Location: /?hesap=silindi', true, 303); exit;
    }
  } catch (RuntimeException $ex) {
    member_flash($ex->getMessage(), 'err');
    header('Location: /hesabim/?s=' . $view, true, 303); exit;
  }
}

$flash = member_flash();
$tok = member_csrf();
$regs = user_regs($me['id']);
$upcoming = []; $past = [];
foreach ($regs as $r) {
  $ev = event_by_id($r['event']);
  if (!$ev) continue;
  $s = is_package($ev) ? (event_sessions($ev, false)[0] ?? null) : session_by_id($ev, $r['session']);
  $endTs = is_package($ev) ? (($l = event_sessions($ev, false)) ? session_ts(end($l), true) : 0) : ($s ? session_ts($s, true) : 0);
  $row = ['r' => $r, 'ev' => $ev, 's' => $s];
  if ($endTs >= time() && $r['status'] !== 'iptal') $upcoming[] = $row; else $past[] = $row;
}
usort($upcoming, fn($a, $b) => session_ts($a['s'] ?? []) <=> session_ts($b['s'] ?? []));
$ints = array_values(array_filter(array_map(fn($eid) => event_by_id((string) $eid), array_keys(user_interests($me['id']))), fn($ev) => $ev && event_visible($ev)));
$title = 'Hesabım · ' . $c['brand']['name'];
$noindex = true;
include __DIR__ . '/inc/header.php';
$tabs = ['etkinlikler' => 'Etkinliklerim', 'dusunduklerim' => 'Düşündüklerim', 'profil' => 'Profil', 'guvenlik' => 'Hesap ve güvenlik'];
?>
  <section class="wrap account">
    <header class="account__head">
      <?= avatar($me, 'lg') ?>
      <div><p class="label">Hesabım</p><h1 class="h2"><?= e($me['name']) ?></h1><a class="link-more" href="<?= e(user_url($me)) ?>">Herkese açık profilim<?= icon('sag') ?></a></div>
    </header>
    <nav class="tabs" aria-label="Hesap bölümleri"><?php foreach ($tabs as $k => $l): ?><a href="/hesabim/?s=<?= $k ?>"<?= $view === $k ? ' aria-current="page"' : '' ?>><?= $l ?><?php if ($k === 'etkinlikler' && $upcoming): ?><span class="count"><?= count($upcoming) ?></span><?php elseif ($k === 'dusunduklerim' && $ints): ?><span class="count"><?= count($ints) ?></span><?php endif; ?></a><?php endforeach; ?></nav>
    <?php if ($flash): ?><p class="notice notice--<?= e($flash[1]) ?>"><?= e($flash[0]) ?></p><?php endif; ?>

    <?php if ($view === 'etkinlikler'): ?>
      <h2 class="h3">Yaklaşan</h2>
      <?php if (!$upcoming): ?><div class="empty empty--left"><p>Yaklaşan bir kaydınız yok.</p><a class="btn" href="/etkinlikler/">Etkinliklere göz atın</a></div><?php endif; ?>
      <ul class="rlist">
        <?php foreach ($upcoming as $row): ['r' => $r, 'ev' => $ev, 's' => $s] = $row; ?>
          <li class="rrow">
            <a class="rrow__img" href="<?= e(ticket_url($r)) ?>"><?php if (!empty($ev['cover'])): ?><img src="<?= e($ev['cover']) ?>" alt="" loading="lazy"><?php endif; ?></a>
            <div class="rrow__info"><a href="<?= e(ticket_url($r)) ?>"><strong><?= e($ev['title']) ?></strong></a><span class="muted"><?= e($s ? (is_package($ev) ? event_when($ev) : session_when($s)) : '') ?> · <?= e(session_place($s, false)) ?></span><span class="small"><span class="pill pill--<?= e($r['status']) ?>"><?= e(REG_STATUS[$r['status']] ?? '') ?></span> <?= e($r['code']) ?><?= $r['total'] > 0 ? ' · ' . money($r['total']) . ($r['paid'] ? ' · ödendi' : (in_array($r['status'], SEAT_STATUSES, true) ? ' · ödeme bekleniyor' : '')) : '' ?></span></div>
            <a class="btn btn--ghost btn--sm" href="<?= e(ticket_url($r)) ?>">Kaydı gör</a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($past): ?>
        <h2 class="h3">Geçmiş ve iptal edilenler</h2>
        <ul class="rlist rlist--past">
          <?php foreach ($past as $row): ['r' => $r, 'ev' => $ev, 's' => $s] = $row; ?>
            <li class="rrow"><a class="rrow__img" href="<?= e(event_url($ev)) ?>"><?php if (!empty($ev['cover'])): ?><img src="<?= e($ev['cover']) ?>" alt="" loading="lazy"><?php endif; ?></a><div class="rrow__info"><a href="<?= e(event_url($ev)) ?>"><strong><?= e($ev['title']) ?></strong></a><span class="muted"><?= e($s ? tr_date($s['date']) : '') ?> · <?= e(REG_STATUS[$r['status']] ?? '') ?></span></div><?php if ($r['status'] !== 'iptal' && ($ev['comments'] ?? true) !== false): ?><a class="btn btn--ghost btn--sm" href="<?= e(event_url($ev)) ?>#yorumlar">Yorum yaz</a><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    <?php elseif ($view === 'dusunduklerim'): ?>
      <p class="muted">"Katılmayı düşünüyorum" dediğiniz etkinlikler. Kesinleştiğinde kaydınızı oluşturmayı unutmayın.</p>
      <?php if (!$ints): ?><div class="empty empty--left"><p>Henüz işaretlediğiniz bir etkinlik yok.</p><a class="btn" href="/etkinlikler/">Etkinliklere göz atın</a></div><?php else: ?>
        <div class="egrid"><?php foreach ($ints as $ev) include __DIR__ . '/inc/event-card.php'; ?></div>
      <?php endif; ?>

    <?php elseif ($view === 'profil'): ?>
      <?php if (!empty($_GET['yeni'])): ?><p class="notice notice--ok">Profilinize bir fotoğraf ve birkaç cümle eklerseniz diğer katılımcılar sizi daha kolay tanır.</p><?php endif; ?>
      <form class="form-card" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="profil">
        <div class="photo-row"><?= avatar($me, 'xl') ?><div><label class="field">Profil fotoğrafı<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></label><?php if (!empty($me['avatar'])): ?><label class="check"><input type="checkbox" name="remove_avatar" value="1"> Fotoğrafı kaldır</label><?php endif; ?><p class="muted small">Kare olarak kırpılır. JPG, PNG ya da WEBP, en fazla 6 MB.</p></div></div>
        <div class="grid2">
          <label class="field">Ad soyad<input name="name" value="<?= e($me['name']) ?>" required maxlength="60"></label>
          <label class="field">Kullanıcı adı<span class="prefix"><span>/uye/</span><input name="username" value="<?= e($me['username']) ?>" required pattern="[a-z0-9]{3,20}" maxlength="20"></span></label>
          <label class="field">Şehir / semt <span class="opt-l">(isteğe bağlı)</span><input name="city" value="<?= e($me['city'] ?? '') ?>" maxlength="40" placeholder="Silivri"></label>
          <label class="field">Instagram <span class="opt-l">(isteğe bağlı)</span><input name="instagram" value="<?= e($me['instagram'] ?? '') ?>" placeholder="kullaniciadi"></label>
        </div>
        <label class="field">Hakkımda <span class="opt-l">(en fazla 300 karakter)</span><textarea name="bio" rows="3" maxlength="300" placeholder="Hangi atölyeleri seversiniz?"><?= e($me['bio'] ?? '') ?></textarea></label>
        <label class="field">Telefon <span class="opt-l">(görünmez, yalnızca kayıtlarda kullanılır)</span><input type="tel" name="phone" value="<?= e($me['phone'] ?? '') ?>"></label>
        <label class="check"><input type="checkbox" name="show_events" value="1"<?= ($me['show_events'] ?? true) ? ' checked' : '' ?>> Katıldığım ve katılmayı düşündüğüm etkinlikler profilimde ve etkinlik sayfalarında görünsün</label>
        <div class="form-card__foot"><button class="btn">Kaydet</button></div>
      </form>

    <?php else: ?>
      <div class="grid2 grid2--top">
        <form class="form-card" method="post">
          <h2 class="h3">Şifre değiştir</h2>
          <input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="sifre">
          <label class="field">Mevcut şifre<input type="password" name="old" required autocomplete="current-password"></label>
          <label class="field">Yeni şifre<input type="password" name="p1" minlength="8" required autocomplete="new-password"></label>
          <label class="field">Yeni şifre (tekrar)<input type="password" name="p2" minlength="8" required autocomplete="new-password"></label>
          <div class="form-card__foot"><button class="btn">Şifreyi değiştir</button></div>
        </form>
        <form class="form-card" method="post">
          <h2 class="h3">E-posta adresi</h2>
          <input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="eposta">
          <label class="field">E-posta<input type="email" name="email" value="<?= e($me['email']) ?>" required></label>
          <label class="field">Şifreniz<input type="password" name="password" required autocomplete="current-password"></label>
          <div class="form-card__foot"><button class="btn btn--ghost">Güncelle</button></div>
        </form>
      </div>
      <details class="cancel">
        <summary>Üyeliğimi silmek istiyorum</summary>
        <p class="muted small">Profiliniz ve fotoğrafınız silinir. Yorumlarınız "Eski üye" adıyla kalır, geçmiş kayıtlarınız muhasebe için isimsiz saklanır.</p>
        <form method="post" onsubmit="return confirm('Üyeliğiniz kalıcı olarak silinecek. Emin misiniz?')">
          <input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="sil">
          <label class="field">Şifreniz<input type="password" name="password" required autocomplete="current-password"></label>
          <button class="btn btn--danger btn--sm">Üyeliğimi sil</button>
        </form>
      </details>
    <?php endif; ?>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
