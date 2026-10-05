<?php
// iyzico ile kartla ödeme (Ortak Ödeme Sayfası / Checkout Form).
// Anahtarlar data/iyzico.php dosyasındadır (Panel > Ayarlar > Kartla ödeme). Dosya yoksa kartla ödeme seçeneği görünmez.
// Akış: odeme.php ödeme formunu başlatır ve kişiyi iyzico'ya yönlendirir; iyzico sonucu odeme-sonuc.php adresine gönderir,
// orada sonuç iyzico'dan yeniden sorgulanır ve ödeme başarılıysa kayıt "ödendi" olur.

const IYZICO_FILE = DATA . '/iyzico.php';

function iyzico_config(): ?array {
  static $c = false;
  if ($c === false) {
    $c = is_file(IYZICO_FILE) ? (include IYZICO_FILE) : null;
    if (!is_array($c) || trim((string) ($c['api_key'] ?? '')) === '' || trim((string) ($c['secret'] ?? '')) === '') $c = null;
  }
  return $c;
}

function iyzico_on(): bool { return iyzico_config() !== null; }

function iyzico_base(array $cfg): string {
  if (!empty($cfg['base'])) return rtrim((string) $cfg['base'], '/'); // yalnızca yerel deneme için
  return !empty($cfg['sandbox']) ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com';
}

// iyzico fiyat biçimi: "150.0", "149.9", "149.95"
function iyzico_price(float $v): string {
  $s = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
  return str_contains($s, '.') ? $s : $s . '.0';
}

// IYZWSv2 imzalı istek. Hata olursa status=failure ve errorMessage döner, hiçbir zaman istisna fırlatmaz.
function iyzico_call(array $cfg, string $path, array $body): array {
  $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $rnd = (string) round(microtime(true) * 1000) . bin2hex(random_bytes(4));
  $sig = bin2hex(hash_hmac('sha256', $rnd . $path . $json, (string) $cfg['secret'], true));
  $headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: IYZWSv2 ' . base64_encode('apiKey:' . $cfg['api_key'] . '&randomKey:' . $rnd . '&signature:' . $sig),
    'x-iyzi-rnd: ' . $rnd,
    'x-iyzi-client-version: iseatolye-1.0',
  ];
  $url = iyzico_base($cfg) . $path;
  $out = false; $err = '';
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30]);
    $out = curl_exec($ch);
    if ($out === false) $err = curl_error($ch);
    curl_close($ch);
  } else {
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $json, 'timeout' => 30, 'ignore_errors' => true]]);
    $out = @file_get_contents($url, false, $ctx);
    if ($out === false) $err = 'sunucuya bağlanılamadı';
  }
  if ($out === false) { error_log('iyzico bağlantı hatası: ' . $err); return ['status' => 'failure', 'errorMessage' => 'Ödeme sistemine bağlanılamadı (' . $err . ').']; }
  $d = json_decode((string) $out, true);
  return is_array($d) ? $d : ['status' => 'failure', 'errorMessage' => 'Ödeme sisteminin yanıtı okunamadı.'];
}

// Anahtarları denemek için kart BIN sorgusu (ödeme oluşturmaz)
function iyzico_test(array $cfg): string {
  $r = iyzico_call($cfg, '/payment/bin/check', ['locale' => 'tr', 'conversationId' => 'deneme', 'binNumber' => '554960']);
  return ($r['status'] ?? '') === 'success' ? '' : (string) ($r['errorMessage'] ?? 'Bilinmeyen hata');
}

function iyzico_split_name(string $full): array {
  $parts = preg_split('/\s+/u', trim($full)) ?: [];
  if (count($parts) < 2) return [$parts[0] ?? 'Katılımcı', $parts[0] ?? 'Katılımcı'];
  $last = array_pop($parts);
  return [implode(' ', $parts), $last];
}

function iyzico_gsm(string $phone): string {
  $d = preg_replace('/\D/', '', $phone);
  if (str_starts_with($d, '90')) $d = substr($d, 2);
  $d = ltrim($d, '0');
  return $d !== '' ? '+90' . $d : '';
}

// Kayıt için ödeme formunu başlatır. Başarılıysa ['ok', paymentPageUrl], değilse ['err', mesaj].
function iyzico_start(array $r, array $ev): array {
  $cfg = iyzico_config();
  if (!$cfg) return ['err', 'Kartla ödeme şu an kullanılamıyor.'];
  $inv = $r['invoice'] ?? [];
  [$first, $last] = iyzico_split_name((string) $r['name']);
  $s = session_by_id($ev, (string) $r['session']) ?? (event_sessions($ev, false)[0] ?? []);
  $v = venue($s['venue'] ?? '');
  $city = trim((string) ($v['city'] ?? '')) ?: 'Istanbul';
  $addr = trim((string) ($inv['address'] ?? '')) ?: trim((string) (content()['contact']['address'] ?? '')) ?: $city;
  $idNo = preg_match('/^\d{11}$/', (string) ($inv['tax_no'] ?? '')) ? $inv['tax_no'] : '11111111111';
  $total = (float) $r['total'];
  $body = [
    'locale' => 'tr',
    'conversationId' => $r['code'],
    'price' => iyzico_price($total),
    'paidPrice' => iyzico_price($total),
    'currency' => 'TRY',
    'basketId' => $r['id'],
    'paymentGroup' => 'PRODUCT',
    'callbackUrl' => site_url('/odeme-sonuc.php'),
    'enabledInstallments' => [1],
    'buyer' => [
      'id' => ($r['user'] ?? '') !== '' ? $r['user'] : 'misafir-' . $r['code'],
      'name' => $first, 'surname' => $last,
      'gsmNumber' => iyzico_gsm((string) $r['phone']),
      'email' => $r['email'],
      'identityNumber' => $idNo,
      'registrationAddress' => $addr,
      'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
      'city' => $city, 'country' => 'Turkey',
    ],
    'billingAddress' => [
      'contactName' => invoice_corporate($r) ? $inv['title'] : $r['name'],
      'city' => $city, 'country' => 'Turkey', 'address' => $addr,
    ],
    'basketItems' => [[
      'id' => (string) ($r['ticket'] ?: $r['id']),
      'name' => mb_substr($ev['title'] . ' (' . $r['ticket_name'] . ' × ' . (int) $r['qty'] . ')', 0, 120),
      'category1' => 'Etkinlik',
      'itemType' => 'VIRTUAL',
      'price' => iyzico_price($total),
    ]],
  ];
  if ($body['buyer']['gsmNumber'] === '') unset($body['buyer']['gsmNumber']);
  $res = iyzico_call($cfg, '/payment/iyzipos/checkoutform/initialize/auth/ecom', $body);
  if (($res['status'] ?? '') === 'success' && !empty($res['paymentPageUrl'])) return ['ok', (string) $res['paymentPageUrl']];
  error_log('iyzico başlatma hatası (' . $r['code'] . '): ' . ($res['errorCode'] ?? '') . ' ' . ($res['errorMessage'] ?? ''));
  return ['err', 'Ödeme sayfası açılamadı: ' . ($res['errorMessage'] ?? 'bilinmeyen hata')];
}

// Ödeme sonucunu token ile iyzico'dan sorgular.
function iyzico_result(string $token): array {
  $cfg = iyzico_config();
  if (!$cfg) return ['status' => 'failure', 'errorMessage' => 'Kartla ödeme kapalı.'];
  return iyzico_call($cfg, '/payment/iyzipos/checkoutform/auth/ecom/detail/', ['locale' => 'tr', 'token' => $token]);
}
