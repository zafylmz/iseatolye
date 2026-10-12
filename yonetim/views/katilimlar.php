<?php
/** @var array $regs */ /** @var string $tok */
$fe = (string) ($_GET['etkinlik'] ?? '');
$fs = (string) ($_GET['oturum'] ?? '');
$fst = (string) ($_GET['durum'] ?? '');
$fpay = (string) ($_GET['odeme'] ?? '');
$fq = trim((string) ($_GET['q'] ?? ''));
$ev = $fe !== '' ? event_by_id($fe) : null;
if (!$ev) { $fe = ''; $fs = ''; }
$self = panel_url(['s' => 'katilimlar', 'etkinlik' => $fe, 'oturum' => $fs, 'durum' => $fst, 'odeme' => $fpay, 'q' => $fq]);

$rows = array_values(array_filter($regs, function ($r) use ($fe, $fs, $fst, $fpay, $fq) {
  if ($fe !== '' && $r['event'] !== $fe) return false;
  if ($fs !== '' && $r['session'] !== $fs) return false;
  if ($fst !== '' && $r['status'] !== $fst) return false;
  if ($fst === '' && $fe === '' && $r['status'] === 'iptal') return false;
  if ($fpay === 'odenmedi' && ($r['paid'] || $r['total'] <= 0 || !in_array($r['status'], SEAT_STATUSES, true))) return false;
  if ($fq !== '' && !str_contains(mb_strtolower($r['name'] . ' ' . $r['email'] . ' ' . $r['phone'] . ' ' . $r['code']), mb_strtolower($fq))) return false;
  return true;
}));
usort($rows, fn($a, $b) => strcmp($b['created'], $a['created']));

// Etkinlik seçmek için: yaklaşanlar önce
$evs = events_all();
usort($evs, fn($a, $b) => [event_is_past($a), event_is_past($a) ? -event_sort_ts($a) : event_sort_ts($a)] <=> [event_is_past($b), event_is_past($b) ? -event_sort_ts($b) : event_sort_ts($b)]);
?>
<div class="bar">
  <h1><?= $ev ? e($ev['title']) : 'Katılımlar' ?></h1>
  <?php if ($ev): ?><div class="bar__act"><a class="btn btn--ghost" href="./?s=etkinlik&amp;id=<?= e(urlencode($ev['id'])) ?>">Etkinliği düzenle</a><a class="btn btn--ghost" href="<?= e(event_url($ev)) ?>" target="_blank" rel="noopener">Sitede gör ↗</a></div><?php endif; ?>
</div>

