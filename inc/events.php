<?php
// Etkinlik modeli: kategoriler, mekanlar, eğitmenler, etkinlikler (oturumlar + bilet türleri),
// katılımlar, "katılmayı düşünüyorum" listesi ve etkinlik yorumları.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

const EVENTS_FILE = DATA . '/events.json';
const REGS_FILE = DATA . '/registrations.json';
const INTERESTS_FILE = DATA . '/interests.json';
const COMMENTS_FILE = DATA . '/comments.json';
const MESSAGES_FILE = DATA . '/messages.json';

// Kontenjanı dolduran durumlar: onay/ödeme bekleyen kayıt da yerini tutar.
const SEAT_STATUSES = ['beklemede', 'onayli'];
const REG_STATUS = [
  'beklemede' => 'Onay bekliyor',
  'onayli' => 'Onaylandı',
  'yedek' => 'Yedek listede',
  'iptal' => 'İptal edildi',
];
const EVENT_STATUS = [
  'yayinda' => 'Yayında',
  'taslak' => 'Taslak',
  'ertelendi' => 'Ertelendi',
  'iptal' => 'İptal edildi',
];
const PAY_METHODS = [
  'havale' => 'Havale / EFT',
  'yerinde' => 'Etkinlikte ödeme',
  'link' => 'Online ödeme bağlantısı',
  'iyzico' => 'Kredi / banka kartı',
];
// Sitede seçilebilen yöntemler. "Etkinlikte ödeme" kaldırıldı; eski kayıtlarda adı görünsün diye PAY_METHODS'ta duruyor.
// Kartla ödeme (iyzico) etkinlik bazında seçilmez: anahtarlar girilince tüm ücretli etkinliklerde görünür.
const PAY_METHODS_ACTIVE = ['havale', 'link'];
const LEVELS = ['' => 'Belirtilmemiş', 'herkes' => 'Herkes için', 'baslangic' => 'Başlangıç', 'orta' => 'Orta seviye', 'ileri' => 'İleri seviye'];

function catalog(): array {
  static $c = null;
  if ($c === null) $c = json_read(EVENTS_FILE, []) + ['categories' => [], 'venues' => [], 'instructors' => [], 'events' => []];
  return $c;
}

function by_id(array $list, ?string $id): ?array {
  foreach ($list as $x) if (($x['id'] ?? '') === $id) return $x;
  return null;
}

function categories(): array { return catalog()['categories']; }
function category(?string $id): ?array { return by_id(categories(), $id); }
function venues(): array { return catalog()['venues']; }
function venue(?string $id): ?array { return by_id(venues(), $id); }
function instructors(): array { return catalog()['instructors']; }
function instructor(?string $id): ?array { return by_id(instructors(), $id); }
function events_all(): array { return catalog()['events']; }
function event_by_id(?string $id): ?array { return by_id(events_all(), $id); }

function event_by_slug(string $slug): ?array {
  foreach (events_all() as $ev) if (($ev['slug'] ?? '') === $slug) return $ev;
  return null;
}

function event_url(array $ev): string { return '/etkinlik/' . rawurlencode($ev['slug']) . '/'; }
function venue_url(array $v): string { return '/mekanlar/' . rawurlencode($v['slug']) . '/'; }

function event_visible(array $ev): bool {
  return ($ev['status'] ?? 'taslak') !== 'taslak';
}

function venue_label(?array $v, bool $withDistrict = true): string {
  if (!$v) return 'Mekan duyurulacak';
  $d = $withDistrict ? trim(implode(', ', array_filter([$v['district'] ?? '', $v['city'] ?? '']))) : '';
  return $v['name'] . ($d !== '' && !str_contains($v['name'], (string) ($v['district'] ?? '###')) ? ' · ' . $d : '');
}

function session_place(?array $s, bool $withDistrict = true): string {
  $v = venue($s['venue'] ?? '');
  return $v ? venue_label($v, $withDistrict) : (trim($s['place'] ?? '') ?: 'Mekan duyurulacak');
}

