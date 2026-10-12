<?php
// Etkinlik oturumunu takvim dosyası (.ics) olarak indirir.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
header('X-Robots-Tag: noindex');
$ev = event_by_slug((string) ($_GET['slug'] ?? ''));
if (!$ev || !event_visible($ev)) { http_response_code(404); exit; }
$list = is_package($ev) ? event_sessions($ev, false) : array_filter([session_by_id($ev, (string) ($_GET['o'] ?? '')) ?? next_session($ev)]);
$fmt = fn($ts) => gmdate('Ymd\THis\Z', $ts);
$out = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//iseatolye//tr", "CALSCALE:GREGORIAN", "METHOD:PUBLISH"];
foreach ($list as $s) {
  $v = venue($s['venue'] ?? '');
  $out[] = 'BEGIN:VEVENT';
  $out[] = 'UID:' . $ev['id'] . '-' . $s['id'] . '@' . preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'iseatolye.com.tr'));
  $out[] = 'DTSTAMP:' . $fmt(time());
  $out[] = 'DTSTART:' . $fmt(session_ts($s));
  $out[] = 'DTEND:' . $fmt(max(session_ts($s, true), session_ts($s) + 3600));
  $out[] = 'SUMMARY:' . ics_escape($ev['title']);
  $out[] = 'DESCRIPTION:' . ics_escape(trim(($ev['summary'] ?? '') . "\n" . site_url(event_url($ev))));
  $out[] = 'LOCATION:' . ics_escape($v ? trim($v['name'] . ', ' . implode(', ', array_filter([$v['address'] ?? '', $v['district'] ?? '', $v['city'] ?? '']))) : session_place($s));
  $out[] = 'URL:' . site_url(event_url($ev));
  if (($s['status'] ?? '') === 'iptal' || $ev['status'] === 'iptal') $out[] = 'STATUS:CANCELLED';
  $out[] = 'BEGIN:VALARM';
  $out[] = 'TRIGGER:-PT2H';
  $out[] = 'ACTION:DISPLAY';
  $out[] = 'DESCRIPTION:' . ics_escape($ev['title']);
  $out[] = 'END:VALARM';
  $out[] = 'END:VEVENT';
}
$out[] = 'END:VCALENDAR';
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $ev['slug'] . '.ics"');
echo implode("\r\n", $out) . "\r\n";
