<?php
// Etkinliğe katılım (kayıt) formu ve kaydı oluşturma.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$ev = event_by_slug((string) ($_GET['slug'] ?? ''));
if (!$ev || ($ev['status'] ?? '') !== 'yayinda') { http_response_code(404); include __DIR__ . '/404.php'; exit; }
$needLogin = (bool) setting('require_login', true);
$me = $needLogin ? require_user() : current_user();
if ($me && ($r = user_has_reg($me['id'], $ev['id']))) { header('Location: ' . ticket_url($r), true, 303); exit; }

$sessions = bookable_sessions($ev);
$tickets = array_values(array_filter(event_tickets($ev), 'ticket_on_sale'));
$methods = pay_methods($ev);
$free = event_is_free($ev);
$max = max(1, (int) ($ev['max_per_order'] ?? 4));
$errors = [];
$old = ['session' => (string) ($_GET['oturum'] ?? ''), 'ticket' => $tickets[0]['id'] ?? '', 'qty' => 1, 'phone' => $me['phone'] ?? '', 'note' => '', 'others' => '', 'method' => $methods[0] ?? '', 'name' => $me['name'] ?? '', 'email' => $me['email'] ?? ''];
if (count($sessions) === 1) $old['session'] = $sessions[0]['id'];
// Fatura bilgisi: formdan ya da üyenin önceki kaydından
$invOld = ['type' => ($_POST['inv_type'] ?? '') === 'kurumsal' ? 'kurumsal' : 'bireysel', 'title' => '', 'tax_office' => '', 'tax_no' => '', 'tckn' => '', 'address' => ''];
foreach (['title', 'tax_office', 'tax_no', 'tckn', 'address'] as $k) $invOld[$k] = trim((string) ($_POST['inv_' . $k] ?? ''));
if ($me && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  $mine = array_filter(regs_all(), fn($x) => ($x['user'] ?? '') === $me['id'] && trim((string) ($x['invoice']['address'] ?? '')) !== '');
  usort($mine, fn($a, $b) => strcmp($b['created'], $a['created']));
  if ($mine) {
    $iv = $mine[0]['invoice'];
    $corp = trim((string) ($iv['title'] ?? '')) !== '';
    $invOld = ['type' => $corp ? 'kurumsal' : 'bireysel', 'title' => $iv['title'] ?? '', 'tax_office' => $iv['tax_office'] ?? '', 'tax_no' => $corp ? $iv['tax_no'] ?? '' : '', 'tckn' => $corp ? '' : $iv['tax_no'] ?? '', 'address' => $iv['address'] ?? ''];
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  foreach ($old as $k => $_) if (isset($_POST[$k])) $old[$k] = trim(str_replace("\r\n", "\n", (string) $_POST[$k]));
  $old['qty'] = max(1, min($max, (int) $old['qty']));
  if ($me) { if (!member_csrf_ok()) $errors[] = 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.'; }
  else { $g = guard_check('katil|' . $ev['id']); if ($g === 'bot') { header('Location: /'); exit; } if ($g !== '') $errors[] = $g; }
  $s = null; foreach ($sessions as $x) if ($x['id'] === $old['session']) $s = $x;
  $t = ticket_by_id($ev, $old['ticket']);
  if (!$s) $errors[] = 'Lütfen bir tarih seçin.';
  if (!$t || !ticket_on_sale($t)) $errors[] = 'Lütfen bir bilet türü seçin.';
  if (!preg_match('/^\+?[0-9 ()-]{10,20}$/', $old['phone'])) $errors[] = 'Telefon numaranızı yazın (ör. 0507 542 87 98).';
  if (!$me) {
    if (mb_strlen($old['name']) < 3 || mb_strlen($old['name']) > 60 || preg_match('#https?://|www\.|[a-z0-9-]\.[a-z]{2,}\b|[/@<>]#iu', $old['name'])) $errors[] = 'Adınızı ve soyadınızı yazın.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta adresi yazın.';
  }
  if (!$free && !in_array($old['method'], $methods, true)) $errors[] = 'Bir ödeme yöntemi seçin.';
  $inv = $free ? [] : invoice_from_post($errors);
  if (empty($_POST['kosul'])) $errors[] = $free ? 'Katılım koşullarını ve aydınlatma metnini onaylayın.' : 'Katılım koşullarını, mesafeli satış sözleşmesini ve aydınlatma metnini onaylayın.';
  if (mb_strlen($old['note']) > 500) $errors[] = 'Not en fazla 500 karakter olabilir.';
  if (!$errors && !rate_hit('kayit:' . client_hash(), 12, 5)) $errors[] = 'Kısa sürede çok fazla deneme yaptınız, biraz sonra tekrar deneyin.';

  if (!$errors) {
    $now = date('Y-m-d H:i');
    $res = json_update(REGS_FILE, function (array &$d) use ($ev, $s, $t, $old, $me, $free, $now, $inv) {
      $d['regs'] ??= [];
      if ($me) foreach ($d['regs'] as $r) if (($r['user'] ?? '') === $me['id'] && $r['event'] === $ev['id'] && $r['status'] !== 'iptal') return ['dup', $r];
      $st = session_state($ev, $s, $d['regs']);
      $seats = $old['qty'] * max(1, (int) ($t['seats'] ?? 1));
      $left = seats_left($ev, $s['id'], $d['regs']);
      $tLimit = (int) ($t['limit'] ?? 0);
      if ($st === 'kapali' || $st === 'gecti' || $st === 'iptal') return ['err', 'Bu tarih için kayıtlar kapandı.'];
      if ($tLimit > 0 && ticket_taken($ev, $t['id'], $s['id'], $d['regs']) + $old['qty'] > $tLimit) return ['err', '"' . $t['name'] . '" biletinden yeterli sayıda kalmadı. Başka bir bilet türü seçin.'];
      $wait = $st === 'dolu' || ($left !== null && $left < $seats);
      if ($wait && $st !== 'dolu' && $left > 0) return ['err', 'Bu tarihte yalnızca ' . $left . ' kişilik yer kaldı. Kişi sayısını azaltın.'];
      if ($wait && empty($ev['waitlist'])) return ['err', 'Bu tarihin kontenjanı doldu.'];
      $status = $wait ? 'yedek' : ((!empty($ev['approval']) || (!$free && $old['method'] !== 'yerinde')) ? 'beklemede' : 'onayli');
      $r = [
        'id' => new_id(), 'code' => reg_code(), 'event' => $ev['id'], 'session' => is_package($ev) ? '*' : $s['id'],
        'ticket' => $t['id'], 'ticket_name' => $t['name'], 'qty' => $old['qty'], 'seats' => $seats,
        'unit_price' => (float) $t['price'], 'total' => (float) $t['price'] * $old['qty'],
        'user' => $me['id'] ?? '', 'name' => $me['name'] ?? $old['name'], 'email' => $me['email'] ?? $old['email'], 'phone' => $old['phone'],
        'others' => mb_substr($old['others'], 0, 400), 'note' => $old['note'], 'method' => $free ? '' : $old['method'],
        'invoice' => $inv, 'inv_no' => '', 'inv_date' => '',
        'status' => $status, 'paid' => $free, 'checked_in' => false, 'admin_note' => '', 'source' => 'site', 'created' => $now, 'updated' => $now,
      ];
      $d['regs'][] = $r;
      return ['ok', $r];
    }, ['regs' => []]);
    if ($res[0] === 'dup') { header('Location: ' . ticket_url($res[1]), true, 303); exit; }
    if ($res[0] === 'err') $errors[] = $res[1];
    else {
      $r = $res[1];
      if ($me) {
        // Katılınca "düşünüyorum" listesinden çıkar, telefonu profile kaydet
        if (data_exists(INTERESTS_FILE)) json_update(INTERESTS_FILE, function (array &$d) use ($ev, $me) { unset($d[$ev['id']][$me['id']]); if (empty($d[$ev['id']])) unset($d[$ev['id']]); });
        if (empty($me['phone'])) json_update(USERS_FILE, function (array &$d) use ($me, $old) { foreach ($d['users'] as &$u) if ($u['id'] === $me['id']) $u['phone'] = $old['phone']; }, ['users' => []]);
      }
      require_once __DIR__ . '/inc/mail.php';
      mail_registration($r, $ev);
      // Kartla ödemede doğrudan ödeme sayfasına geçilir
      if ($r['method'] === 'kart' && $r['status'] === 'beklemede') { header('Location: /odeme.php?kod=' . rawurlencode($r['code']) . '&k=' . reg_key($r), true, 303); exit; }
      header('Location: ' . ticket_url($r, !$me) . (!$me ? '&' : '?') . 'yeni=1', true, 303);
      exit;
    }
  }
}

$title = 'Katılım · ' . $ev['title'] . ' · ' . $c['brand']['name'];
$noindex = true;
$tok = $me ? member_csrf() : '';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap join">
    <a class="back" href="<?= e(event_url($ev)) ?>"><?= icon('sol') ?>Etkinliğe dön</a>
    <div class="join__grid">
      <form class="join__form" method="post" data-join<?= !$me ? ' data-guard-form' : '' ?> novalidate>
        <p class="label">Etkinliğe katıl</p>
        <h1 class="h2"><?= e($ev['title']) ?></h1>
        <?php if ($errors): ?><div class="notice notice--err" role="alert"><?= icon('bilgi') ?><div><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div></div><?php endif; ?>
        <?php if (!$sessions): ?><p class="notice notice--warn"><?= icon('bilgi') ?>Bu etkinlik için katılıma açık tarih yok.</p><?php else: ?>
        <?php if ($me): ?><input type="hidden" name="csrf" value="<?= e($tok) ?>"><?php else: ?><?= guard_fields('katil|' . $ev['id']) ?><?php endif; ?>

        <fieldset class="fs">
          <legend><span class="fs__n">1</span><?= is_package($ev) ? 'Kurs tarihleri' : 'Tarih seçin' ?></legend>
          <div class="opts">
            <?php foreach ($sessions as $s): $st = session_state($ev, $s); $left = seats_left($ev, $s['id']); $dis = !in_array($st, ['acik', 'dolu'], true) || ($st === 'dolu' && empty($ev['waitlist'])); ?>
              <label class="opt<?= $dis ? ' is-off' : '' ?>">
                <input type="radio" name="session" value="<?= e($s['id']) ?>"<?= $old['session'] === $s['id'] ? ' checked' : '' ?><?= $dis ? ' disabled' : '' ?> required>
                <span class="opt__body">
                  <strong><?= is_package($ev) ? count(event_sessions($ev, false)) . ' buluşma · ' . e(tr_date_short($s['date'])) . ' başlangıç' : e(session_when($s)) ?></strong>
                  <span><?= e(session_place($s)) ?><?= trim($s['note'] ?? '') !== '' && !is_package($ev) ? ' · ' . e($s['note']) : '' ?></span>
                  <small class="<?= $st === 'dolu' ? 'is-warn' : '' ?>"><?= $st === 'dolu' ? (!empty($ev['waitlist']) ? 'Kontenjan doldu · yedek listeye yazılırsınız' : 'Kontenjan doldu') : ($st !== 'acik' ? e(state_label($st, $ev)) : ($left !== null && !empty($ev['show_left']) ? $left . ' kişilik yer kaldı' : 'Yer var')) ?></small>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if (is_package($ev)): ?><ul class="mini-list"><?php foreach (event_sessions($ev, false) as $s): ?><li><?= e(session_when($s)) ?><?= trim($s['note'] ?? '') !== '' ? ' · ' . e($s['note']) : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
        </fieldset>

        <fieldset class="fs">
          <legend><span class="fs__n">2</span>Bilet ve kişi sayısı</legend>
          <div class="opts">
            <?php foreach ($tickets as $t): ?>
              <label class="opt opt--row">
                <input type="radio" name="ticket" value="<?= e($t['id']) ?>" data-price="<?= (float) $t['price'] ?>" data-seats="<?= max(1, (int) ($t['seats'] ?? 1)) ?>"<?= $old['ticket'] === $t['id'] ? ' checked' : '' ?> required>
                <span class="opt__body"><strong><?= e($t['name']) ?></strong><?php if (trim($t['note'] ?? '') !== '' || (int) ($t['seats'] ?? 1) > 1): ?><span><?= e(trim(($t['note'] ?? '') . ((int) ($t['seats'] ?? 1) > 1 && !str_contains($t['note'] ?? '', 'kişi') ? ' · ' . (int) $t['seats'] . ' kişi' : ''), ' ·')) ?></span><?php endif; ?></span>
                <b class="opt__price"><?= (float) $t['price'] > 0 ? money($t['price']) : 'Ücretsiz' ?></b>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="qty">
            <label for="qty">Adet</label>
            <div class="qty__ctl"><button type="button" class="btn btn--ghost btn--icon" data-qty="-1" aria-label="Azalt">−</button><input id="qty" name="qty" type="number" min="1" max="<?= $max ?>" value="<?= (int) $old['qty'] ?>" inputmode="numeric"><button type="button" class="btn btn--ghost btn--icon" data-qty="1" aria-label="Artır">+</button></div>
            <span class="muted small">En fazla <?= $max ?></span>
          </div>
          <label class="field" data-others<?= (int) $old['qty'] < 2 ? ' hidden' : '' ?>>Sizinle gelecek kişilerin adları <span class="opt-l">(isteğe bağlı)</span><textarea name="others" rows="2" maxlength="400"><?= e($old['others']) ?></textarea></label>
        </fieldset>

        <fieldset class="fs">
          <legend><span class="fs__n">3</span>İletişim bilgileri</legend>
          <?php if ($me): ?>
            <div class="whois"><?= avatar($me, 'sm') ?><span><strong><?= e($me['name']) ?></strong><span class="muted"><?= e($me['email']) ?></span></span></div>
          <?php else: ?>
            <div class="grid2"><label class="field">Ad soyad<input name="name" value="<?= e($old['name']) ?>" required autocomplete="name"></label><label class="field">E-posta<input type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email"></label></div>
          <?php endif; ?>
          <label class="field">Telefon<input type="tel" name="phone" value="<?= e($old['phone']) ?>" required autocomplete="tel" placeholder="05xx xxx xx xx"></label>
          <label class="field">Notunuz <span class="opt-l">(isteğe bağlı: alerji, özel durum, soru)</span><textarea name="note" rows="2" maxlength="500"><?= e($old['note']) ?></textarea></label>
        </fieldset>

        <?php if (!$free): $corp = $invOld['type'] === 'kurumsal'; ?>
        <fieldset class="fs" data-inv>
          <legend><span class="fs__n">4</span>Fatura bilgileri</legend>
          <div class="seg-radio inv-type" role="radiogroup" aria-label="Fatura türü">
            <label><input type="radio" name="inv_type" value="bireysel" data-inv-type<?= !$corp ? ' checked' : '' ?>><span>Bireysel</span></label>
            <label><input type="radio" name="inv_type" value="kurumsal" data-inv-type<?= $corp ? ' checked' : '' ?>><span>Şirket adına</span></label>
          </div>
          <div data-inv-group="bireysel"<?= $corp ? ' hidden' : '' ?>>
            <label class="field">T.C. kimlik no<input name="inv_tckn" value="<?= e($invOld['tckn']) ?>" inputmode="numeric" pattern="\d{11}" maxlength="11" autocomplete="off"></label>
            <p class="muted small">Fatura <?= $me ? e($me['name']) : 'yukarıdaki ad soyad' ?> adına kesilir. e-Arşiv fatura için T.C. kimlik numaranız gereklidir.</p>
          </div>
          <div class="grid2" data-inv-group="kurumsal"<?= !$corp ? ' hidden' : '' ?>>
            <label class="field">Şirket unvanı<input name="inv_title" value="<?= e($invOld['title']) ?>" maxlength="160" autocomplete="organization"></label>
            <label class="field">Vergi dairesi<input name="inv_tax_office" value="<?= e($invOld['tax_office']) ?>" maxlength="60"></label>
            <label class="field">Vergi no <span class="opt-l">(şahıs şirketinde T.C. kimlik no)</span><input name="inv_tax_no" value="<?= e($invOld['tax_no']) ?>" inputmode="numeric" pattern="\d{10,11}" maxlength="11"></label>
          </div>
          <label class="field">Fatura adresi <span class="opt-l">(il ve ilçe yeterli)</span><input name="inv_address" value="<?= e($invOld['address']) ?>" maxlength="300" required autocomplete="street-address"></label>
          <p class="muted small">Faturanız e-Arşiv fatura olarak e-posta adresinize gönderilir.</p>
        </fieldset>

        <fieldset class="fs">
          <legend><span class="fs__n">5</span>Ödeme</legend>
          <div class="opts">
            <?php foreach ($methods as $m): ?>
              <label class="opt opt--row"><input type="radio" name="method" value="<?= $m ?>"<?= $old['method'] === $m ? ' checked' : '' ?> required><span class="opt__body"><strong><?= e(PAY_METHODS[$m]) ?></strong><span><?= e(['havale' => 'Kaydınızdan sonra hesap bilgileri gösterilir. Ödemeniz onaylanınca kaydınız kesinleşir.', 'yerinde' => 'Ücreti etkinlik günü nakit ya da kartla ödersiniz.', 'link' => 'Kaydınızdan sonra güvenli ödeme sayfasına yönlendirilirsiniz.', 'kart' => 'PayTR güvenli ödeme sayfasında kartınızla ödersiniz. Ödeme alınınca kaydınız hemen kesinleşir. Yeriniz ' . CARD_HOLD_MINUTES . ' dakika ayrılır.'][$m]) ?></span></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <?php endif; ?>

        <label class="consent"><input type="checkbox" name="kosul" value="1"<?= !empty($_POST['kosul']) ? ' checked' : '' ?> required><span><a href="/katilim-kosullari/" target="_blank">Katılım koşullarını</a>, <?php if (!$free): ?><a href="/mesafeli-satis/" target="_blank">ön bilgilendirme formu ve mesafeli satış sözleşmesini</a> ve <?php endif; ?><a href="/gizlilik/" target="_blank">aydınlatma metnini</a> okudum, kabul ediyorum.</span></label>
        <div class="join__submit"><button class="btn btn--lg" type="submit" data-join-btn>Kaydımı oluştur</button><p class="cform__msg" role="status" data-form-msg></p></div>
        <?php endif; ?>
      </form>

      <aside class="join__sum">
        <div class="sum">
          <?php if (!empty($ev['cover'])): ?><img src="<?= e($ev['cover']) ?>" alt="" class="sum__img"><?php endif; ?>
          <div class="sum__body">
            <strong><?= e($ev['title']) ?></strong>
            <span class="muted small"><?= e(event_when($ev)) ?></span>
            <dl class="sum__rows">
              <div><dt>Bilet</dt><dd data-sum-ticket>–</dd></div>
              <div><dt>Adet</dt><dd data-sum-qty><?= (int) $old['qty'] ?></dd></div>
              <div class="sum__total"><dt>Toplam</dt><dd data-sum-total><?= $free ? 'Ücretsiz' : '–' ?></dd></div>
            </dl>
            <?php $pol = trim($ev['cancel_policy'] ?? '') ?: (string) setting('cancel_policy', ''); if ($pol !== ''): ?><p class="muted small"><?= e($pol) ?></p><?php endif; ?>
          </div>
        </div>
      </aside>
    </div>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