// ---------- Oturumlar ----------
function session_ts(array $s, bool $end = false): int {
  $time = $end ? (($s['end'] ?? '') ?: ($s['start'] ?? '') ?: '23:59') : (($s['start'] ?? '') ?: '00:00');
  return (int) strtotime(($s['date'] ?? '1970-01-01') . ' ' . $time);
}

function event_sessions(array $ev, bool $withCancelled = true): array {
  $list = array_values(array_filter($ev['sessions'] ?? [], fn($s) => $withCancelled || ($s['status'] ?? 'acik') !== 'iptal'));
  usort($list, fn($a, $b) => session_ts($a) <=> session_ts($b));
  return $list;
}

function session_by_id(array $ev, string $sid): ?array {
  foreach ($ev['sessions'] ?? [] as $s) if ($s['id'] === $sid) return $s;
  return null;
}

// Paket etkinlikte (kurs, seri) tek kayıt tüm oturumları kapsar.
function is_package(array $ev): bool { return !empty($ev['package']); }

// Yaklaşan ilk oturum; hepsi geçtiyse son oturum.
function next_session(array $ev): ?array {
  $list = event_sessions($ev, false) ?: event_sessions($ev);
  foreach ($list as $s) if (session_ts($s, true) >= time()) return $s;
  return $list ? end($list) : null;
}

function event_is_past(array $ev): bool {
  foreach (event_sessions($ev) as $s) if (session_ts($s, true) >= time()) return false;
  return true;
}

function event_sort_ts(array $ev): int {
  $s = next_session($ev);
  return $s ? session_ts($s) : 0;
}

function event_venues(array $ev): array {
  $ids = array_unique(array_filter(array_map(fn($s) => $s['venue'] ?? '', event_sessions($ev, false))));
  return array_values(array_filter(array_map('venue', $ids)));
}

// "29 Ekim 2026, Perşembe · 17:00 – 19:00"
function session_when(array $s, bool $withDay = true): string {
  $t = trim(($s['start'] ?? '') . (($s['end'] ?? '') !== '' ? ' – ' . $s['end'] : ''));
  return tr_date($s['date'] ?? '', $withDay) . ($t !== '' ? ' · ' . $t : '');
}

function event_when(array $ev): string {
  $list = event_sessions($ev, false);
  if (!$list) return 'Tarih duyurulacak';
  if (count($list) === 1) return session_when($list[0]);
  $first = $list[0]; $last = end($list);
  if (is_package($ev)) return tr_date_short($first['date']) . ' – ' . tr_date($last['date']) . ' · ' . count($list) . ' buluşma';
  $n = next_session($ev);
  return session_when($n) . ' · ' . count($list) . ' farklı tarih';
}

// Kartlarda kullanılan tarih kutusu için gün ve ay
function date_badge(array $ev): array {
  $s = next_session($ev);
  $t = $s ? strtotime($s['date']) : 0;
  return $t ? ['day' => date('j', $t), 'mon' => TR_MONTHS_SHORT[(int) date('n', $t)], 'dow' => TR_DAYS_SHORT[(int) date('w', $t)]] : ['day' => '–', 'mon' => '', 'dow' => ''];
}

// ---------- Biletler ve fiyat ----------
function event_tickets(array $ev): array {
  $list = array_values(array_filter($ev['tickets'] ?? [], fn($t) => trim($t['name'] ?? '') !== ''));
  return $list ?: [['id' => 'standart', 'name' => 'Katılım', 'price' => 0, 'seats' => 1, 'limit' => 0, 'until' => '', 'note' => '']];
}

function ticket_by_id(array $ev, string $tid): ?array {
  foreach (event_tickets($ev) as $t) if ($t['id'] === $tid) return $t;
  return null;
}

function ticket_on_sale(array $t): bool {
  return trim($t['until'] ?? '') === '' || strtotime($t['until'] . ' 23:59:59') >= time();
}

function event_is_free(array $ev): bool {
  foreach (event_tickets($ev) as $t) if ((float) ($t['price'] ?? 0) > 0) return false;
  return true;
}