<form class="toolbar toolbar--filters" method="get">
  <input type="hidden" name="s" value="katilimlar">
  <select name="etkinlik" onchange="this.form.oturum && (this.form.oturum.value='');this.form.submit()">
    <option value="">Tüm etkinlikler</option>
    <?php foreach ($evs as $x): ?><option value="<?= e($x['id']) ?>"<?= $fe === $x['id'] ? ' selected' : '' ?>><?= e((event_is_past($x) ? '· ' : '') . $x['title'] . ' (' . tr_date_short((next_session($x)['date'] ?? '')) . ')') ?></option><?php endforeach; ?>
  </select>
  <?php if ($ev && !is_package($ev) && count($ev['sessions']) > 1): ?>
    <select name="oturum" onchange="this.form.submit()"><option value="">Tüm tarihler</option><?php foreach (event_sessions($ev) as $s): ?><option value="<?= e($s['id']) ?>"<?= $fs === $s['id'] ? ' selected' : '' ?>><?= e(session_when($s, false) . ' · ' . session_place($s, false)) ?></option><?php endforeach; ?></select>
  <?php endif; ?>
  <select name="durum" onchange="this.form.submit()"><option value="">Etkin kayıtlar</option><?php foreach (REG_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $fst === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <label class="check"><input type="checkbox" name="odeme" value="odenmedi"<?= $fpay === 'odenmedi' ? ' checked' : '' ?> onchange="this.form.submit()"> Ödeme bekleyenler</label>
  <input type="search" name="q" value="<?= e($fq) ?>" placeholder="Ad, e-posta, telefon, kod">
  <button class="btn btn--ghost">Filtrele</button>
</form>

<?php if ($ev):
  $evRegs = regs_of_event($ev['id'], $regs);
  $sessions = is_package($ev) ? [['id' => '*', 'label' => 'Tüm buluşmalar (' . count(event_sessions($ev, false)) . ')', 'cap' => (int) ($ev['capacity'] ?? 0)]] : array_map(fn($s) => ['id' => $s['id'], 'label' => session_when($s, false) . ' · ' . session_place($s, false) . (($s['status'] ?? '') === 'iptal' ? ' · İptal' : ''), 'cap' => (int) ($s['capacity'] ?? 0)], event_sessions($ev));
  $paidSum = array_sum(array_map(fn($r) => in_array($r['status'], SEAT_STATUSES, true) && $r['paid'] ? $r['total'] : 0, $evRegs));
  $expSum = array_sum(array_map(fn($r) => in_array($r['status'], SEAT_STATUSES, true) ? $r['total'] : 0, $evRegs));
?>
  <section class="card">
    <div class="card__head"><h2>Doluluk</h2><span class="hint">Tahsil edilen <b><?= e(money($paidSum)) ?></b> / beklenen <?= e(money($expSum)) ?></span></div>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Tarih</th><th>Kayıtlı</th><th>Onaylı</th><th>Bekleyen</th><th>Yedek</th><th>Kalan</th><th>Gelen</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($sessions as $s):
          $sr = array_filter($evRegs, fn($r) => $r['session'] === $s['id'] || is_package($ev));
          $sum = fn(string $st) => array_sum(array_map(fn($r) => $r['status'] === $st ? (int) $r['seats'] : 0, $sr));
          $taken = $sum('onayli') + $sum('beklemede');
          $in = array_sum(array_map(fn($r) => $r['checked_in'] && $r['status'] !== 'iptal' ? (int) $r['seats'] : 0, $sr)); ?>
          <tr>
            <td><a href="<?= e(panel_url(['s' => 'katilimlar', 'etkinlik' => $fe, 'oturum' => is_package($ev) ? '' : $s['id']])) ?>"><?= e($s['label']) ?></a></td>
            <td><b><?= $taken ?></b><?= $s['cap'] ? ' / ' . $s['cap'] : '' ?></td>
            <td><?= $sum('onayli') ?></td><td><?= $sum('beklemede') ?></td><td><?= $sum('yedek') ?></td>
            <td><?= $s['cap'] ? max(0, $s['cap'] - $taken) : '∞' ?></td>
            <td><?= $in ?></td>
            <td><a href="./?s=yoklama&amp;etkinlik=<?= e(urlencode($ev['id'])) ?>&amp;oturum=<?= e(urlencode($s['id'])) ?>" target="_blank" rel="noopener">Yoklama listesi</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="plist__act plist__act--start">
      <a class="btn btn--ghost" href="<?= e(panel_url(['s' => 'csv', 'etkinlik' => $fe, 'oturum' => $fs])) ?>">Excel için indir (CSV)</a>
      <button type="button" class="btn btn--ghost" data-toggle="#ekle">+ Elle kayıt ekle</button>
      <button type="button" class="btn btn--ghost" data-toggle="#duyuru">Katılımcılara e-posta</button>
    </div>
  </section>

  <section class="card" id="ekle" hidden>
    <h2>Elle kayıt ekle</h2>
    <p class="hint">Telefonla, WhatsApp'tan ya da kurumsal grup için aldığınız kayıtları buradan ekleyin. Kontenjan kontrolü yapılmaz, aşılırsa uyarılırsınız.</p>
    <form method="post" class="form-grid">
      <?= hidden($tok, 'kayit-ekle', ['event' => $ev['id']]) ?>
      <div class="grid3">
        <label>Ad soyad<input name="name" required></label>
        <label>E-posta <span class="opt">(isteğe bağlı)</span><input type="email" name="email"></label>
        <label>Telefon<input name="phone"></label>
        <?php if (!is_package($ev)): ?><label>Tarih<select name="session"><?php foreach (event_sessions($ev, false) as $s): ?><option value="<?= e($s['id']) ?>"<?= $fs === $s['id'] ? ' selected' : '' ?>><?= e(session_when($s, false)) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <label>Bilet<select name="ticket"><?php foreach (event_tickets($ev) as $t): ?><option value="<?= e($t['id']) ?>"><?= e($t['name'] . ' · ' . ($t['price'] > 0 ? money($t['price']) : 'Ücretsiz')) ?></option><?php endforeach; ?></select></label>
        <label>Adet<input type="number" name="qty" value="1" min="1" max="50"></label>
        <label>Durum<select name="status"><?php foreach (REG_STATUS as $k => $l): if ($k === 'iptal') continue; ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></label>
        <label>Ödeme<select name="method"><option value="">Belirtilmedi</option><?php foreach (PAY_METHODS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Gelecek diğer kişiler<input name="others"></label>
      </div>
      <label>Not <span class="opt">(yalnızca panelde görünür)</span><input name="admin_note"></label>
      <div class="checks"><label class="check"><input type="checkbox" name="paid" value="1"> Ödeme alındı</label><label class="check"><input type="checkbox" name="notify" value="1" checked> Kişiye kayıt e-postası gönder</label></div>
      <div><button class="btn">Kaydı ekle</button></div>
    </form>
  </section>

  <section class="card" id="duyuru" hidden>
    <h2>Katılımcılara e-posta</h2>
    <p class="hint">Hatırlatma, mekan değişikliği ya da etkinlik sonrası teşekkür için. Her kişiye kendi kayıt bağlantısıyla ayrı ayrı gönderilir.</p>
    <form method="post" data-confirm="E-posta seçtiğiniz kişilere gönderilecek. Emin misiniz?">
      <?= hidden($tok, 'duyuru', ['event' => $ev['id'], 'session' => $fs]) ?>
      <label>Konu<input name="subject" value="<?= e($ev['title']) ?>: hatırlatma" required></label>
      <label>Mesaj<textarea name="text" rows="6" required>Etkinliğimiz yaklaşıyor! <?= e(event_when($ev)) ?> tarihinde <?= e(session_place(next_session($ev))) ?> adresinde buluşuyoruz.

Sorunuz olursa bu e-postayı yanıtlayabilir ya da WhatsApp'tan yazabilirsiniz.</textarea></label>
      <div class="checks"><?php foreach (['onayli' => 'Onaylı', 'beklemede' => 'Onay/ödeme bekleyen', 'yedek' => 'Yedek listedekiler'] as $k => $l): ?><label class="check"><input type="checkbox" name="who[]" value="<?= $k ?>"<?= $k !== 'yedek' ? ' checked' : '' ?>> <?= $l ?></label><?php endforeach; ?></div>
      <?php if ($fs !== ''): ?><p class="hint">Yalnızca seçili tarihteki kişilere gönderilir.</p><?php endif; ?>
      <div><button class="btn">Gönder</button></div>
    </form>
  </section>
<?php endif; ?>

<section class="card card--flush">
  <div class="card__head card__head--pad"><h2><?= count($rows) ?> kayıt</h2><?php if (!$ev): ?><a href="<?= e(panel_url(['s' => 'csv', 'durum' => $fst])) ?>">Tümünü indir (CSV)</a><?php endif; ?></div>
  <?php if (!$rows): ?><p class="hint pad">Bu filtreye uyan kayıt yok.</p><?php else: ?>
  <form method="post" id="toplu-sil" class="toolbar pad" data-confirm="Seçilen kayıtlar kalıcı olarak silinecek (deneme kayıtları için). Gerçek bir kaydı iptal etmeniz genelde yeterlidir. Emin misiniz?"><?= hidden($tok, 'kayit-toplu-sil', ['back' => $self]) ?><button class="btn btn--danger btn--sm" data-regsel-btn disabled>Seçilenleri sil</button></form>
  <div class="tbl-wrap"><table class="tbl tbl--left tbl--regs">
    <thead><tr><th><input type="checkbox" data-regsel-all aria-label="Tümünü seç"></th><th>Kişi</th><?php if (!$ev): ?><th>Etkinlik</th><?php endif; ?><th>Tarih / bilet</th><th>Durum</th><th>Ödeme</th><th>İşlem</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): $rev = $ev ?? event_by_id($r['event']);
        $quick = function (string $do, string $label, string $cls = 'btn--ghost', bool $notify = false) use ($tok, $r, $self) {
          return '<form method="post">' . hidden($tok, 'kayit-hizli', ['id' => $r['id'], 'do' => $do, 'back' => $self] + ($notify ? ['notify' => '1'] : [])) . '<button class="btn btn--sm ' . $cls . '">' . e($label) . '</button></form>';
        }; ?>
        <tr class="<?= $r['status'] === 'iptal' ? 'is-off' : '' ?>">
          <td><input type="checkbox" name="ids[]" value="<?= e($r['id']) ?>" form="toplu-sil" data-regsel aria-label="Seç: <?= e($r['code']) ?>"></td>
          <td><a href="./?s=kayit&amp;id=<?= e(urlencode($r['id'])) ?>"><strong><?= e($r['name']) ?></strong></a><?= $r['user'] ? ' <span class="tag">üye</span>' : '' ?><small><?= e($r['phone']) ?><?= $r['email'] !== '' ? ' · ' . e($r['email']) : '' ?></small><small class="mono-s"><?= e($r['code']) ?> · <?= e(time_ago($r['created'])) ?><?= ($r['source'] ?? '') === 'panel' ? ' · panelden' : '' ?></small><?php if (trim($r['note'] ?? '') !== ''): ?><small class="note">“<?= e($r['note']) ?>”</small><?php endif; ?></td>
          <?php if (!$ev): ?><td><a href="<?= e(panel_url(['s' => 'katilimlar', 'etkinlik' => $r['event']])) ?>"><?= e($rev['title'] ?? 'Silinmiş etkinlik') ?></a></td><?php endif; ?>
          <td><?= e(reg_session_label($r, $rev)) ?><small><?= e($r['ticket_name']) ?> × <?= (int) $r['qty'] ?><?= (int) $r['seats'] !== (int) $r['qty'] ? ' (' . (int) $r['seats'] . ' kişi)' : '' ?></small><?php if (trim($r['others'] ?? '') !== ''): ?><small>+ <?= e($r['others']) ?></small><?php endif; ?></td>
          <td><?= pill(REG_STATUS[$r['status']] ?? $r['status'], reg_tone($r['status'])) ?><?= $r['checked_in'] ? '<small class="ok">✓ geldi</small>' : '' ?><?= hold_expired($r) ? ' <small class="muted" title="Ödeme süresi doldu; kontenjandan düştü. Ödeme gelirse ödendi işaretleyin.">süre doldu</small>' : '' ?></td>
          <td><?php if ($r['total'] > 0): ?><?= e(money($r['total'])) ?><small class="<?= $r['paid'] ? 'ok' : 'warn' ?>"><?= $r['paid'] ? 'Ödendi' : 'Bekliyor' ?><?= $r['method'] ? ' · ' . e(PAY_METHODS[$r['method']] ?? $r['method']) : '' ?></small><?php else: ?>Ücretsiz<?php endif; ?></td>
          <td class="tbl__act">
            <?php if ($r['status'] === 'beklemede'): ?>
              <?= $r['total'] > 0 && !$r['paid'] ? $quick('odendi', 'Ödendi, onayla', 'btn--sm', true) : $quick('onayla', 'Onayla', '', true) ?>
            <?php elseif ($r['status'] === 'yedek'): ?>
              <?= $quick('yedekten', 'Yedekten al', '', true) ?>
            <?php elseif ($r['status'] === 'onayli'): ?>
              <?= $r['total'] > 0 && !$r['paid'] ? $quick('odendi', 'Ödendi', 'btn--ghost', true) : '' ?>
              <?= $r['checked_in'] ? $quick('gelmedi', 'Geldi ✓', 'btn--ghost') : $quick('geldi', 'Geldi') ?>
            <?php endif; ?>
            <a class="btn btn--ghost btn--sm" href="./?s=kayit&amp;id=<?= e(urlencode($r['id'])) ?>">Aç</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<p class="hint">Onay, ödeme ve yedekten alma işlemlerinde kişiye otomatik bilgi e-postası gider. Yer tutan kayıtlar: "Onaylandı" ve "Onay bekliyor". Bir kaydı iptal ettiğinizde yeri boşalır; yedek listeden birini kayda alabilirsiniz.</p>
