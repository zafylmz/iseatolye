<?php
// PayTR ile kartla ödeme (iFrame API).
// Bilgiler data/paytr.php dosyasındadır (Panel > Ayarlar > Kartla ödeme). Dosya yoksa kartla ödeme seçeneği görünmez.
// Akış: odeme.php PayTR'den tek kullanımlık ödeme anahtarı alır ve ödeme formunu sayfada çerçeve içinde gösterir.
// Ödemenin sonucu PayTR'nin sunucudan sunucuya gönderdiği bildirimle (paytr-bildirim.php) kesinleşir;
// kişinin döndüğü sayfadaki "başarılı" bilgisine tek başına güvenilmez.

const PAYTR_FILE = DATA . '/paytr.php';
const PAYTR_TOKEN_URL = 'https://www.paytr.com/odeme/api/get-token';
const PAYTR_FRAME_URL = 'https://www.paytr.com/odeme/guvenli/';

function paytr_config(): ?array {
  static $c = false;
  if ($c === false) {
    $c = is_file(PAYTR_FILE) ? (include PAYTR_FILE) : null;
    if (!is_array($c)) $c = null;
    else foreach (['merchant_id', 'merchant_key', 'merchant_salt'] as $k) if (trim((string) ($c[$k] ?? '')) === '') { $c = null; break; }
  }
  return $c;
}

function paytr_on(): bool { return paytr_config() !== null; }

// Katılım kodu "ISE-ABC123" PayTR sipariş numarasında "ISEABC123X1760000000" olur.
// Sipariş numarası yalnızca harf ve rakam içerebilir ve her denemede farklı olmalıdır.
function paytr_oid(array $r): string {
  return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $r['code'])) . 'X' . time() . random_int(10, 99);
}

function paytr_code_of_oid(string $oid): string {
  return preg_match('/^ISE([A-Z0-9]{6})X\d+$/', $oid, $m) ? 'ISE-' . $m[1] : '';
}

function paytr_kurus(float $v): int { return (int) round($v * 100); }

function paytr_phone(string $phone): string {
  $d = preg_replace('/\D/', '', $phone);
  if (str_starts_with($d, '90')) $d = substr($d, 2);
  return $d !== '' ? '0' . ltrim($d, '0') : '05000000000';
}

function paytr_post(array $fields): array {
  $out = false; $err = '';
  if (function_exists('curl_init')) {
    $ch = curl_init(PAYTR_TOKEN_URL);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $out = curl_exec($ch);
    if ($out === false) $err = curl_error($ch);
    curl_close($ch);
  } else {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => http_build_query($fields), 'timeout' => 25, 'ignore_errors' => true]]);
    $out = @file_get_contents(PAYTR_TOKEN_URL, false, $ctx);
    if ($out === false) $err = 'sunucuya bağlanılamadı';
  }
  if ($out === false) { error_log('PayTR bağlantı hatası: ' . $err); return ['status' => 'failed', 'reason' => 'Ödeme sistemine bağlanılamadı (' . $err . ').']; }
  $d = json_decode((string) $out, true);
  return is_array($d) ? $d : ['status' => 'failed', 'reason' => 'Ödeme sisteminin yanıtı okunamadı.'];
}

// Ödeme anahtarı (iframe token) ister. Başarılıysa ['ok', token], değilse ['err', mesaj].
// $o: oid, email, amount (kuruş), basket ([[ad, "150.00", adet]]), name, address, phone, ok_url, fail_url
function paytr_token(array $cfg, array $o): array {
  $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
  $basket = base64_encode(json_encode($o['basket'], JSON_UNESCAPED_UNICODE));
  $noInst = '1'; $maxInst = '0'; $currency = 'TL'; $test = !empty($cfg['test']) ? '1' : '0';
  $hash = $cfg['merchant_id'] . $ip . $o['oid'] . $o['email'] . $o['amount'] . $basket . $noInst . $maxInst . $currency . $test;
  $res = paytr_post([
    'merchant_id' => $cfg['merchant_id'],
    'user_ip' => $ip,
    'merchant_oid' => $o['oid'],
    'email' => $o['email'],
    'payment_amount' => $o['amount'],
    'paytr_token' => base64_encode(hash_hmac('sha256', $hash . $cfg['merchant_salt'], $cfg['merchant_key'], true)),
    'user_basket' => $basket,
    'debug_on' => $test,
    'no_installment' => $noInst,
    'max_installment' => $maxInst,
    'user_name' => mb_substr($o['name'], 0, 60),
    'user_address' => mb_substr($o['address'], 0, 400),
    'user_phone' => mb_substr($o['phone'], 0, 20),
    'merchant_ok_url' => $o['ok_url'],
    'merchant_fail_url' => $o['fail_url'],
    'timeout_limit' => 30,
    'currency' => $currency,
    'test_mode' => $test,
    'lang' => 'tr',
  ]);
  if (($res['status'] ?? '') === 'success' && !empty($res['token'])) return ['ok', (string) $res['token']];
  return ['err', (string) ($res['reason'] ?? 'bilinmeyen hata')];
}

// Paneldeki bilgileri denemek için örnek bir sipariş anahtarı ister (ödeme alınmaz, anahtar kullanılmadan düşer).
function paytr_test(array $cfg): string {
  [$ok, $res] = paytr_token($cfg, [
    'oid' => 'DENEME' . time(), 'email' => 'deneme@iseatolye.com.tr', 'amount' => 100,
    'basket' => [['Deneme', '1.00', 1]], 'name' => 'Deneme', 'address' => 'Deneme', 'phone' => '05000000000',
    'ok_url' => site_url('/'), 'fail_url' => site_url('/'),
  ]);
  return $ok === 'ok' ? '' : $res;
}

// Kayıt için ödeme anahtarı ister. Başarılıysa ['ok', token], değilse ['err', mesaj].
function paytr_start(array $r, array $ev): array {
  $cfg = paytr_config();
  if (!$cfg) return ['err', 'Kartla ödeme şu an kullanılamıyor.'];
  $inv = $r['invoice'] ?? [];
  $addr = trim((string) ($inv['address'] ?? '')) ?: trim((string) (content()['contact']['address'] ?? '')) ?: 'Türkiye';
  $total = (float) $r['total'];
  $back = site_url(ticket_url($r, true));
  [$ok, $res] = paytr_token($cfg, [
    'oid' => paytr_oid($r),
    'email' => (string) $r['email'],
    'amount' => paytr_kurus($total),
    'basket' => [[mb_substr($ev['title'] . ' (' . $r['ticket_name'] . ' × ' . (int) $r['qty'] . ')', 0, 120), number_format($total, 2, '.', ''), 1]],
    'name' => invoice_corporate($r) ? (string) $inv['title'] : (string) $r['name'],
    'address' => $addr,
    'phone' => paytr_phone((string) $r['phone']),
    'ok_url' => $back . '&odeme=ok',
    'fail_url' => $back . '&odeme=hata',
  ]);
  if ($ok !== 'ok') error_log('PayTR başlatma hatası (' . $r['code'] . '): ' . $res);
  return [$ok, $ok === 'ok' ? $res : 'Ödeme sayfası açılamadı: ' . $res];
}

// PayTR bildiriminin imzasını doğrular.
function paytr_verify(array $cfg, array $p): bool {
  $calc = base64_encode(hash_hmac('sha256', ($p['merchant_oid'] ?? '') . $cfg['merchant_salt'] . ($p['status'] ?? '') . ($p['total_amount'] ?? ''), $cfg['merchant_key'], true));
  return hash_equals($calc, (string) ($p['hash'] ?? ''));
}
