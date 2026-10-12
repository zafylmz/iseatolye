<?php
// Sistem kontrolü: sunucunun siteyi çalıştırmak için gerekenleri sağlayıp sağlamadığı ve son hata kayıtları.
$rows = [];
$add = function (string $label, bool $ok, string $detail, bool $warnOnly = false) use (&$rows) { $rows[] = [$label, $ok ? 'on' : ($warnOnly ? 'warn' : 'off'), $ok ? 'Tamam' : ($warnOnly ? 'Dikkat' : 'Sorun'), $detail]; };

$add('PHP sürümü', version_compare(PHP_VERSION, '8.1', '>='), PHP_VERSION . (version_compare(PHP_VERSION, '8.1', '>=') ? '' : ' · cPanel > MultiPHP Manager\'dan 8.1 veya üstünü seçin'));

$probe = DATA . '/kontrol.json';
$writeOk = false; $writeErr = '';
try { json_update($probe, function (array &$d) { $d['t'] = date('c'); }); $writeOk = (json_read($probe)['t'] ?? '') !== ''; } catch (Throwable $ex) { $writeErr = $ex->getMessage(); }
$add('data klasörüne yazma', $writeOk, $writeOk ? 'Üyeler, katılımlar ve yorumlar kaydedilebiliyor.' : 'Yazılamıyor: ' . $writeErr . ' · Dosya Yöneticisi\'nde data klasörünün izinlerini 755 yapın.');
$dbRows = [];
$dbErr = '';
if (db_config()) {
  try {
    $dbRows = db()->query('SELECT ad, LENGTH(veri) AS boyut, guncel FROM ise_belgeler ORDER BY ad')->fetchAll();
    $bk = db()->query('SELECT COUNT(*) FROM ise_yedekler')->fetchColumn();
    $add('Veritabanı', true, 'Bağlı (' . db_config()['name'] . '). Sitenin bütün verileri (içerik, etkinlikler, blog, üyeler, katılımlar, yorumlar) veritabanında; ' . (int) $bk . ' yedek kopya var.');
  } catch (Throwable $ex) { $dbErr = $ex->getMessage(); $add('Veritabanı', false, 'Bağlanılamıyor: ' . $dbErr); }
} else {
  $add('Veritabanı', false, 'Bağlı değil; kayıtlar data/ klasöründeki dosyalarda. Aşağıdan bağlayabilirsiniz.', true);
}
foreach (['users.json' => 'Üyeler', 'registrations.json' => 'Katılımlar', 'comments.json' => 'Yorumlar', 'content.json' => 'İçerik', 'events.json' => 'Etkinlikler'] as $f => $l) {
  if (db_doc(DATA . '/' . $f)) continue;
  $p = DATA . '/' . $f;
  if (!is_file($p)) { $add($l . ' dosyası', true, 'Henüz oluşmadı (ilk kayıtta oluşur).'); continue; }
  $valid = is_array(json_decode((string) file_get_contents($p), true));
  $add($l . ' dosyası', $valid && is_writable($p), number_format(filesize($p) / 1024, 1, ',', '.') . ' KB' . ($valid ? '' : ' · DOSYA BOZUK, data/yedek/gunluk altındaki son kopyayı geri yükleyin') . (is_writable($p) ? '' : ' · yazılamıyor'));
}
$up = ROOT . '/uploads';
$add('uploads klasörüne yazma', is_dir($up) && is_writable($up), is_writable($up) ? 'Fotoğraf yüklenebiliyor.' : 'Yazılamıyor · izinleri 755 yapın.');

$sp = (string) session_save_path();
$add('Oturum klasörü', $sp === '' || is_writable(str_contains($sp, ';') ? substr($sp, strrpos($sp, ';') + 1) : $sp) || is_writable(sys_get_temp_dir()), ($sp !== '' ? $sp : sys_get_temp_dir()) . ' · Giriş ve üyelik oturumları burada tutulur.');
$_SESSION['kontrol_n'] = (int) ($_SESSION['kontrol_n'] ?? 0) + 1;
$add('Oturum hatırlanıyor mu', $_SESSION['kontrol_n'] > 1, $_SESSION['kontrol_n'] > 1 ? 'Evet (' . $_SESSION['kontrol_n'] . '. açılış).' : 'Bu sayfayı bir kez yenileyin; hâlâ "Dikkat" görünüyorsa oturumlar kaydedilmiyor demektir.', true);
$add('HTTPS', https(), https() ? 'Bağlantı güvenli.' : 'Sunucu HTTPS bildirmiyor; çerezler güvenli işaretlenemiyor.', true);

$free = @disk_free_space(DATA);
$add('Disk alanı', $free === false || $free > 50 * 1048576, $free === false ? 'Ölçülemedi.' : number_format($free / 1048576, 0, ',', '.') . ' MB boş', true);
$add('Görsel işleme (GD)', function_exists('imagecreatetruecolor'), function_exists('imagecreatetruecolor') ? 'Fotoğraflar küçültülebiliyor.' : 'GD eklentisi yok · cPanel > PHP eklentilerinden "gd"yi açın.', true);
require_once ROOT . '/inc/smtp.php';
$smtpC = smtp_config();
$mailOn = setting('mail_enabled', true);
if (!$mailOn) $add('E-posta', false, 'Ayarlar\'da e-posta gönderimi kapalı.', true);
elseif ($smtpC) $add('E-posta', true, 'SMTP hesabıyla gönderiliyor: ' . ($smtpC['user'] ?? '') . ' (' . $smtpC['host'] . ':' . $smtpC['port'] . ') · Ayarlar\'dan deneme e-postası gönderebilirsiniz.');
elseif (php_mail_ok()) $add('E-posta', true, 'Sunucunun mail() işleviyle gönderiliyor. Spam\'e düşmemesi için Ayarlar > E-posta hesabı (SMTP) bölümünü doldurabilirsiniz.');
else $add('E-posta', false, 'Sunucuda PHP mail() kapalı · Ayarlar > E-posta hesabı (SMTP) bölümüne cPanel\'deki e-posta hesabınızın bilgilerini girin.');

