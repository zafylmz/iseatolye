<?php
// Mağaza sipariş formu (/magaza/urun-adi/siparis/) ve siparişi oluşturma.
// Üyelik gerekmez; üye girişliyse bilgileri hazır gelir.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/shop.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$p = product_by_slug((string) ($_GET['slug'] ?? ''));
if (!$p || !product_visible($p)) { http_response_code(404); include __DIR__ . '/404.php'; exit; }
$me = current_user();
$max = product_max_qty($p);
$methods = order_pay_methods();
$deliveries = delivery_options();
$errors = [];
$old = ['qty' => max(1, min(max(1, $max), (int) ($_GET['adet'] ?? 1))), 'name' => $me['name'] ?? '', 'email' => $me['email'] ?? '', 'phone' => $me['phone'] ?? '', 'delivery' => $deliveries[0], 'address' => '', 'city' => $me['city'] ?? '', 'note' => '', 'method' => $methods[0] ?? ''];
$invOld = ['type' => ($_POST['inv_type'] ?? '') === 'kurumsal' ? 'kurumsal' : 'bireysel', 'title' => '', 'tax_office' => '', 'tax_no' => '', 'tckn' => '', 'address' => ''];
foreach (['title', 'tax_office', 'tax_no', 'tckn', 'address'] as $k) $invOld[$k] = trim((string) ($_POST['inv_' . $k] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $max > 0 && $methods) {
  foreach ($old as $k => $_) if (isset($_POST[$k])) $old[$k] = trim(str_replace("\r\n", "\n", (string) $_POST[$k]));
  $old['qty'] = max(1, (int) $old['qty']);
  if ($me) { if (!member_csrf_ok()) $errors[] = 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.'; }
  else { $g = guard_check('siparis|' . $p['id']); if ($g === 'bot') { header('Location: /'); exit; } if ($g !== '') $errors[] = $g; }
  if (!$me) {
    if (mb_strlen($old['name']) < 3 || mb_strlen($old['name']) > 60 || preg_match('#https?://|www\.|[a-z0-9-]\.[a-z]{2,}\b|[/@<>]#iu', $old['name'])) $errors[] = 'Adınızı ve soyadınızı yazın.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta adresi yazın.';
  }
  if (!preg_match('/^\+?[0-9 ()-]{10,20}$/', $old['phone'])) $errors[] = 'Telefon numaranızı yazın (ör. 0507 542 87 98).';
  if (!in_array($old['delivery'], $deliveries, true)) $errors[] = 'Bir teslimat şekli seçin.';
  if ($old['delivery'] === 'kargo') {
    if (mb_strlen($old['address']) < 10) $errors[] = 'Teslimat adresinizi açık olarak yazın (mahalle, sokak, bina ve daire no).';
    if (mb_strlen($old['city']) < 3) $errors[] = 'İl ve ilçeyi yazın.';
  }
  $old['address'] = mb_substr($old['address'], 0, 400);
  $old['city'] = mb_substr($old['city'], 0, 80);
  if (!in_array($old['method'], $methods, true)) $errors[] = 'Bir ödeme yöntemi seçin.';
  // Fatura adresi boşsa teslimat adresi kullanılır
  if (trim((string) ($_POST['inv_address'] ?? '')) === '' && $old['delivery'] === 'kargo') $_POST['inv_address'] = trim($old['address'] . ' ' . $old['city']);
  $inv = invoice_from_post($errors);
  if (empty($_POST['kosul'])) $errors[] = 'Ön bilgilendirme formu, mesafeli satış sözleşmesi ve aydınlatma metnini onaylayın.';
  if (mb_strlen($old['note']) > 500) $errors[] = 'Not en fazla 500 karakter olabilir.';
  if (!$errors && !rate_hit('siparis:' . client_hash(), 12, 5)) $errors[] = 'Kısa sürede çok fazla deneme yaptınız, biraz sonra tekrar deneyin.';

  if (!$errors) {
    $now = date('Y-m-d H:i');
    $res = json_update(ORDERS_FILE, function (array &$d) use ($p, $old, $me, $inv, $now) {
      $d['orders'] ??= [];
      // Stok ürün kaydından güncel olarak okunur (aynı anda gelen siparişler stoğu aşamasın diye sipariş listesi kilitliyken)
      $fresh = null;
      foreach (json_read(SHOP_FILE, SHOP_DEFAULT)['products'] ?? [] as $x) if ($x['id'] === $p['id']) $fresh = $x;
      if (!$fresh || !product_visible($fresh)) return ['err', 'Bu ürün artık satışta değil.'];
      $left = product_max_qty($fresh, $d['orders']);
      if ($left <= 0) return ['err', 'Bu ürün tükendi.'];
      if ($old['qty'] > $left) return ['err', 'Bu üründen en fazla ' . $left . ' adet sipariş verebilirsiniz.'];
      $price = (float) $fresh['price'];
      $sub = round($price * $old['qty'], 2);
      $ship = shipping_fee($old['delivery'], $sub);
      $o = [
        'id' => new_id(), 'code' => order_code($d['orders']), 'product' => $fresh['id'], 'product_name' => $fresh['name'],
        'qty' => $old['qty'], 'unit_price' => $price, 'subtotal' => $sub, 'shipping' => $ship, 'total' => round($sub + $ship, 2),
        'user' => $me['id'] ?? '', 'name' => $me['name'] ?? $old['name'], 'email' => $me['email'] ?? $old['email'], 'phone' => $old['phone'],
        'delivery' => $old['delivery'], 'address' => $old['delivery'] === 'kargo' ? trim($old['address'] . "\n" . $old['city']) : '',
        'note' => $old['note'], 'method' => $old['method'], 'invoice' => $inv, 'inv_no' => '', 'inv_date' => '',
        'status' => 'beklemede', 'paid' => false, 'stock_taken' => false, 'cargo' => '', 'admin_note' => '', 'created' => $now, 'updated' => $now,
      ];
      $d['orders'][] = $o;
      return ['ok', $o];
    }, ['orders' => []]);
    if ($res[0] === 'err') $errors[] = $res[1];
    else {
      $o = $res[1];
      mail_order($o);
      if ($o['method'] === 'kart') { header('Location: ' . order_pay_url($o), true, 303); exit; }
      header('Location: ' . order_url($o, true) . '&yeni=1', true, 303);
      exit;
    }
  }
}

$title = 'Sipariş · ' . $p['name'] . ' · ' . $c['brand']['name'];
$noindex = true;
$tok = $me ? member_csrf() : '';
$img = product_cover($p);
$fee = (float) shop_setting('shipping_fee', 0);
$freeOver = (float) shop_setting('free_over', 0);
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap join">
    <a class="back" href="<?= e(product_url($p)) ?>"><?= icon('sol') ?>Ürüne dön</a>
    <div class="join__grid">
      <form class="join__form" method="post" data-order data-price="<?= (float) $p['price'] ?>" data-fee="<?= $fee ?>" data-free="<?= $freeOver ?>"<?= !$me ? ' data-guard-form' : '' ?> novalidate>
        <p class="label">Sipariş</p>
        <h1 class="h2"><?= e($p['name']) ?></h1>
        <?php if ($errors): ?><div class="notice notice--err" role="alert"><?= icon('bilgi') ?><div><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div></div><?php endif; ?>
        <?php if ($max <= 0): ?><p class="notice notice--warn"><?= icon('bilgi') ?>Bu ürün şu an tükendi.</p>
        <?php elseif (!$methods): ?><p class="notice notice--warn"><?= icon('bilgi') ?>Online sipariş şu an alınamıyor. Sipariş vermek için bize <?= trim($c['contact']['whatsapp'] ?? '') !== '' ? '<a href="' . e(wa_href($c['contact']['whatsapp'], $p['name'] . ' sipariş vermek istiyorum.')) . '" target="_blank" rel="noopener">WhatsApp</a>\'tan' : 'telefonla' ?> ulaşın.</p>
        <?php else: ?>
        <?php if ($me): ?><input type="hidden" name="csrf" value="<?= e($tok) ?>"><?php else: ?><?= guard_fields('siparis|' . $p['id']) ?><?php endif; ?>

        <fieldset class="fs">
          <legend><span class="fs__n">1</span>Adet</legend>
          <div class="qty">
            <label for="qty">Adet</label>
            <div class="qty__ctl"><button type="button" class="btn btn--ghost btn--icon" data-qty="-1" aria-label="Azalt">−</button><input id="qty" name="qty" type="number" min="1" max="<?= $max ?>" value="<?= (int) $old['qty'] ?>" inputmode="numeric"><button type="button" class="btn btn--ghost btn--icon" data-qty="1" aria-label="Artır">+</button></div>
            <span class="muted small">Stokta <?= product_available($p) ?> adet</span>
          </div>
        </fieldset>

        <fieldset class="fs">
          <legend><span class="fs__n">2</span>İletişim bilgileri</legend>
          <?php if ($me): ?>
            <div class="whois"><?= avatar($me, 'sm') ?><span><strong><?= e($me['name']) ?></strong><span class="muted"><?= e($me['email']) ?></span></span></div>
          <?php else: ?>
            <div class="grid2"><label class="field">Ad soyad<input name="name" value="<?= e($old['name']) ?>" required autocomplete="name"></label><label class="field">E-posta<input type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email"></label></div>
          <?php endif; ?>
          <label class="field">Telefon<input type="tel" name="phone" value="<?= e($old['phone']) ?>" required autocomplete="tel" placeholder="05xx xxx xx xx"></label>
        </fieldset>

        <fieldset class="fs">
          <legend><span class="fs__n">3</span>Teslimat</legend>
          <div class="opts">
            <?php foreach ($deliveries as $dv): ?>
              <label class="opt opt--row"><input type="radio" name="delivery" value="<?= $dv ?>"<?= $old['delivery'] === $dv ? ' checked' : '' ?> required data-delivery><span class="opt__body"><strong><?= e(DELIVERY[$dv]) ?></strong><span><?= $dv === 'kargo' ? e(($fee > 0 ? 'Kargo ücreti ' . money($fee) . ($freeOver > 0 ? ', ' . money($freeOver) . ' ve üzeri siparişlerde ücretsiz' : '') : 'Kargo ücretsiz') . (trim((string) shop_setting('ship_days', '')) !== '' ? ' · ' . shop_setting('ship_days') : '')) : e(trim((string) shop_setting('pickup_note', '')) ?: 'Siparişiniz hazır olunca size haber veririz.') ?></span></span></label>
            <?php endforeach; ?>
          </div>
          <div data-ship<?= $old['delivery'] !== 'kargo' ? ' hidden' : '' ?>>
            <label class="field">Teslimat adresi <span class="opt-l">(mahalle, sokak, bina ve daire no)</span><textarea name="address" rows="2" maxlength="400" autocomplete="street-address"><?= e($old['address']) ?></textarea></label>
            <label class="field">İl / ilçe<input name="city" value="<?= e($old['city']) ?>" maxlength="80" placeholder="İstanbul / Silivri"></label>
          </div>
          <label class="field">Notunuz <span class="opt-l">(isteğe bağlı)</span><textarea name="note" rows="2" maxlength="500"><?= e($old['note']) ?></textarea></label>
        </fieldset>

        <?php $corp = $invOld['type'] === 'kurumsal'; ?>
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
          <label class="field">Fatura adresi <span class="opt-l">(kargoda boş bırakırsanız teslimat adresi kullanılır)</span><input name="inv_address" value="<?= e($invOld['address']) ?>" maxlength="300" autocomplete="street-address"></label>
        </fieldset>

        <fieldset class="fs">
          <legend><span class="fs__n">5</span>Ödeme</legend>
          <div class="opts">
            <?php foreach ($methods as $m): ?>
              <label class="opt opt--row"><input type="radio" name="method" value="<?= $m ?>"<?= $old['method'] === $m ? ' checked' : '' ?> required><span class="opt__body"><strong><?= e(ORDER_PAY_METHODS[$m]) ?></strong><span><?= e(['kart' => 'PayTR güvenli ödeme sayfasında kartınızla ödersiniz. Ödeme alınınca siparişiniz hazırlanmaya başlar.', 'havale' => 'Siparişinizden sonra hesap bilgileri gösterilir. Ödemeniz ulaşınca siparişiniz hazırlanır.'][$m]) ?></span></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <label class="consent"><input type="checkbox" name="kosul" value="1"<?= !empty($_POST['kosul']) ? ' checked' : '' ?> required><span><a href="/mesafeli-satis/#urun-satisi" target="_blank">Ön bilgilendirme formu ve mesafeli satış sözleşmesini</a> ve <a href="/gizlilik/" target="_blank">aydınlatma metnini</a> okudum, kabul ediyorum.</span></label>
        <div class="join__submit"><button class="btn btn--lg" type="submit" data-join-btn>Siparişi onayla</button><p class="cform__msg" role="status" data-form-msg></p></div>
        <?php endif; ?>
      </form>

      <aside class="join__sum">
        <div class="sum">
          <?php if ($img !== ''): ?><img src="<?= e($img) ?>" alt="" class="sum__img"><?php endif; ?>
          <div class="sum__body">
            <strong><?= e($p['name']) ?></strong>
            <span class="muted small"><?= money($p['price']) ?> / adet</span>
            <dl class="sum__rows">
              <div><dt>Adet</dt><dd data-sum-qty><?= (int) $old['qty'] ?></dd></div>
              <div><dt>Ürün tutarı</dt><dd data-sum-sub><?= money((float) $p['price'] * (int) $old['qty']) ?></dd></div>
              <div><dt>Kargo</dt><dd data-sum-ship><?= $old['delivery'] === 'kargo' ? ((($s = shipping_fee('kargo', (float) $p['price'] * (int) $old['qty'])) > 0) ? money($s) : 'Ücretsiz') : '–' ?></dd></div>
              <div class="sum__total"><dt>Toplam</dt><dd data-sum-total><?= money((float) $p['price'] * (int) $old['qty'] + shipping_fee($old['delivery'], (float) $p['price'] * (int) $old['qty'])) ?></dd></div>
            </dl>
            <p class="muted small">Fiyatlara KDV dahildir.</p>
          </div>
        </div>
      </aside>
    </div>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
