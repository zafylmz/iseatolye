<?php
// Kartla ödeme başlatma: kaydın ödemesi için iyzico ödeme sayfasına yönlendirir.
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/iyzico.php';
$r = reg_by_code((string) ($_GET['kod'] ?? ''));
$me = current_user();
$keyOk = $r && hash_equals(reg_key($r), (string) ($_GET['k'] ?? ''));
if (!$r || (!$keyOk && (!$me || ($r['user'] ?? '') !== $me['id']))) { http_response_code(404); include __DIR__ . '/404.php'; exit; }
$back = ticket_url($r, true);
$ev = event_by_id($r['event']);
if (!$ev || !empty($r['paid']) || (float) $r['total'] <= 0 || !in_array($r['status'], ['beklemede', 'onayli'], true)) { header('Location: ' . $back, true, 303); exit; }
if (!iyzico_on() || !rate_hit('odeme:' . client_hash(), 20, 3)) { header('Location: ' . $back . '&odeme=kapali', true, 303); exit; }
[$ok, $res] = iyzico_start($r, $ev);
header('Location: ' . ($ok === 'ok' ? $res : $back . '&odeme=hata'), true, 303);
