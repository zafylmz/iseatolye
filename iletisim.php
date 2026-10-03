<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$formTopic = in_array($_GET['konu'] ?? '', ['kurumsal', 'ozel'], true) ? $_GET['konu'] : 'genel';
$formState = ['ok' => !empty($_GET['gonderildi']), 'errors' => [], 'old' => []];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $o = [];
  foreach (['name', 'email', 'phone', 'company', 'people', 'date', 'text', 'topic'] as $k) $o[$k] = trim(str_replace("\r\n", "\n", (string) ($_POST[$k] ?? '')));
  if (!in_array($o['topic'], ['genel', 'kurumsal', 'ozel'], true)) $o['topic'] = 'genel';
  $formTopic = $o['topic'];
  $err = [];
  $g = guard_check('iletisim');
  if ($g === 'bot') { header('Location: /iletisim/?gonderildi=1#form', true, 303); exit; }
  if ($g !== '') $err[] = $g;
  if (mb_strlen($o['name']) < 3) $err[] = 'Adınızı yazın.';
  if (!filter_var($o['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Geçerli bir e-posta adresi yazın.';
  if (mb_strlen($o['text']) < 10) $err[] = 'Mesajınız çok kısa.';
  if (preg_match_all('#(https?://|www\.)#i', $o['text']) > 2) $err[] = 'Mesajda en fazla 2 bağlantı olabilir.';
  if (empty($_POST['kvkk'])) $err[] = 'Aydınlatma metnini onaylayın.';
  if (!$err && !rate_hit('iletisim:' . client_hash(), 4, 30)) $err[] = 'Kısa sürede çok fazla mesaj gönderdiniz. Biraz sonra tekrar deneyin.';
  if (!$err) {
    $store = $o; $store['date_pref'] = $store['date']; unset($store['date']);
    $msg = ['id' => new_id(), 'date' => date('Y-m-d H:i'), 'read' => false, 'user' => current_user()['id'] ?? ''] + array_map(fn($v) => mb_substr($v, 0, 3000), $store);
    json_update(MESSAGES_FILE, function (array &$d) use ($msg) { $d['messages'] ??= []; array_unshift($d['messages'], $msg); }, ['messages' => []]);
    $labels = ['genel' => 'Genel soru', 'kurumsal' => 'Kurumsal etkinlik talebi', 'ozel' => 'Özel gün / grup talebi'];
    if (($to = admin_email()) !== '') send_mail($to, $labels[$o['topic']] . ': ' . $o['name'], implode("\n", array_filter([
      'Ad: ' . $o['name'], 'E-posta: ' . $o['email'], $o['phone'] ? 'Telefon: ' . $o['phone'] : '', $o['company'] ? 'Kurum: ' . $o['company'] : '',
      $o['people'] ? 'Kişi sayısı: ' . $o['people'] : '', $o['date'] ? 'Tarih: ' . $o['date'] : '', '', $o['text'], '', 'Panel: ' . site_url('/yonetim/?s=mesajlar'),
    ], fn($l) => $l !== false)));
    header('Location: /iletisim/?gonderildi=1#form', true, 303);
    exit;
  }
  $formState = ['ok' => false, 'errors' => $err, 'old' => $o];
}
$cc = $c['contact'];
$title = 'İletişim · ' . $c['brand']['name'];
$description = 'İse Atölye ile iletişime geçin: etkinlikler, kurumsal atölyeler ve özel gün organizasyonları.';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head">
    <p class="label">İletişim</p>
    <h1 class="display display--md"><?= e($cc['heading'] ?? 'Bize yazın') ?></h1>
  </section>
  <section class="wrap page-body contact-grid">
    <div class="contact-info">
      <ul class="cinfo">
        <?php if (trim($cc['phone'] ?? '') !== ''): ?><li><?= icon('telefon') ?><span><span class="label">Telefon</span><a href="<?= e(phone_href($cc['phone'])) ?>"><?= e($cc['phone']) ?></a></span></li><?php endif; ?>
        <?php if (trim($cc['whatsapp'] ?? '') !== ''): ?><li><?= icon('whatsapp') ?><span><span class="label">WhatsApp</span><a href="<?= e(wa_href($cc['whatsapp'])) ?>" target="_blank" rel="noopener">Mesaj gönderin</a></span></li><?php endif; ?>
        <?php if (trim($cc['email'] ?? '') !== ''): ?><li><?= icon('posta') ?><span><span class="label">E-posta</span><a href="mailto:<?= e($cc['email']) ?>"><?= e($cc['email']) ?></a></span></li><?php endif; ?>
        <?php if (trim($cc['address'] ?? '') !== ''): ?><li><?= icon('konum') ?><span><span class="label">Bölge</span><?= e($cc['address']) ?></span></li><?php endif; ?>
        <?php if (trim($cc['hours'] ?? '') !== ''): ?><li><?= icon('saat') ?><span><span class="label">Çalışma saatleri</span><?= e($cc['hours']) ?></span></li><?php endif; ?>
      </ul>
      <?php $socials = array_filter($c['socials'] ?? [], fn($s) => trim($s['url'] ?? '') !== ''); if ($socials): ?>
        <div class="social-list"><?php foreach ($socials as $s): ?><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><?= social_icon($s['label']) ?><?= e($s['label']) ?></a><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
    <?php include __DIR__ . '/inc/contact-form.php'; ?>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