// Son hatalar: sitenin kendi kaydı ve cPanel'in error_log dosyası
$logs = [];
foreach ([DATA . '/hata.log' => 'Site hata kaydı', ROOT . '/error_log' => 'cPanel error_log'] as $f => $l) {
  if (!is_file($f)) continue;
  $size = filesize($f);
  $h = fopen($f, 'r');
  if ($size > 40000) fseek($h, -40000, SEEK_END);
  $lines = array_slice(array_filter(explode("\n", (string) stream_get_contents($h))), -40);
  fclose($h);
  $logs[$l] = array_reverse($lines);
}
$bad = count(array_filter($rows, fn($r) => $r[1] === 'off'));
?>
<div class="bar"><h1>Sistem kontrolü</h1><div class="bar__act"><a class="btn btn--ghost btn--sm" href="./?s=kontrol">Yenile</a></div></div>
<p class="hint"><?= $bad ? '<b>' . $bad . ' sorun bulundu.</b> Kırmızı satırlardaki açıklamayı izleyin.' : 'Sunucu siteyi çalıştırmak için gerekenlerin hepsini sağlıyor.' ?> Bir şey çalışmazsa bu sayfanın ekran görüntüsünü gönderebilirsiniz.</p>
<section class="card card--flush">
  <div class="tbl-wrap"><table class="tbl tbl--left">
    <thead><tr><th>Kontrol</th><th>Durum</th><th>Ayrıntı</th></tr></thead>
    <tbody><?php foreach ($rows as [$l, $tone, $st, $d]): ?><tr><td><strong><?= e($l) ?></strong></td><td><?= pill($st, $tone) ?></td><td><?= e($d) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
</section>
<?php if (!db_config()): ?>
  <form class="card" method="post" autocomplete="off">
    <?= hidden($tok, 'veritabani') ?>
    <h2>Veritabanına bağla (MySQL)</h2>
    <ol class="steps-list">
      <li>cPanel > <b>MySQL Veritabanı Sihirbazı</b>'nı açın.</li>
      <li>Bir veritabanı oluşturun (ör. <code>iseatolye</code>). cPanel başına hesap adınızı ekler: <code>hesapadi_iseatolye</code>.</li>
      <li>Bir kullanıcı oluşturun, güçlü bir şifre verin ve kullanıcıya <b>TÜM YETKİLER</b>'i verin.</li>
      <li>Bilgileri aşağıya yazın. Mevcut üye, katılım ve yorum kayıtları otomatik aktarılır; dosyalar silinmez, data/yedek altına taşınır.</li>
    </ol>
    <div class="grid2">
      <label>Veritabanı adı<input name="db_name" required placeholder="hesapadi_iseatolye"></label>
      <label>Kullanıcı adı<input name="db_user" required placeholder="hesapadi_iseuser"></label>
      <label>Şifre<input type="password" name="db_pass" autocomplete="new-password"></label>
      <label>Sunucu <span class="opt">(çoğunlukla değiştirmeyin)</span><input name="db_host" value="localhost"></label>
    </div>
    <div><button class="btn">Bağlan ve kayıtları aktar</button></div>
  </form>
<?php elseif ($dbRows): ?>
  <section class="card card--flush">
    <div class="card__head card__head--pad"><h2>Veritabanındaki kayıtlar</h2></div>
    <div class="tbl-wrap"><table class="tbl tbl--left"><thead><tr><th>Kayıt</th><th>Boyut</th><th>Son değişiklik</th></tr></thead><tbody>
      <?php foreach ($dbRows as $r): ?><tr><td><?= e(['users' => 'Üyeler', 'registrations' => 'Katılımlar', 'comments' => 'Etkinlik yorumları', 'interests' => 'İlgilenenler', 'messages' => 'Mesajlar', 'blog-comments' => 'Blog yorumları', 'blog-likes' => 'Blog beğenileri', 'resets' => 'Şifre yenileme'][$r['ad']] ?? $r['ad']) ?></td><td><?= number_format($r['boyut'] / 1024, 1, ',', '.') ?> KB</td><td><?= e(date('d.m.Y H:i', strtotime($r['guncel']))) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </section>
<?php endif; ?>
<?php foreach ($logs as $l => $lines): ?>
  <section class="card">
    <h2><?= e($l) ?> <span class="count">son <?= count($lines) ?> satır, en yeni üstte</span></h2>
    <?php if (!$lines): ?><p class="hint">Kayıt boş.</p><?php else: ?><pre class="mono logbox"><?= e(implode("\n", $lines)) ?></pre><?php endif; ?>
  </section>
<?php endforeach; ?>
<?php if (!$logs): ?><section class="card"><h2>Hata kaydı</h2><p class="hint">Henüz kaydedilmiş bir hata yok.</p></section><?php endif; ?>