function price_label(array $ev): string {
  if (event_is_free($ev)) return 'Ücretsiz';
  $prices = array_map(fn($t) => (float) $t['price'], array_filter(event_tickets($ev), fn($t) => ticket_on_sale($t) && (float) $t['price'] > 0));
  if (!$prices) $prices = array_map(fn($t) => (float) $t['price'], event_tickets($ev));
  $min = min($prices);
  return (count(array_unique($prices)) > 1 ? 'Kişi başı ' : '') . money($min) . (count(array_unique($prices)) > 1 ? "'den başlayan" : '');
}

function price_short(array $ev): string {
  if (event_is_free($ev)) return 'Ücretsiz';
  $prices = array_map(fn($t) => (float) $t['price'], array_filter(event_tickets($ev), fn($t) => ticket_on_sale($t) && (float) $t['price'] > 0)) ?: array_map(fn($t) => (float) $t['price'], event_tickets($ev));
  return money(min($prices));
}

function pay_methods(array $ev): array {
  if (event_is_free($ev)) return [];
  $m = array_values(array_intersect(PAY_METHODS_ACTIVE, (array) ($ev['pay_methods'] ?? ['havale'])));
  if (in_array('link', $m, true) && trim($ev['pay_link'] ?? '') === '') $m = array_values(array_diff($m, ['link']));
  $m = $m ?: ['havale'];
  require_once __DIR__ . '/iyzico.php';
  if (iyzico_on()) array_unshift($m, 'iyzico');
  return $m;
}

// ---------- Katılımlar ----------
function regs_all(): array {
  static $r = null;
  if ($r === null) $r = json_read(REGS_FILE, ['regs' => []])['regs'] ?? [];
  return $r;
}

function regs_of_event(string $eid, ?array $list = null): array {
  return array_values(array_filter($list ?? regs_all(), fn($r) => $r['event'] === $eid));
}

function reg_by_code(string $code): ?array {
  foreach (regs_all() as $r) if (strcasecmp($r['code'], $code) === 0) return $r;
  return null;
}

function capacity_of(array $ev, string $sid): int {
  if (is_package($ev)) return (int) ($ev['capacity'] ?? 0);
  $s = session_by_id($ev, $sid);
  return (int) ($s['capacity'] ?? 0);
}

// Havale ya da ödeme bağlantısıyla yapılıp ödenmeyen kayıtlar, Ayarlar'daki süre dolunca kontenjandan düşer.
// Böylece ödeme yapılmayan kayıtlarla yerler kalıcı olarak tutulamaz. Kayıt silinmez, panelde görünmeye devam eder.
function holds_seat(array $r): bool {
  if (!in_array($r['status'], SEAT_STATUSES, true)) return false;
  if ($r['status'] === 'onayli' || !empty($r['paid']) || !in_array($r['method'] ?? '', ['havale', 'link', 'iyzico'], true)) return true;
  $h = (int) setting('hold_hours', 48);
  return $h <= 0 || (strtotime((string) ($r['created'] ?? '')) ?: time()) > time() - $h * 3600;
}

function hold_expired(array $r): bool { return in_array($r['status'], SEAT_STATUSES, true) && !holds_seat($r); }

function seats_taken(array $ev, string $sid, ?array $regs = null): int {
  $n = 0;
  foreach ($regs ?? regs_all() as $r) {
    if ($r['event'] !== $ev['id'] || !holds_seat($r)) continue;
    if (is_package($ev) || $r['session'] === $sid) $n += (int) $r['seats'];
  }
  return $n;
}

function ticket_taken(array $ev, string $tid, string $sid, ?array $regs = null): int {
  $n = 0;
  foreach ($regs ?? regs_all() as $r) {
    if ($r['event'] === $ev['id'] && $r['ticket'] === $tid && holds_seat($r) && (is_package($ev) || $r['session'] === $sid)) $n += (int) $r['qty'];
  }
  return $n;
}

// null = sınırsız
function seats_left(array $ev, string $sid, ?array $regs = null): ?int {
  $cap = capacity_of($ev, $sid);
  return $cap > 0 ? max(0, $cap - seats_taken($ev, $sid, $regs)) : null;
}

function reg_deadline(array $ev, array $s): int {
  return session_ts($s) - (int) ($ev['reg_close_hours'] ?? 0) * 3600;
}

