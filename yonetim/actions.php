<?php
// Panelde gönderilen formlar. Her işlem sonunda bir sayfaya yönlendirilir.
declare(strict_types=1);
/** @var string $action */

$c = content();
$back = (string) ($_POST['back'] ?? '');
$back = preg_match('#^\./\?[A-Za-z0-9_=&%.+-]*$#', $back) ? $back : '';

switch ($action) {

  // ---------- Etkinlikler ----------
  case 'etkinlik':
    $id = (string) ($_POST['id'] ?? '');
    $title = post('title');
    if ($title === '') throw new RuntimeException('Etkinlik adı boş olamaz.');
    $regsNow = regs_all();

    // Oturumlar
    $sessions = [];
    foreach (array_values((array) ($_POST['sessions'] ?? [])) as $row) {
      $date = valid_date(trim((string) ($row['date'] ?? '')));
      if ($date === '') continue;
      $sessions[] = [
        'id' => preg_replace('/[^a-z0-9]/', '', (string) ($row['id'] ?? '')),
        'date' => $date,
        'start' => valid_time(trim((string) ($row['start'] ?? ''))),
        'end' => valid_time(trim((string) ($row['end'] ?? ''))),
        'venue' => (string) ($row['venue'] ?? ''),
        'place' => mb_substr(trim((string) ($row['place'] ?? '')), 0, 160),
        'capacity' => max(0, (int) ($row['capacity'] ?? 0)),
        'status' => in_array($row['status'] ?? '', ['acik', 'dolu', 'iptal'], true) ? $row['status'] : 'acik',
        'note' => mb_substr(trim((string) ($row['note'] ?? '')), 0, 160),
      ];
    }
    if (!$sessions && post('status') === 'yayinda') throw new RuntimeException('Yayındaki bir etkinliğin en az bir tarihi olmalı. Tarih ekleyin ya da taslak olarak kaydedin.');
    usort($sessions, fn($a, $b) => strcmp($a['date'] . $a['start'], $b['date'] . $b['start']));
    $used = array_filter(array_column($sessions, 'id'));
    $n = 1;
    foreach ($sessions as &$s) { if ($s['id'] === '') { while (in_array('s' . $n, $used, true)) $n++; $s['id'] = 's' . $n; $used[] = $s['id']; } }
    unset($s);

    // Biletler
    $tickets = [];
    foreach (array_values((array) ($_POST['tickets'] ?? [])) as $row) {
      $name = mb_substr(trim((string) ($row['name'] ?? '')), 0, 80);
      if ($name === '') continue;
      $tickets[] = [
        'id' => preg_replace('/[^a-z0-9]/', '', (string) ($row['id'] ?? '')),
        'name' => $name,
        'price' => price_input((string) ($row['price'] ?? '0')),
        'seats' => max(1, min(20, (int) ($row['seats'] ?? 1))),
        'limit' => max(0, (int) ($row['limit'] ?? 0)),
        'until' => valid_date(trim((string) ($row['until'] ?? ''))),
        'note' => mb_substr(trim((string) ($row['note'] ?? '')), 0, 120),
      ];
    }
    $used = array_filter(array_column($tickets, 'id'));
    $n = 1;
    foreach ($tickets as &$t) { if ($t['id'] === '') { while (in_array('t' . $n, $used, true)) $n++; $t['id'] = 't' . $n; $used[] = $t['id']; } }
    unset($t);

    $faq = [];
    foreach (array_values((array) ($_POST['faq'] ?? [])) as $row) {
      $q = trim((string) ($row['q'] ?? '')); $a = trim(str_replace("\r\n", "\n", (string) ($row['a'] ?? '')));
      if ($q !== '' && $a !== '') $faq[] = ['q' => mb_substr($q, 0, 200), 'a' => mb_substr($a, 0, 1500)];
    }

    $saved = catalog_update(function (array &$d) use ($id, $title, $sessions, $tickets, $faq, $regsNow) {
      $i = $id === '' ? false : array_search($id, array_column($d['events'], 'id'), true);
      $ev = $i === false ? ['id' => 'e' . substr(bin2hex(random_bytes(4)), 0, 7), 'slug' => '', 'cover' => '', 'gallery' => [], 'created' => date('Y-m-d H:i')] : $d['events'][$i];
      // Kaydı olan bir oturum silinemez; iptal edilmesi istenir
      $keep = array_column($sessions, 'id');
      foreach ($ev['sessions'] ?? [] as $old) {
        if (in_array($old['id'], $keep, true)) continue;
        foreach ($regsNow as $r) if ($r['event'] === $ev['id'] && $r['session'] === $old['id'] && $r['status'] !== 'iptal') {
          throw new RuntimeException(tr_date($old['date']) . ' tarihli oturumda kayıtlı katılımcılar var. Bu oturumu silmek yerine durumunu "İptal edildi" yapın ya da önce kayıtları başka bir tarihe taşıyın.');
        }
      }
      $ev['title'] = $title;
      $ev['status'] = array_key_exists(post('status'), EVENT_STATUS) ? post('status') : 'taslak';
      $ev['summary'] = mb_substr(post('summary'), 0, 300);
      $ev['category'] = post('category');
      $ev['body'] = post('body');
      $ev['level'] = array_key_exists(post('level'), LEVELS) ? post('level') : '';
      $ev['age'] = mb_substr(post('age'), 0, 40);
      $ev['duration'] = mb_substr(post('duration'), 0, 60);
      $ev['includes'] = lines(post('includes'));
      $ev['bring'] = lines(post('bring'));
      $ev['faq'] = $faq;
      $ev['instructors'] = array_values(array_intersect((array) ($_POST['instructors'] ?? []), array_column($d['instructors'], 'id')));
      $ev['sessions'] = $sessions;
      $ev['package'] = !empty($_POST['package']);
      $ev['capacity'] = post_int('capacity');
      $ev['tickets'] = $tickets;
      $ev['pay_methods'] = array_values(array_intersect((array) ($_POST['pay_methods'] ?? []), array_keys(PAY_METHODS)));
      $ev['pay_link'] = filter_var(post('pay_link'), FILTER_VALIDATE_URL) ? post('pay_link') : '';
      foreach (['reg_open', 'waitlist', 'approval', 'show_left', 'show_attendees', 'comments', 'featured', 'pinned'] as $k) $ev[$k] = !empty($_POST[$k]);
      $ev['reg_close_hours'] = post_int('reg_close_hours', 0, 24 * 30);
      $ev['max_per_order'] = post_int('max_per_order', 1, 50);
      $ev['cancel_policy'] = post('cancel_policy');
      $ev['seo_title'] = mb_substr(post('seo_title'), 0, 90);
      $ev['cover'] = image_pick('cover', (string) ($ev['cover'] ?? ''));
      $ev['gallery'] = gallery_pick('gallery', $ev['gallery'] ?? []);
      $want = slugify(post('slug') ?: $title, 'etkinlik');
      $taken = array_column(array_filter($d['events'], fn($x) => $x['id'] !== $ev['id']), 'slug');
      $new = $want; $n = 2;
      while (in_array($new, $taken, true)) $new = $want . '-' . $n++;
      $ev['slug'] = $new;
      $ev['updated'] = date('Y-m-d H:i');
      if ($i === false) $d['events'][] = $ev; else $d['events'][$i] = $ev;
      return $ev;
    });
    $warn = '';
    if ($saved['status'] === 'yayinda' && !$saved['tickets']) $warn = ' Bilet tanımlanmadığı için etkinlik ücretsiz görünür.';
    flash('"' . $saved['title'] . '" kaydedildi (' . EVENT_STATUS[$saved['status']] . ').' . $warn);
    redirect('./?s=etkinlik&id=' . urlencode($saved['id']));

  case 'etkinlik-kopyala':
    $id = (string) ($_POST['id'] ?? '');
    $copy = catalog_update(function (array &$d) use ($id) {
      $i = array_search($id, array_column($d['events'], 'id'), true);
      if ($i === false) throw new RuntimeException('Etkinlik bulunamadı.');
      $ev = $d['events'][$i];
      $ev['id'] = 'e' . substr(bin2hex(random_bytes(4)), 0, 7);
      $ev['title'] .= ' (kopya)';
      $want = $ev['slug'] . '-kopya'; $new = $want; $n = 2;
      $taken = array_column($d['events'], 'slug');
      while (in_array($new, $taken, true)) $new = $want . '-' . $n++;
      $ev['slug'] = $new;
      $ev['status'] = 'taslak';
      $ev['featured'] = false; $ev['pinned'] = false;
      $ev['created'] = $ev['updated'] = date('Y-m-d H:i');
      $d['events'][] = $ev;
      return $ev;
    });
    flash('Etkinliğin bir kopyası taslak olarak oluşturuldu. Tarihleri güncelleyip yayına alabilirsiniz.');
    redirect('./?s=etkinlik&id=' . urlencode($copy['id']));

  case 'etkinlik-sil':
    $id = (string) ($_POST['id'] ?? '');
    $ev = event_by_id($id);
    if (!$ev) throw new RuntimeException('Etkinlik bulunamadı.');
    catalog_update(function (array &$d) use ($id) { $d['events'] = array_values(array_filter($d['events'], fn($x) => $x['id'] !== $id)); });
    if (data_exists(REGS_FILE)) { backup_file(REGS_FILE); regs_update(function (array &$d) use ($id) { $d['regs'] = array_values(array_filter($d['regs'], fn($r) => $r['event'] !== $id)); }); }
    foreach ([COMMENTS_FILE, INTERESTS_FILE] as $f) if (is_file($f)) json_update($f, function (array &$d) use ($id) { unset($d[$id]); });
    flash('"' . $ev['title'] . '" silindi.');
    redirect('./?s=etkinlikler');

  case 'etkinlik-durum':
    $id = (string) ($_POST['id'] ?? ''); $st = post('status');
    if (!array_key_exists($st, EVENT_STATUS)) throw new RuntimeException('Geçersiz durum.');
    catalog_update(function (array &$d) use ($id, $st) { foreach ($d['events'] as &$x) if ($x['id'] === $id) { $x['status'] = $st; $x['updated'] = date('Y-m-d H:i'); } });
    flash('Etkinlik durumu: ' . EVENT_STATUS[$st] . '.');
    redirect($back ?: './?s=etkinlikler');

  // ---------- Mekanlar, eğitmenler, kategoriler ----------
  case 'mekan':
    $id = (string) ($_POST['id'] ?? '');
    $name = post('name');
    if ($name === '') throw new RuntimeException('Mekan adı boş olamaz.');
    catalog_update(function (array &$d) use ($id, $name) {
      $i = $id === '' ? false : array_search($id, array_column($d['venues'], 'id'), true);
      $v = $i === false ? ['id' => 'v' . substr(bin2hex(random_bytes(3)), 0, 6), 'photo' => ''] : $d['venues'][$i];
      $v['name'] = $name;
      $v['type'] = in_array(post('type'), ['mekan', 'acik', 'online'], true) ? post('type') : 'mekan';
      foreach (['address' => 200, 'district' => 60, 'city' => 60, 'phone' => 30, 'note' => 600] as $k => $max) $v[$k] = mb_substr(post($k), 0, $max);
      $v['map_url'] = filter_var(post('map_url'), FILTER_VALIDATE_URL) ? post('map_url') : '';
      $v['capacity'] = post_int('capacity');
      $v['photo'] = image_pick('photo', (string) ($v['photo'] ?? ''));
      $want = slugify(post('slug') ?: $name, 'mekan'); $new = $want; $n = 2;
      $taken = array_column(array_filter($d['venues'], fn($x) => $x['id'] !== $v['id']), 'slug');
      while (in_array($new, $taken, true)) $new = $want . '-' . $n++;
      $v['slug'] = $new;
      if ($i === false) $d['venues'][] = $v; else $d['venues'][$i] = $v;
    });
    flash('"' . $name . '" kaydedildi.');
    redirect('./?s=mekanlar');

  case 'mekan-sil':
    $id = (string) ($_POST['id'] ?? '');
    foreach (events_all() as $ev) foreach ($ev['sessions'] ?? [] as $s) if (($s['venue'] ?? '') === $id) throw new RuntimeException('Bu mekan "' . $ev['title'] . '" etkinliğinde kullanılıyor. Önce etkinlikten kaldırın.');
    catalog_update(function (array &$d) use ($id) { $d['venues'] = array_values(array_filter($d['venues'], fn($x) => $x['id'] !== $id)); });
    flash('Mekan silindi.');
    redirect('./?s=mekanlar');

  case 'egitmen':
    $id = (string) ($_POST['id'] ?? '');
    $name = post('name');
    if ($name === '') throw new RuntimeException('Eğitmen adı boş olamaz.');
    catalog_update(function (array &$d) use ($id, $name) {
      $i = $id === '' ? false : array_search($id, array_column($d['instructors'], 'id'), true);
      $x = $i === false ? ['id' => 'i' . substr(bin2hex(random_bytes(3)), 0, 6), 'photo' => ''] : $d['instructors'][$i];
      $x['name'] = $name;
      $x['title'] = mb_substr(post('title'), 0, 80);
      $x['bio'] = mb_substr(post('bio'), 0, 1200);
      $x['instagram'] = filter_var(post('instagram'), FILTER_VALIDATE_URL) ? post('instagram') : '';
      $x['photo'] = image_pick('photo', (string) ($x['photo'] ?? ''));
      if ($i === false) $d['instructors'][] = $x; else $d['instructors'][$i] = $x;
    });
    flash('"' . $name . '" kaydedildi.');
    redirect('./?s=mekanlar#egitmenler');

  case 'egitmen-sil':
    $id = (string) ($_POST['id'] ?? '');
    catalog_update(function (array &$d) use ($id) {
      $d['instructors'] = array_values(array_filter($d['instructors'], fn($x) => $x['id'] !== $id));
      foreach ($d['events'] as &$ev) $ev['instructors'] = array_values(array_diff($ev['instructors'] ?? [], [$id]));
    });
    flash('Eğitmen silindi ve etkinliklerden kaldırıldı.');
    redirect('./?s=mekanlar#egitmenler');

  case 'kategoriler':
    $cats = [];
    foreach (array_values((array) ($_POST['cats'] ?? [])) as $row) {
      $name = mb_substr(trim((string) ($row['name'] ?? '')), 0, 40);
      if ($name === '') continue;
      $cid = slugify((string) (($row['id'] ?? '') ?: $name), 'kategori');
      if (in_array($cid, array_column($cats, 'id'), true)) $cid .= '-' . (count($cats) + 1);
      $cats[] = ['id' => $cid, 'name' => $name];
    }
    catalog_update(function (array &$d) use ($cats) { $d['categories'] = $cats; });
    flash('Kategoriler kaydedildi.');
    redirect('./?s=mekanlar#kategoriler');

  // ---------- Katılımlar ----------
  case 'fatura':
    $id = (string) ($_POST['id'] ?? '');
    $no = mb_substr(trim(post('inv_no')), 0, 40);
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('inv_date')) ? post('inv_date') : date('Y-m-d');
    $code = regs_update(function (array &$d) use ($id, $no, $date) {
      foreach ($d['regs'] as &$r) if ($r['id'] === $id) { $r['inv_no'] = $no; $r['inv_date'] = $no === '' ? '' : $date; $r['updated'] = date('Y-m-d H:i'); return $r['code']; }
      return null;
    });
    if (!$code) throw new RuntimeException('Kayıt bulunamadı.');
    flash($code . ($no === '' ? ': fatura numarası silindi, kayıt yeniden "Kesilecek" listesinde.' : ': fatura ' . $no . ' kaydedildi.'));
    $back = post('back');
    redirect(str_starts_with($back, './?') ? $back : './?s=faturalar');

  case 'kayit':
    $id = (string) ($_POST['id'] ?? '');
    $notify = !empty($_POST['notify']);
    $res = regs_update(function (array &$d) use ($id) {
      foreach ($d['regs'] as &$r) {
        if ($r['id'] !== $id) continue;
        $before = $r;
        $ev = event_by_id($r['event']);
        $st = post('status');
        if (array_key_exists($st, REG_STATUS)) $r['status'] = $st;
        $r['paid'] = !empty($_POST['paid']);
        $r['checked_in'] = !empty($_POST['checked_in']);
        $r['admin_note'] = mb_substr(post('admin_note'), 0, 1000);
        if (isset($_POST['inv_no'])) {
          $r['inv_no'] = mb_substr(trim(post('inv_no')), 0, 40);
          $r['inv_date'] = $r['inv_no'] === '' ? '' : (preg_match('/^\d{4}-\d{2}-\d{2}$/', post('inv_date')) ? post('inv_date') : date('Y-m-d'));
          $inv = [];
          foreach (['title' => 160, 'tax_office' => 60, 'tax_no' => 11, 'address' => 300] as $k => $max) $inv[$k] = mb_substr(trim(post('inv_' . $k)), 0, $max);
          $r['invoice'] = $inv['title'] !== '' ? ['type' => 'kurumsal'] + $inv : (implode('', $inv) !== '' ? ['type' => 'bireysel', 'tax_no' => $inv['tax_no'], 'address' => $inv['address']] : []);
        }
        foreach (['name' => 80, 'phone' => 30, 'others' => 400] as $k => $max) if (isset($_POST[$k])) $r[$k] = mb_substr(post($k), 0, $max);
        if (isset($_POST['email']) && ($r['user'] ?? '') === '' && filter_var(post('email'), FILTER_VALIDATE_EMAIL)) $r['email'] = post('email');
        $sid = post('session');
        if ($ev && $sid !== '' && $sid !== $r['session'] && ($sid === '*' || session_by_id($ev, $sid))) $r['session'] = $sid;
        $qty = post_int('qty', 1, 50);
        if ($ev && $qty !== (int) $r['qty']) {
          $t = ticket_by_id($ev, $r['ticket']);
          $r['qty'] = $qty;
          $r['seats'] = $qty * max(1, (int) ($t['seats'] ?? ($r['seats'] / max(1, $before['qty']))));
          $r['total'] = (float) $r['unit_price'] * $qty;
        }
        $r['updated'] = date('Y-m-d H:i');
        return [$before, $r, $ev];
      }
      return null;
    });
    if (!$res) throw new RuntimeException('Kayıt bulunamadı.');
    [$before, $r, $ev] = $res;
    if ($notify && $ev && $r['email'] !== '') {
      require_once ROOT . '/inc/mail.php';
      if ($before['status'] === 'yedek' && in_array($r['status'], SEAT_STATUSES, true)) mail_reg_update($r, $ev, 'yedekten');
      elseif ($before['status'] !== $r['status'] && in_array($r['status'], ['onayli', 'iptal'], true)) mail_reg_update($r, $ev, $r['status']);
      elseif (!$before['paid'] && $r['paid']) mail_reg_update($r, $ev, 'odeme');
      elseif ($before['session'] !== $r['session']) mail_reg_update($r, $ev, 'tarih');
    }
    flash($r['code'] . ' kaydı güncellendi.' . ($notify && $r['email'] !== '' && is_local() ? ' (Yerel ortamda e-posta gönderilmez.)' : ''));
    redirect($back ?: './?s=kayit&id=' . urlencode($id));

  // Listeden tek dokunuşla: onayla, ödendi, geldi, yedekten al, iptal
  case 'kayit-hizli':
    $id = (string) ($_POST['id'] ?? ''); $do = post('do');
    $res = regs_update(function (array &$d) use ($id, $do) {
      foreach ($d['regs'] as &$r) {
        if ($r['id'] !== $id) continue;
        $before = $r;
        match ($do) {
          'onayla' => $r['status'] = 'onayli',
          'odendi' => [$r['paid'] = true, $r['status'] = $r['status'] === 'beklemede' ? 'onayli' : $r['status']],
          'odenmedi' => $r['paid'] = false,
          'geldi' => $r['checked_in'] = true,
          'gelmedi' => $r['checked_in'] = false,
          'yedekten' => $r['status'] = 'onayli',
          'iptal' => $r['status'] = 'iptal',
          default => null,
        };
        $r['updated'] = date('Y-m-d H:i');
        return [$before, $r];
      }
      return null;
    });
    if (!$res) throw new RuntimeException('Kayıt bulunamadı.');
    [$before, $r] = $res;
    $ev = event_by_id($r['event']);
    $over = '';
    if ($ev && $before['status'] === 'yedek' && in_array($r['status'], SEAT_STATUSES, true)) {
      $cap = capacity_of($ev, $r['session']);
      if ($cap > 0 && seats_taken($ev, $r['session']) > $cap) $over = ' Not: kontenjan aşıldı; gerekirse tarihin kontenjanını artırın.';
    }
    if (!empty($_POST['notify']) && $ev && $r['email'] !== '') {
      require_once ROOT . '/inc/mail.php';
      $what = ['onayla' => 'onayli', 'odendi' => 'odeme', 'yedekten' => 'yedekten', 'iptal' => 'iptal'][$do] ?? '';
      if ($what !== '') mail_reg_update($r, $ev, $what);
    }
    flash($r['code'] . ': ' . ['onayla' => 'onaylandı', 'odendi' => 'ödeme alındı', 'odenmedi' => 'ödenmedi olarak işaretlendi', 'geldi' => 'geldi olarak işaretlendi', 'gelmedi' => 'yoklama geri alındı', 'yedekten' => 'yedek listeden kayda alındı', 'iptal' => 'iptal edildi'][$do] . '.' . $over);
    redirect($back ?: './?s=katilimlar');

  case 'kayit-sil':
    $id = (string) ($_POST['id'] ?? '');
    backup_file(REGS_FILE);
    regs_update(function (array &$d) use ($id) { $d['regs'] = array_values(array_filter($d['regs'], fn($r) => $r['id'] !== $id)); });
    flash('Kayıt silindi.');
    redirect($back ?: './?s=katilimlar');

  case 'kayit-ekle':
    $ev = event_by_id((string) ($_POST['event'] ?? ''));
    if (!$ev) throw new RuntimeException('Etkinlik seçin.');
    $name = post('name');
    if (mb_strlen($name) < 2) throw new RuntimeException('Katılımcının adını yazın.');
    $email = post('email');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('E-posta adresi geçerli görünmüyor.');
    $sid = is_package($ev) ? '*' : post('session');
    if (!is_package($ev) && !session_by_id($ev, $sid)) throw new RuntimeException('Bir tarih seçin.');
    $t = ticket_by_id($ev, post('ticket')) ?? (event_tickets($ev)[0] ?? null);
    $qty = post_int('qty', 1, 50);
    $user = $email !== '' ? user_by('email', $email) : null;
    $st = array_key_exists(post('status'), REG_STATUS) ? post('status') : 'onayli';
    $now = date('Y-m-d H:i');
    $r = [
      'id' => new_id(), 'code' => reg_code(), 'event' => $ev['id'], 'session' => $sid,
      'ticket' => $t['id'] ?? 'standart', 'ticket_name' => $t['name'] ?? 'Standart', 'qty' => $qty, 'seats' => $qty * max(1, (int) ($t['seats'] ?? 1)),
      'unit_price' => (float) ($t['price'] ?? 0), 'total' => (float) ($t['price'] ?? 0) * $qty,
      'user' => $user['id'] ?? '', 'name' => $name, 'email' => $email, 'phone' => mb_substr(post('phone'), 0, 30),
      'others' => mb_substr(post('others'), 0, 400), 'note' => '', 'method' => in_array(post('method'), array_keys(PAY_METHODS), true) ? post('method') : '',
      'status' => $st, 'paid' => !empty($_POST['paid']) || (float) ($t['price'] ?? 0) <= 0, 'checked_in' => false, 'admin_note' => mb_substr(post('admin_note'), 0, 1000),
      'source' => 'panel', 'created' => $now, 'updated' => $now,
    ];
    $left = seats_left($ev, $sid === '*' ? (event_sessions($ev, false)[0]['id'] ?? '') : $sid);
    regs_update(function (array &$d) use ($r) { $d['regs'][] = $r; });
    if (!empty($_POST['notify']) && $email !== '') { require_once ROOT . '/inc/mail.php'; mail_registration($r, $ev, false); }
    flash($name . ' eklendi (' . $r['code'] . ').' . ($left !== null && $left < $r['seats'] && in_array($st, SEAT_STATUSES, true) ? ' Not: bu kayıtla kontenjan aşıldı.' : ''), 'ok');
    redirect('./?s=katilimlar&etkinlik=' . urlencode($ev['id']));

  case 'duyuru':
    $ev = event_by_id((string) ($_POST['event'] ?? ''));
    if (!$ev) throw new RuntimeException('Etkinlik bulunamadı.');
    $subject = post('subject'); $text = post('text');
    if ($subject === '' || mb_strlen($text) < 5) throw new RuntimeException('Konu ve mesaj yazın.');
    $who = (array) ($_POST['who'] ?? ['onayli', 'beklemede']);
    $sid = post('session');
    $sent = 0; $seen = [];
    foreach (regs_of_event($ev['id']) as $r) {
      if (!in_array($r['status'], $who, true) || $r['email'] === '' || isset($seen[$r['email']])) continue;
      if ($sid !== '' && $r['session'] !== $sid) continue;
      $seen[$r['email']] = true;
      send_mail($r['email'], $subject, 'Merhaba ' . $r['name'] . ",\n\n" . $text . "\n\nKaydınız: " . site_url(ticket_url($r, true)));
      $sent++;
    }
    flash($sent . ' kişiye e-posta ' . (is_local() || !setting('mail_enabled', true) ? 'hazırlandı (e-posta gönderimi kapalı ya da yerel ortam).' : 'gönderildi.'));
    redirect('./?s=katilimlar&etkinlik=' . urlencode($ev['id']));

  // ---------- Üyeler ----------
  case 'uye-durum':
    $id = (string) ($_POST['id'] ?? '');
    $st = post('status') === 'engelli' ? 'engelli' : 'aktif';
    json_update(USERS_FILE, function (array &$d) use ($id, $st) { foreach ($d['users'] as &$u) if ($u['id'] === $id) { $u['status'] = $st; if ($st === 'engelli') $u['tokens'] = []; } }, ['users' => []]);
    flash($st === 'engelli' ? 'Üyelik askıya alındı. Üye giriş yapamaz, profili ve yorumları görünmez.' : 'Üyelik yeniden açıldı.');
    redirect('./?s=uye&id=' . urlencode($id));

  case 'uye-sil':
    $id = (string) ($_POST['id'] ?? '');
    $u = user_by_id($id);
    if (!$u) throw new RuntimeException('Üye bulunamadı.');
    backup_file(USERS_FILE);
    delete_upload($u['avatar'] ?? '');
    json_update(USERS_FILE, function (array &$d) use ($id) { $d['users'] = array_values(array_filter($d['users'], fn($x) => $x['id'] !== $id)); }, ['users' => []]);
    if (data_exists(INTERESTS_FILE)) json_update(INTERESTS_FILE, function (array &$d) use ($id) { foreach ($d as $k => &$m) { unset($m[$id]); if (!$m) unset($d[$k]); } });
    if (!empty($_POST['comments']) && data_exists(COMMENTS_FILE)) json_update(COMMENTS_FILE, function (array &$d) use ($id) {
      foreach ($d as $eid => &$list) {
        $gone = array_column(array_filter($list, fn($x) => ($x['user'] ?? '') === $id), 'id');
        $list = array_values(array_filter($list, fn($x) => ($x['user'] ?? '') !== $id && !in_array($x['parent'] ?? '', $gone, true)));
      }
    });
    // Kayıtlar etkinlik geçmişi için kalır, üyelik bağlantısı kaldırılır
    if (data_exists(REGS_FILE)) regs_update(function (array &$d) use ($id) { foreach ($d['regs'] as &$r) if (($r['user'] ?? '') === $id) $r['user'] = ''; });
    flash($u['name'] . ' adlı üye silindi.');
    redirect('./?s=uyeler');

  // ---------- Yorumlar ----------
  case 'yorum-etkinlik':
    $eid = (string) ($_POST['event'] ?? ''); $id = (string) ($_POST['id'] ?? ''); $do = post('do');
    json_update(COMMENTS_FILE, function (array &$d) use ($eid, $id, $do) {
      foreach ($d[$eid] ?? [] as $k => $cm) {
        if ($cm['id'] !== $id) continue;
        if ($do === 'sil') {
          $d[$eid] = array_values(array_filter($d[$eid], fn($x) => $x['id'] !== $id && ($x['parent'] ?? '') !== $id));
          return;
        }
        if ($do === 'onayla') $d[$eid][$k]['status'] = 'yayinda';
        if ($do === 'gizle') $d[$eid][$k]['status'] = 'gizli';
        if ($do === 'yanit' && post('reply') !== '') {
          $d[$eid][$k]['status'] = 'yayinda';
          $d[$eid][] = ['id' => new_id(), 'user' => '', 'text' => mb_substr(post('reply'), 0, 1500), 'date' => date('Y-m-d H:i'), 'status' => 'yayinda', 'parent' => ($cm['parent'] ?? '') ?: $cm['id'], 'admin' => true];
        }
        return;
      }
    });
    flash(['sil' => 'Yorum silindi.', 'onayla' => 'Yorum yayında.', 'gizle' => 'Yorum gizlendi.', 'yanit' => 'Yanıtınız "' . ($c['brand']['name'] ?? 'Ekip') . '" adıyla yayınlandı.'][$do] ?? 'Kaydedildi.');
    redirect($back ?: './?s=yorumlar');

  // ---------- Blog ----------
  case 'yazi':
    $orig = (string) ($_POST['orig'] ?? '');
    $title = post('title');
    if ($title === '') throw new RuntimeException('Yazı başlığı boş olamaz.');
    $saved = json_update(BLOG_FILE, function (array &$d) use ($orig, $title) {
      $d['posts'] ??= [];
      $i = $orig === '' ? false : array_search($orig, array_column($d['posts'], 'slug'), true);
      $p = $i === false ? ['slug' => '', 'cover' => '', 'created' => date('Y-m-d H:i')] : $d['posts'][$i];
      $p['title'] = $title;
      $p['category'] = post('category');
      $p['excerpt'] = post('excerpt');
      $p['body'] = post('body');
      $p['date'] = valid_date(post('date')) ?: date('Y-m-d');
      $p['published'] = !empty($_POST['published']);
      $p['comments'] = !empty($_POST['comments']);
      $p['updated'] = date('Y-m-d');
      $p['cover'] = image_pick('cover', (string) ($p['cover'] ?? ''));
      $want = blog_slug(post('slug') ?: $title) ?: 'yazi';
      $taken = array_column(array_filter($d['posts'], fn($x) => $x['slug'] !== ($p['slug'] ?? '')), 'slug');
      $new = $want; $n = 2;
      while (in_array($new, $taken, true)) $new = $want . '-' . $n++;
      $p['slug'] = $new;
      if ($i === false) $d['posts'][] = $p; else $d['posts'][$i] = $p;
      return $p;
    }, ['posts' => []]);
    if ($orig !== '' && $orig !== $saved['slug']) {
      foreach ([BLOG_COMMENTS_FILE, BLOG_LIKES_FILE] as $file) if (is_file($file)) json_update($file, function (array &$d) use ($orig, $saved) { if (isset($d[$orig])) { $d[$saved['slug']] = $d[$orig]; unset($d[$orig]); } });
    }
    flash('"' . $saved['title'] . '" ' . ($saved['published'] ? 'kaydedildi ve yayında.' : 'taslak olarak kaydedildi.'));
    redirect('./?s=yazi&slug=' . urlencode($saved['slug']));

  case 'yazi-sil':
    $slug = (string) ($_POST['slug'] ?? '');
    json_update(BLOG_FILE, function (array &$d) use ($slug) { $d['posts'] = array_values(array_filter($d['posts'] ?? [], fn($p) => $p['slug'] !== $slug)); }, ['posts' => []]);
    if (data_exists(BLOG_COMMENTS_FILE)) json_update(BLOG_COMMENTS_FILE, function (array &$d) use ($slug) { unset($d[$slug]); });
    if (data_exists(BLOG_LIKES_FILE)) json_update(BLOG_LIKES_FILE, function (array &$d) use ($slug) { unset($d[$slug]); });
    flash('Yazı silindi.');
    redirect('./?s=blog');

  case 'yorum':
    $slug = (string) ($_POST['slug'] ?? ''); $id = (string) ($_POST['id'] ?? ''); $do = (string) ($_POST['do'] ?? '');
    json_update(BLOG_COMMENTS_FILE, function (array &$d) use ($slug, $id, $do) {
      foreach ($d[$slug] ?? [] as $k => $cm) {
        if ($cm['id'] !== $id) continue;
        if ($do === 'sil') { array_splice($d[$slug], $k, 1); return; }
        if ($do === 'onayla') $d[$slug][$k]['status'] = 'approved';
        if ($do === 'gizle') $d[$slug][$k]['status'] = 'pending';
        if ($do === 'yanit') { $d[$slug][$k]['reply'] = post('reply'); if (post('reply') !== '') $d[$slug][$k]['status'] = 'approved'; }
        return;
      }
    });
    flash(['sil' => 'Yorum silindi.', 'onayla' => 'Yorum onaylandı ve yayında.', 'gizle' => 'Yorum yayından kaldırıldı.', 'yanit' => 'Yanıtınız kaydedildi.'][$do] ?? 'Kaydedildi.');
    redirect('./?s=yorumlar&t=blog' . (isset($_GET['f']) ? '&f=' . urlencode((string) $_GET['f']) : ''));

  // ---------- Mesajlar ----------
  case 'mesaj':
    $id = (string) ($_POST['id'] ?? ''); $do = post('do');
    json_update(MESSAGES_FILE, function (array &$d) use ($id, $do) {
      foreach ($d['messages'] ?? [] as $k => $m) {
        if ($m['id'] !== $id) continue;
        if ($do === 'sil') array_splice($d['messages'], $k, 1);
        else $d['messages'][$k]['read'] = $do === 'okundu';
        return;
      }
    }, ['messages' => []]);
    flash(['sil' => 'Mesaj silindi.', 'okundu' => 'Okundu olarak işaretlendi.', 'okunmadi' => 'Okunmadı olarak işaretlendi.'][$do] ?? 'Kaydedildi.');
    redirect('./?s=mesajlar');

  // ---------- Sayfalar ----------
  case 'sayfa-ana':
    $c['home'] = array_merge($c['home'], [
      'eyebrow' => post('eyebrow'), 'title' => post('title'), 'subtitle' => post('subtitle'),
      'events_title' => post('events_title'), 'calendar_title' => post('calendar_title'), 'calendar_text' => post('calendar_text'),
      'steps_title' => post('steps_title'), 'quote' => post('quote'), 'community_title' => post('community_title'), 'community_text' => post('community_text'),
    ]);
    $c['home']['steps'] = [];
    foreach (array_values((array) ($_POST['steps'] ?? [])) as $s) if (trim((string) ($s['title'] ?? '')) !== '') $c['home']['steps'][] = ['title' => trim((string) $s['title']), 'text' => trim((string) ($s['text'] ?? ''))];
    $c['stats'] = [];
    foreach (array_values((array) ($_POST['stats'] ?? [])) as $s) if (trim((string) ($s['value'] ?? '')) !== '') $c['stats'][] = ['value' => trim((string) $s['value']), 'label' => trim((string) ($s['label'] ?? ''))];
    $c['home']['hero_image'] = image_pick('hero_image', (string) ($c['home']['hero_image'] ?? ''));
    save_content($c);
    flash('Ana sayfa kaydedildi.');
    redirect('./?s=sayfalar');

  case 'sayfa-hakkimizda':
    $a = &$c['about'];
    foreach (['title', 'lead', 'body', 'founder', 'founder_title'] as $k) $a[$k] = post($k);
    $a['gallery'] = gallery_pick('gallery', $a['gallery'] ?? []);
    $a['image'] = image_pick('image', (string) ($a['image'] ?? ''));
    unset($a);
    save_content($c);
    flash('Hakkımızda sayfası kaydedildi.');
    redirect('./?s=sayfalar&t=hakkimizda');

  case 'sayfa-kurumsal':
    $k = &$c['corporate'];
    foreach (['title', 'lead', 'body', 'services_title', 'process_title', 'contact_name', 'contact_phone', 'contact_email'] as $f) $k[$f] = post($f);
    $k['brands'] = lines(post('brands'));
    $k['services'] = [];
    foreach (array_values((array) ($_POST['services'] ?? [])) as $s) if (trim((string) ($s['title'] ?? '')) !== '') $k['services'][] = ['icon' => (string) ($s['icon'] ?? 'yaprak'), 'title' => trim((string) $s['title']), 'text' => trim((string) ($s['text'] ?? ''))];
    $k['process'] = [];
    foreach (array_values((array) ($_POST['process'] ?? [])) as $s) if (trim((string) ($s['title'] ?? '')) !== '') $k['process'][] = ['title' => trim((string) $s['title']), 'text' => trim((string) ($s['text'] ?? ''))];
    $k['image'] = image_pick('image', (string) ($k['image'] ?? ''));
    unset($k);
    save_content($c);
    flash('Kurumsal sayfası kaydedildi.');
    redirect('./?s=sayfalar&t=kurumsal');

  case 'marka':
    foreach (['name', 'logo_text', 'tagline', 'description', 'footer_text'] as $k) $c['brand'][$k] = post($k);
    if ($c['brand']['name'] === '') $c['brand']['name'] = 'İSE ATÖLYE';
    if ($c['brand']['logo_text'] === '') $c['brand']['logo_text'] = $c['brand']['name'];
    $c['brand']['logo'] = image_pick('logo', (string) ($c['brand']['logo'] ?? ''));
    $c['brand']['og_image'] = image_pick('og_image', (string) ($c['brand']['og_image'] ?? ''));
    $c['announcement'] = ['on' => !empty($_POST['ann_on']), 'text' => post('ann_text'), 'url' => post('ann_url')];
    save_content($c);
    flash('Marka ve duyuru kaydedildi.');
    redirect('./?s=sayfalar&t=marka');

  // ---------- Ayarlar ----------
  case 'ayarlar':
    foreach (['heading', 'address', 'hours', 'phone', 'email'] as $k) $c['contact'][$k] = post('contact_' . $k);
    $c['contact']['whatsapp'] = preg_replace('/\D/', '', post('contact_whatsapp'));
    $c['socials'] = [];
    foreach (array_values((array) ($_POST['socials'] ?? [])) as $s) {
      $url = trim((string) ($s['url'] ?? '')); $label = trim((string) ($s['label'] ?? ''));
      if ($label !== '' && filter_var($url, FILTER_VALIDATE_URL)) $c['socials'][] = ['label' => $label, 'url' => $url];
    }
    $st = &$c['settings'];
    foreach (['site_url', 'mail_from', 'notify_email', 'bank_name', 'bank_holder', 'bank_iban', 'payment_note', 'cancel_policy', 'terms', 'reg_success_note'] as $k) $st[$k] = post($k);
    $st['site_url'] = rtrim($st['site_url'], '/');
    $st['bank_iban'] = strtoupper(preg_replace('/\s+/', ' ', $st['bank_iban']));
    foreach (['require_login', 'comment_moderation', 'mail_enabled'] as $k) $st[$k] = !empty($_POST[$k]);
    $st['hold_hours'] = max(0, min(720, (int) post('hold_hours', '48')));
    $st['vat_rate'] = max(0, min(30, (int) post('vat_rate', '20')));
    foreach (['mail_from', 'notify_email'] as $k) if ($st[$k] !== '' && !filter_var($st[$k], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('E-posta adresi geçerli görünmüyor: ' . $st[$k]);
    unset($st);
    save_content($c);
    flash('Ayarlar kaydedildi.');
    redirect('./?s=ayarlar');

  case 'smtp':
    require_once ROOT . '/inc/smtp.php';
    if (post('kaldir') === '1') {
      if (is_file(SMTP_FILE)) { backup_file(SMTP_FILE); @unlink(SMTP_FILE); }
      flash('SMTP ayarı kaldırıldı.');
      redirect('./?s=ayarlar#smtp');
    }
    $old = smtp_config() ?? [];
    $port = post('smtp_port') === '587' ? 587 : 465;
    $cfg = [
      'host' => preg_replace('/[^a-z0-9.-]/i', '', post('smtp_host')),
      'port' => $port,
      'secure' => $port === 465 ? 'ssl' : 'tls',
      'user' => trim(post('smtp_user')),
      'pass' => ($_POST['smtp_pass'] ?? '') !== '' ? (string) $_POST['smtp_pass'] : (string) ($old['pass'] ?? ''),
    ];
    if ($cfg['host'] === '' || !filter_var($cfg['user'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Sunucu ve e-posta adresini doğru yazın.');
    if ($cfg['pass'] === '') throw new RuntimeException('E-posta hesabının şifresini yazın.');
    $to = admin_email() ?: $cfg['user'];
    $brand = (string) (content()['brand']['name'] ?? 'İSE ATÖLYE');
    $err = smtp_send($cfg, $cfg['user'], $brand, $to, 'Deneme e-postası', "Bu bir deneme e-postasıdır. Bu mesajı aldıysanız sitenin e-posta gönderimi çalışıyor.\n\n—\n" . $brand);
    if ($err !== '') { flash('Ayar kaydedilmedi, e-posta gönderilemedi: ' . $err, 'err'); redirect('./?s=ayarlar#smtp'); }
    if (@file_put_contents(SMTP_FILE, "<?php\n// E-posta hesabı (panelden oluşturuldu). Silerseniz site sunucunun mail() işlevini kullanır.\nreturn " . var_export($cfg, true) . ";\n", LOCK_EX) === false) throw new RuntimeException('data/smtp.php yazılamadı. data klasörünün yazma iznini kontrol edin.');
    @chmod(SMTP_FILE, 0600);
    flash('E-posta hesabı kaydedildi ve ' . $to . ' adresine deneme e-postası gönderildi. Gelen kutunuzu ve spam klasörünü kontrol edin.');
    redirect('./?s=ayarlar#smtp');

  case 'deneme-epostasi':
    $to = admin_email();
    if ($to === '') throw new RuntimeException('Önce bildirim e-postasını ya da iletişim e-postasını yazın.');
    $sent = send_mail($to, 'Deneme e-postası', "Bu bir deneme e-postasıdır. Bu mesajı aldıysanız sitenin e-posta gönderimi çalışıyor.");
    if (is_local() || !setting('mail_enabled', true)) flash($to . ' adresine deneme e-postası gönderilmedi (yerel ortam ya da e-posta gönderimi kapalı).', 'err');
    elseif (!$sent) flash('E-posta gönderilemedi: ' . (mail_error() ?: 'bilinmeyen hata') . ' · Aşağıdaki "E-posta hesabı (SMTP)" bilgilerini kontrol edin.', 'err');
    else flash($to . ' adresine deneme e-postası gönderildi. Gelen kutunuzu ve istenmeyen (spam) klasörünü kontrol edin.');
    redirect('./?s=ayarlar');

  // ---------- İstatistik ve şifre ----------
  case 'sayma':
    $on = post('on') === '1';
    skip_cookie($on);
    flash($on ? 'Bu tarayıcıdan yaptığınız ziyaretler artık sayılmayacak.' : 'Bu tarayıcıdan yaptığınız ziyaretler de sayılacak.');
    redirect('./?s=istatistik');

  case 'geo-guncelle':
    $ym = geo_download();
    flash('Konum verisi güncellendi (' . $ym . ').');
    redirect('./?s=istatistik');

  // ---------- Galeri (görsel kütüphanesi) ----------
  case 'medya-yukle':
    // Tarayıcıdan tek tek gönderilen görseller; yanıt JSON
    header('Content-Type: application/json; charset=utf-8');
    try {
      $f = files_of('file')[0] ?? [];
      if (!$f) throw new RuntimeException('Görsel seçilmedi.');
      $album = preg_replace('/[^a-z0-9-]/', '', (string) ($_POST['album'] ?? ''));
      if ($album !== '' && !in_array($album, array_column(media_meta()['albums'], 'slug'), true)) $album = '';
      $base = pathinfo((string) ($f['name'] ?? ''), PATHINFO_FILENAME);
      $path = store_image($f, $base !== '' ? $base : 'gorsel', 'galeri', ['album' => $album]);
      if (!$path) throw new RuntimeException('Görsel seçilmedi.');
      echo json_encode(['ok' => true, 'path' => $path, 'thumb' => thumb_url($path)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } catch (RuntimeException $ex) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;

  case 'medya':
    $path = media_valid((string) ($_POST['path'] ?? ''));
    if ($path === '') throw new RuntimeException('Görsel bulunamadı.');
    $album = preg_replace('/[^a-z0-9-]/', '', post('album'));
    media_save($path, ['title' => mb_substr(post('title'), 0, 200), 'album' => in_array($album, array_column(media_meta()['albums'], 'slug'), true) ? $album : null]);
    flash('Görsel bilgileri kaydedildi.');
    redirect($back ?: './?s=galeri');

  case 'medya-sil':
    $path = media_valid((string) ($_POST['path'] ?? ''));
    if ($path === '') throw new RuntimeException('Görsel bulunamadı.');
    $use = media_usage()[$path] ?? [];
    if ($use) throw new RuntimeException('Bu görsel kullanılıyor (' . implode(', ', array_column($use, 'label')) . '). Önce oradan kaldırın.');
    media_delete_files($path);
    json_update(MEDIA_FILE, function (array &$d) use ($path) { unset($d['items'][$path]); }, ['items' => [], 'albums' => []]);
    flash('Görsel silindi.');
    redirect($back ?: './?s=galeri');

  case 'medya-toplu':
    $paths = array_values(array_filter(array_map(fn($p) => media_valid((string) $p), (array) ($_POST['paths'] ?? []))));
    if (!$paths) throw new RuntimeException('Önce görselleri işaretleyin.');
    $op = (string) ($_POST['op'] ?? '');
    if (str_starts_with($op, 'album:')) {
      $album = substr($op, 6);
      if ($album !== '' && !in_array($album, array_column(media_meta()['albums'], 'slug'), true)) throw new RuntimeException('Albüm bulunamadı.');
      foreach ($paths as $p) media_save($p, ['album' => $album === '' ? null : $album]);
      flash(count($paths) . ' görsel ' . ($album === '' ? 'albümden çıkarıldı.' : 'albüme taşındı.'));
    } elseif (str_starts_with($op, 'etkinlik:')) {
      $id = substr($op, 9);
      $title = '';
      catalog_update(function (array &$d) use ($id, $paths, &$title) {
        foreach ($d['events'] as &$ev) if ($ev['id'] === $id) {
          $ev['gallery'] = array_values(array_unique([...($ev['gallery'] ?? []), ...$paths]));
          $title = $ev['title'];
        }
      });
      if ($title === '') throw new RuntimeException('Etkinlik bulunamadı.');
      flash(count($paths) . ' görsel "' . $title . '" galerisine eklendi.');
    } elseif ($op === 'sil') {
      $use = media_usage();
      $done = 0; $kept = 0;
      foreach ($paths as $p) {
        if (!empty($use[$p])) { $kept++; continue; }
        media_delete_files($p);
        $done++;
      }
      json_update(MEDIA_FILE, function (array &$d) use ($paths, $use) { foreach ($paths as $p) if (empty($use[$p])) unset($d['items'][$p]); }, ['items' => [], 'albums' => []]);
      flash($done . ' görsel silindi.' . ($kept ? ' ' . $kept . ' görsel sitede kullanıldığı için silinmedi.' : ''), $kept && !$done ? 'err' : 'ok');
    } else {
      throw new RuntimeException('Bir işlem seçin.');
    }
    redirect($back ?: './?s=galeri');

  case 'album':
    $orig = (string) ($_POST['orig'] ?? '');
    $name = post('name');
    if ($name === '') throw new RuntimeException('Albüm adı boş olamaz.');
    $eventSlugs = array_column(events_all(), 'slug');
    $slug = json_update(MEDIA_FILE, function (array &$d) use ($orig, $name, $eventSlugs) {
      $d += ['items' => [], 'albums' => []];
      $i = $orig === '' ? false : array_search($orig, array_column($d['albums'], 'slug'), true);
      $a = $i === false ? ['slug' => '', 'created' => date('Y-m-d H:i')] : $d['albums'][$i];
      $a['name'] = mb_substr($name, 0, 120);
      $a['date'] = valid_date(post('date'));
      $a['text'] = mb_substr(post('text'), 0, 600);
      $a['public'] = !empty($_POST['public']);
      if ($a['slug'] === '') {
        $taken = [...array_column($d['albums'], 'slug'), ...$eventSlugs];
        $want = slugify($name, 'album'); $new = $want; $n = 2;
        while (in_array($new, $taken, true)) $new = $want . '-' . $n++;
        $a['slug'] = $new;
      }
      if ($i === false) $d['albums'][] = $a; else $d['albums'][$i] = $a;
      return $a['slug'];
    }, ['items' => [], 'albums' => []]);
    flash('"' . $name . '" albümü kaydedildi.');
    redirect('./?s=galeri&album=' . rawurlencode($slug));

  case 'album-sil':
    $slug = (string) ($_POST['slug'] ?? '');
    json_update(MEDIA_FILE, function (array &$d) use ($slug) {
      $d['albums'] = array_values(array_filter($d['albums'] ?? [], fn($a) => $a['slug'] !== $slug));
      foreach ($d['items'] ?? [] as $p => $m) if (($m['album'] ?? '') === $slug) unset($d['items'][$p]['album']);
    }, ['items' => [], 'albums' => []]);
    flash('Albüm silindi. İçindeki görseller galeride duruyor.');
    redirect('./?s=galeri');

  // ---------- Veritabanı ----------
  case 'veritabani':
    if (db_config()) throw new RuntimeException('Site zaten veritabanına bağlı.');
    if (!extension_loaded('pdo_mysql')) throw new RuntimeException('Sunucuda PHP\'nin MySQL eklentisi (pdo_mysql) kapalı. cPanel > PHP eklentilerinden açın.');
    $cfg = ['host' => post('db_host') ?: 'localhost', 'port' => post_int('db_port', 0, 65535), 'name' => post('db_name'), 'user' => post('db_user'), 'pass' => (string) ($_POST['db_pass'] ?? '')];
    if ($cfg['name'] === '' || $cfg['user'] === '') throw new RuntimeException('Veritabanı adını ve kullanıcı adını yazın.');
    try { $pdo = db_connect($cfg); db_install($pdo); }
    catch (PDOException $ex) {
      $m = $ex->getMessage();
      throw new RuntimeException(str_contains($m, 'Access denied') ? 'Bağlanılamadı: kullanıcı adı ya da şifre hatalı, ya da kullanıcı veritabanına eklenmemiş (cPanel > MySQL Veritabanları > "Kullanıcıyı veritabanına ekle", TÜM YETKİLER).' : (str_contains($m, 'Unknown database') ? 'Bağlanılamadı: bu adda bir veritabanı yok. cPanel\'deki tam adı (ör. kullaniciadi_iseatolye) yazın.' : 'Bağlanılamadı: ' . $m));
    }
    // Mevcut kayıtları aktar (veritabanında zaten olan belgeye dokunulmaz)
    $moved = [];
    foreach (DB_DOCS as $n) {
      $f = DATA . '/' . $n . '.json';
      if (!is_file($f)) continue;
      $d = json_decode((string) file_get_contents($f), true);
      if (!is_array($d)) throw new RuntimeException($n . '.json dosyası okunamadı; aktarım durduruldu, hiçbir şey değişmedi.');
      $q = $pdo->prepare('INSERT IGNORE INTO ise_belgeler (ad, veri, guncel) VALUES (?, ?, NOW())');
      $q->execute([$n, json_encode_data($d)]);
      $chk = $pdo->prepare('SELECT veri FROM ise_belgeler WHERE ad = ?'); $chk->execute([$n]);
      if (!is_array(json_decode((string) $chk->fetchColumn(), true))) throw new RuntimeException($n . ' aktarılamadı; hiçbir şey değişmedi.');
      $moved[$n] = $f;
    }
    if (@file_put_contents(DB_FILE, "<?php\n// Veritabanı bağlantısı (panelden oluşturuldu). Silerseniz site yeniden data/ dosyalarını kullanır.\nreturn " . var_export($cfg, true) . ";\n", LOCK_EX) === false) throw new RuntimeException('data/db.php yazılamadı. data klasörünün yazma iznini kontrol edin.');
    @chmod(DB_FILE, 0600);
    // Aktarılan dosyalar silinmez, yedek olarak yeniden adlandırılır
    $stamp = date('Ymd-His');
    if (!is_dir(BACKUP_DIR)) @mkdir(BACKUP_DIR, 0755, true);
    foreach ($moved as $n => $f) @rename($f, DATA . '/yedek/' . $n . '-veritabanina-tasindi-' . $stamp . '.json') || @rename($f, $f . '.tasindi');
    flash('Site veritabanına bağlandı.' . ($moved ? ' Aktarılan kayıtlar: ' . implode(', ', array_keys($moved)) . '.' : ''));
    redirect('./?s=kontrol');

  case 'sifre':
    if (!password_verify((string) ($_POST['old'] ?? ''), password_hash_stored())) throw new RuntimeException('Mevcut şifre hatalı.');
    $p1 = (string) ($_POST['p1'] ?? '');
    if (mb_strlen($p1) < 8) throw new RuntimeException('Yeni şifre en az 8 karakter olmalı.');
    if ($p1 !== (string) ($_POST['p2'] ?? '')) throw new RuntimeException('Yeni şifreler aynı değil.');
    save_password($p1);
    session_regenerate_id(true);
    flash('Şifreniz değiştirildi. Diğer cihazlardaki açık oturumlar kapatıldı.');
    redirect('./?s=sifre');
}

throw new RuntimeException('Bilinmeyen işlem.');
