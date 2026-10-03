<?php
// Basit SMTP gönderici: sunucuda PHP mail() kapalıysa e-postalar cPanel'deki bir e-posta hesabı üzerinden gönderilir.
// Bağlantı bilgisi data/smtp.php dosyasındadır (Panel > Ayarlar > E-posta).
declare(strict_types=1);

const SMTP_FILE = DATA . '/smtp.php';

function smtp_config(): ?array {
  static $c = false;
  if ($c === false) { $c = is_file(SMTP_FILE) ? (include SMTP_FILE) : null; if (!is_array($c) || empty($c['host'])) $c = null; }
  return $c;
}

function smtp_same_server(string $host): bool {
  $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
  if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) return false;
  return str_starts_with($ip, '127.') || $ip === '::1' || $ip === (string) ($_SERVER['SERVER_ADDR'] ?? '-');
}

// Başarılıysa '' döner, değilse hatanın açıklamasını.
function smtp_send(array $c, string $from, string $fromName, string $to, string $subject, string $text, string $replyTo = ''): string {
  $port = (int) ($c['port'] ?? 465) ?: 465;
  $secure = (string) ($c['secure'] ?? ($port === 465 ? 'ssl' : 'tls'));
  $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $port;
  $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $c['host']]]);
  $fp = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
  if (!$fp && $secure !== 'none' && smtp_same_server((string) $c['host'])) {
    // Paylaşımlı sunucularda sertifika adı tutmayabiliyor. Doğrulamasız bağlantı yalnızca e-posta sunucusu sitenin
    // kendi sunucusuysa denenir; bağlantı ağa çıkmadığı için araya girilemez.
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
  }
  if (!$fp) return 'Sunucuya bağlanılamadı (' . $c['host'] . ':' . $port . '): ' . $errstr;
  stream_set_timeout($fp, 15);
  $read = function () use ($fp): string {
    $out = '';
    while (($line = fgets($fp, 1024)) !== false) { $out .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; }
    return $out;
  };
  $cmd = function (string $line, array $ok) use ($fp, $read): string {
    if ($line !== '') fwrite($fp, $line . "\r\n");
    $r = $read();
    if (!in_array((int) substr($r, 0, 3), $ok, true)) throw new RuntimeException(trim($r) ?: 'Sunucu yanıt vermedi.');
    return $r;
  };
  try {
    $cmd('', [220]);
    $helo = preg_replace('/[^a-z0-9.-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $cmd('EHLO ' . $helo, [250]);
    if ($secure === 'tls') {
      $cmd('STARTTLS', [220]);
      if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        if (!smtp_same_server((string) $c['host'])) throw new RuntimeException('Sunucunun güvenlik sertifikası doğrulanamadı.');
        stream_context_set_option($fp, 'ssl', 'verify_peer', false);
        stream_context_set_option($fp, 'ssl', 'verify_peer_name', false);
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Şifreli bağlantı kurulamadı.');
      }
      $cmd('EHLO ' . $helo, [250]);
    }
    if (($c['user'] ?? '') !== '') {
      $cmd('AUTH LOGIN', [334]);
      $cmd(base64_encode((string) $c['user']), [334]);
      try { $cmd(base64_encode((string) ($c['pass'] ?? '')), [235]); }
      catch (RuntimeException $ex) { throw new RuntimeException('Kullanıcı adı ya da şifre kabul edilmedi. (' . $ex->getMessage() . ')'); }
    }
    $cmd('MAIL FROM:<' . $from . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
    $headers = [
      'Date: ' . date('r'),
      'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
      'To: <' . $to . '>',
      'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
      'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
      'MIME-Version: 1.0',
      'Content-Type: text/plain; charset=UTF-8',
      'Content-Transfer-Encoding: base64',
    ];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: <' . $replyTo . '>';
    $body = rtrim(chunk_split(base64_encode(str_replace(["\r\n", "\n"], ["\n", "\r\n"], $text)), 76, "\r\n"));
    $cmd(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.", [250]);
    fwrite($fp, "QUIT\r\n");
    return '';
  } catch (RuntimeException $ex) {
    return $ex->getMessage();
  } finally {
    fclose($fp);
  }
}