// Bir oturum için kayıt durumu: acik | dolu | kapali | gecti | iptal
function session_state(array $ev, array $s, ?array $regs = null): string {
  if (in_array($ev['status'] ?? '', ['iptal', 'ertelendi'], true) || ($s['status'] ?? 'acik') === 'iptal') return 'iptal';
  $first = is_package($ev) ? (event_sessions($ev, false)[0] ?? $s) : $s;
  if (session_ts($first, true) < time()) return 'gecti';
  if (empty($ev['reg_open']) || time() > reg_deadline($ev, $first)) return 'kapali';
  if (($s['status'] ?? '') === 'dolu') return 'dolu';
  $left = seats_left($ev, $s['id'], $regs);
  return $left === 0 ? 'dolu' : 'acik';
}

// Katılıma açık oturumlar (paket etkinlikte ilk oturum temsilcidir)
function bookable_sessions(array $ev): array {
  $list = event_sessions($ev, false);
  if (is_package($ev)) return $list ? [$list[0]] : [];
  return array_values(array_filter($list, fn($s) => session_ts($s, true) >= time()));
}

function event_state(array $ev): string {
  if (($ev['status'] ?? '') === 'iptal') return 'iptal';
  if (($ev['status'] ?? '') === 'ertelendi') return 'ertelendi';
  if (event_is_past($ev)) return 'gecti';
  $states = array_map(fn($s) => session_state($ev, $s), bookable_sessions($ev));
  if (in_array('acik', $states, true)) return 'acik';
  if (in_array('dolu', $states, true)) return 'dolu';
  return 'kapali';
}

function state_label(string $st, array $ev = []): string {
  return [
    'acik' => 'Kayıtlar açık', 'dolu' => !empty($ev['waitlist']) ? 'Kontenjan doldu · yedek liste açık' : 'Kontenjan doldu',
    'kapali' => 'Kayıtlar kapandı', 'gecti' => 'Etkinlik tamamlandı', 'iptal' => 'İptal edildi', 'ertelendi' => 'Ertelendi',
  ][$st] ?? '';
}

// Toplam kalan yer (kartlarda "Son 3 kişilik yer" gibi)
function seats_hint(array $ev): string {
  if (empty($ev['show_left'])) return '';
  $lefts = [];
  foreach (bookable_sessions($ev) as $s) {
    if (session_state($ev, $s) === 'kapali') continue;
    $l = seats_left($ev, $s['id']);
    if ($l === null) return '';
    $lefts[] = ($s['status'] ?? '') === 'dolu' ? 0 : $l;
  }
  if (!$lefts) return '';
  $open = array_filter($lefts, fn($l) => $l > 0);
  if (!$open) return '';
  if (count($lefts) === 1) { $l = $lefts[0]; return $l <= 5 ? 'Son ' . $l . ' kişilik yer' : $l . ' kişilik yer kaldı'; }
  $full = count($lefts) - count($open);
  if ($full > 0) return count($open) . ' tarihte yer var, ' . $full . ' tarih doldu';
  return min($open) <= 5 ? 'Bazı tarihlerde son yerler' : '';
}

function reg_code(): string {
  $abc = 'ABCDEFGHJKLMNPRSTUVYZ23456789';
  $codes = array_column(regs_all(), 'code');
  do {
    $c = 'ISE-';
    for ($i = 0; $i < 6; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)];
  } while (in_array($c, $codes, true));
  return $c;
}

function reg_key(array $r): string {
  return substr(hash_hmac('sha256', 'bilet|' . $r['code'], secret()), 0, 16);
}

function ticket_url(array $r, bool $withKey = false): string {
  return '/bilet/' . rawurlencode($r['code']) . '/' . ($withKey ? '?k=' . reg_key($r) : '');
}

function user_regs(string $uid): array {
  $list = array_values(array_filter(regs_all(), fn($r) => ($r['user'] ?? '') === $uid));
  usort($list, fn($a, $b) => strcmp($b['created'], $a['created']));
  return $list;
}

function user_has_reg(string $uid, string $eid): ?array {
  foreach (regs_all() as $r) if (($r['user'] ?? '') === $uid && $r['event'] === $eid && $r['status'] !== 'iptal') return $r;
  return null;
}

// Etkinliğe katılan (iptal etmemiş) üyeler
function attendee_ids(array $ev, bool $confirmedOnly = false): array {
  $ids = [];
  foreach (regs_of_event($ev['id']) as $r) {
    if (($r['user'] ?? '') === '') continue;
    if ($confirmedOnly ? $r['status'] === 'onayli' : in_array($r['status'], SEAT_STATUSES, true)) $ids[$r['user']] = true;
  }
  return array_keys($ids);
}

// Katılan ve profilinde etkinliklerini göstermeyi kabul eden üyeler
function public_attendees(array $ev): array {
  return array_values(array_filter(array_map('user_by_id', attendee_ids($ev)), fn($u) => $u && user_public($u) && ($u['show_events'] ?? true)));
}

// ---------- Katılmayı düşünüyorum ----------
function interests_of(string $eid): array {
  return json_read(INTERESTS_FILE)[$eid] ?? [];
}

function interested_users(string $eid): array {
  $map = interests_of($eid);
  arsort($map);
  return array_values(array_filter(array_map('user_by_id', array_keys($map)), fn($u) => $u && user_public($u) && ($u['show_events'] ?? true)));
}

function user_interests(string $uid): array {
  $out = [];
  foreach (json_read(INTERESTS_FILE) as $eid => $map) if (isset($map[$uid])) $out[$eid] = $map[$uid];
  arsort($out);
  return $out;
}

// ---------- Etkinlik yorumları ----------
function event_comments(string $eid, bool $publicOnly = true): array {
  $all = json_read(COMMENTS_FILE)[$eid] ?? [];
  if (!$publicOnly) return $all;
  // Askıya alınmış üyelerin yorumları (ve onlara gelen yanıtlar) gösterilmez
  $hidden = [];
  foreach ($all as $c) { $u = ($c['user'] ?? '') !== '' ? user_by_id($c['user']) : null; if ($u && !user_public($u)) $hidden[$c['id']] = true; }
  return array_values(array_filter($all, fn($c) => ($c['status'] ?? '') === 'yayinda' && !isset($hidden[$c['id']]) && !isset($hidden[$c['parent'] ?? ''])));
}

function comment_count(string $eid): int {
  return count(event_comments($eid));
}

// ---------- Listeler ----------
function upcoming_events(int $limit = 0, ?callable $filter = null): array {
  $list = array_values(array_filter(events_all(), fn($ev) => ($ev['status'] ?? '') === 'yayinda' && !event_is_past($ev) && (!$filter || $filter($ev))));
  usort($list, fn($a, $b) => [empty($b['pinned']), event_sort_ts($a)] <=> [empty($a['pinned']), event_sort_ts($b)]);
  return $limit ? array_slice($list, 0, $limit) : $list;
}

function past_events(int $limit = 0): array {
  $list = array_values(array_filter(events_all(), fn($ev) => event_visible($ev) && event_is_past($ev)));
  usort($list, fn($a, $b) => event_sort_ts($b) <=> event_sort_ts($a));
  return $limit ? array_slice($list, 0, $limit) : $list;
}

// Takvim: verilen aydaki oturumlar, tarih => [[etkinlik, oturum], ...]
function month_items(int $y, int $m): array {
  $prefix = sprintf('%04d-%02d-', $y, $m);
  $out = [];
  foreach (events_all() as $ev) {
    if (!event_visible($ev)) continue;
    foreach (event_sessions($ev) as $s) {
      if (!str_starts_with($s['date'] ?? '', $prefix)) continue;
      $out[$s['date']][] = [$ev, $s];
    }
  }
  foreach ($out as &$list) usort($list, fn($a, $b) => session_ts($a[1]) <=> session_ts($b[1]));
  ksort($out);
  return $out;
}

// ---------- Takvime ekle ----------
function ics_escape(string $s): string {
  return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], $s);
}

function gcal_url(array $ev, array $s): string {
  $fmt = fn($ts) => gmdate('Ymd\THis\Z', $ts);
  $v = venue($s['venue'] ?? '');
  return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode($ev['title'])
    . '&dates=' . $fmt(session_ts($s)) . '/' . $fmt(max(session_ts($s, true), session_ts($s) + 3600))
    . '&details=' . rawurlencode(($ev['summary'] ?? '') . "\n" . site_url(event_url($ev)))
    . '&location=' . rawurlencode($v ? trim($v['name'] . ', ' . ($v['address'] ?? '')) : '');
}

function map_url(?array $v): string {
  if (!$v) return '';
  if (trim($v['map_url'] ?? '') !== '') return $v['map_url'];
  $q = trim(implode(', ', array_filter([$v['name'] ?? '', $v['address'] ?? '', $v['district'] ?? '', $v['city'] ?? ''])));
  return $q !== '' && ($v['type'] ?? '') !== 'online' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q) : '';
}

// ---------- Fatura ----------
// Ödemesi alınan ücretli kayıtlar için fatura takibi. Faturanın kendisi e-Arşiv portalında ya da muhasebe programında kesilir;
// panel kimin faturasının kesileceğini, alıcı bilgilerini ve kesilen faturanın numarasını tutar.
function invoice_due(array $r): bool {
  return (float) ($r['total'] ?? 0) > 0 && !empty($r['paid']) && $r['status'] !== 'iptal' && trim((string) ($r['inv_no'] ?? '')) === '';
}

function invoice_corporate(array $r): bool { return trim((string) ($r['invoice']['title'] ?? '')) !== ''; }

function invoice_buyer(array $r): string {
  $i = $r['invoice'] ?? [];
  if (!invoice_corporate($r)) return $r['name'] . (trim((string) ($i['tax_no'] ?? '')) !== '' ? ' · TC ' . $i['tax_no'] : '');
  return $i['title'] . ' · ' . $i['tax_office'] . ' VD · ' . $i['tax_no'];
}

function vat_split(float $gross): array {
  $rate = max(0, (float) setting('vat_rate', 20));
  $net = round($gross / (1 + $rate / 100), 2);
  return [$net, round($gross - $net, 2), $rate];
}

function tckn_valid(string $n): bool {
  if (!preg_match('/^[1-9]\d{10}$/', $n)) return false;
  $d = array_map('intval', str_split($n));
  $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
  $even = $d[1] + $d[3] + $d[5] + $d[7];
  return (($odd * 7 - $even) % 10 + 10) % 10 === $d[9] && array_sum(array_slice($d, 0, 10)) % 10 === $d[10];
}

// Katılım formundan gelen fatura bilgisi.
// Bireysel: kayıttaki ad soyada kesilir; T.C. kimlik no ve adres zorunlu.
// Kurumsal: unvan, vergi dairesi, vergi no ve adres zorunlu.
function invoice_from_post(array &$errors): array {
  $f = [];
  foreach (['title' => 160, 'tax_office' => 60, 'tax_no' => 11, 'tckn' => 11, 'address' => 300] as $k => $max) $f[$k] = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($_POST['inv_' . $k] ?? ''))), 0, $max);
  if (mb_strlen($f['address']) < 3) $errors[] = 'Fatura adresinizi yazın (il ve ilçe yeterli).';
  if (($_POST['inv_type'] ?? '') !== 'kurumsal') {
    if ($f['tckn'] === '') $errors[] = 'Fatura için T.C. kimlik numaranızı yazın.';
    elseif (!tckn_valid($f['tckn'])) $errors[] = 'T.C. kimlik numarası geçerli görünmüyor, kontrol edin.';
    return ['type' => 'bireysel', 'tax_no' => $f['tckn'], 'address' => $f['address']];
  }
  if ($f['title'] === '' || $f['tax_office'] === '') $errors[] = 'Şirket adına fatura için unvan ve vergi dairesini yazın.';
  if (!preg_match('/^\d{10,11}$/', $f['tax_no'])) $errors[] = 'Vergi numarası 10, şahıs şirketlerinde T.C. kimlik numarası 11 haneli olmalı.';
  return ['type' => 'kurumsal', 'title' => $f['title'], 'tax_office' => $f['tax_office'], 'tax_no' => $f['tax_no'], 'address' => $f['address']];
}
