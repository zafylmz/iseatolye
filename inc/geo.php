<?php
// IP adresinden şehir/ülke bulma. Konum verisi sunucuda durur (DB-IP Lite, CC BY 4.0),
// IP adresi hiçbir dış servise gönderilmez. Dosya biçimi: MaxMind DB (.mmdb).
declare(strict_types=1);

const GEO_DIR = ROOT . '/data/geo';
const GEO_FILE = GEO_DIR . '/city.mmdb';

// IP'nin son kısmını gizler: 88.241.12.34 → 88.241.12.x, IPv6 → ilk 3 blok.
function ip_masked(string $ip): string {
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return preg_replace('/\.\d+$/', '.x', $ip);
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $bin = inet_pton($ip);
    return implode(':', array_map(fn($h) => ltrim($h, '0') ?: '0', str_split(bin2hex(substr($bin, 0, 6)), 4))) . '::x';
  }
  return '';
}

function country_name(string $iso, string $fallback = ''): string {
  if ($iso === '') return $fallback;
  if (class_exists('Locale')) {
    $n = Locale::getDisplayRegion('-' . $iso, 'tr');
    if ($n !== '' && $n !== $iso) return $n;
  }
  return $fallback ?: $iso;
}

// ['country' => 'TR', 'city' => 'İstanbul', 'region' => '...'] ya da boş dizi
function geo_lookup(string $ip): array {
  static $db = false;
  if ($db === false) {
    try { $db = is_file(GEO_FILE) ? new MmdbReader(GEO_FILE) : null; } catch (Throwable $e) { $db = null; }
  }
  if (!$db || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return [];
  try { $r = $db->get($ip); } catch (Throwable $e) { return []; }
  if (!is_array($r)) return [];
  $name = fn($x) => is_array($x['names'] ?? null) ? (string) ($x['names']['tr'] ?? $x['names']['en'] ?? '') : '';
  return [
    'country' => (string) ($r['country']['iso_code'] ?? ''),
    'country_name' => $name($r['country'] ?? []),
    'city' => $name($r['city'] ?? []),
    'region' => $name($r['subdivisions'][0] ?? []),
  ];
}

// Konum veritabanını DB-IP'den indirir (bu ay ya da geçen ay). Panelden çağrılır.
function geo_download(): string {
  if (!is_dir(GEO_DIR)) mkdir(GEO_DIR, 0755, true);
  @set_time_limit(600);
  $gz = GEO_DIR . '/download.mmdb.gz';
  $tmp = GEO_DIR . '/download.mmdb';
  foreach ([0, 1] as $back) {
    $ym = date('Y-m', strtotime("first day of -$back month"));
    $fh = fopen($gz, 'wb');
    $ch = curl_init("https://download.db-ip.com/free/dbip-city-lite-$ym.mmdb.gz");
    curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 500, CURLOPT_FAILONERROR => true]);
    $ok = curl_exec($ch);
    curl_close($ch);
    fclose($fh);
    if (!$ok || filesize($gz) < 1000000) continue;
    $in = gzopen($gz, 'rb'); $out = fopen($tmp, 'wb');
    while (!gzeof($in)) fwrite($out, gzread($in, 1 << 20));
    gzclose($in); fclose($out);
    @unlink($gz);
    try {
      $test = new MmdbReader($tmp);
      $test->get('8.8.8.8');
    } catch (Throwable $e) { @unlink($tmp); continue; }
    rename($tmp, GEO_FILE);
    return $ym;
  }
  @unlink($gz); @unlink($tmp);
  throw new RuntimeException('Konum verisi indirilemedi. Biraz sonra tekrar deneyin.');
}

// Bağımsız, küçük bir MaxMind DB okuyucu (yalnızca okuma).
final class MmdbReader {
  private $fh;
  private int $nodeCount;
  private int $recordSize;
  private int $nodeBytes;
  private int $dataStart;
  private int $ipv4Start = 0;
  private int $ipVersion;

  public function __construct(string $file) {
    $this->fh = fopen($file, 'rb');
    if (!$this->fh) throw new RuntimeException('mmdb açılamadı');
    $size = filesize($file);
    $tail = min($size, 128 * 1024);
    fseek($this->fh, $size - $tail);
    $buf = fread($this->fh, $tail);
    $pos = strrpos($buf, "\xAB\xCD\xEFMaxMind.com");
    if ($pos === false) throw new RuntimeException('mmdb meta verisi yok');
    $metaStart = $size - $tail + $pos + 14;
    $this->dataStart = 0;
    [$meta] = $this->decode($metaStart, $metaStart);
    $this->nodeCount = (int) $meta['node_count'];
    $this->recordSize = (int) $meta['record_size'];
    $this->ipVersion = (int) $meta['ip_version'];
    $this->nodeBytes = $this->recordSize * 2 / 8;
    $this->dataStart = $this->nodeCount * $this->nodeBytes + 16;
    if ($this->ipVersion === 6) {
      $node = 0;
      for ($i = 0; $i < 96 && $node < $this->nodeCount; $i++) $node = $this->record($node, 0);
      $this->ipv4Start = $node;
    }
  }

  public function get(string $ip) {
    $bin = inet_pton($ip);
    if ($bin === false) return null;
    $bits = strlen($bin) * 8;
    $node = $bits === 32 ? $this->ipv4Start : 0;
    if ($bits === 128 && $this->ipVersion === 4) return null;
    for ($i = 0; $i < $bits && $node < $this->nodeCount; $i++) {
      $bit = (ord($bin[$i >> 3]) >> (7 - ($i & 7))) & 1;
      $node = $this->record($node, $bit);
    }
    if ($node <= $this->nodeCount) return null;
    $off = $this->dataStart + ($node - $this->nodeCount - 16);
    return $this->decode($off, $this->dataStart)[0];
  }

  private function record(int $node, int $bit): int {
    fseek($this->fh, $node * $this->nodeBytes);
    $b = fread($this->fh, $this->nodeBytes);
    switch ($this->recordSize) {
      case 24: $s = $bit ? substr($b, 3, 3) : substr($b, 0, 3); return unpack('N', "\0" . $s)[1];
      case 28:
        if ($bit) return ((ord($b[3]) & 0x0F) << 24) | unpack('N', "\0" . substr($b, 4, 3))[1];
        return ((ord($b[3]) & 0xF0) << 20) | unpack('N', "\0" . substr($b, 0, 3))[1];
      case 32: return unpack('N', $bit ? substr($b, 4, 4) : substr($b, 0, 4))[1];
    }
    throw new RuntimeException('desteklenmeyen kayıt boyutu');
  }

  private function bytes(int $off, int $n): string {
    if ($n === 0) return '';
    fseek($this->fh, $off);
    return (string) fread($this->fh, $n);
  }

  private static function uint(string $b): int {
    $v = 0;
    for ($i = 0, $l = strlen($b); $i < $l; $i++) $v = ($v << 8) | ord($b[$i]);
    return $v;
  }

  // [değer, sonraki konum]
  private function decode(int $off, int $base): array {
    $ctrl = ord($this->bytes($off++, 1));
    $type = $ctrl >> 5;
    if ($type === 1) {
      $ss = ($ctrl >> 3) & 3; $vvv = $ctrl & 7;
      $n = $ss + 1;
      $p = self::uint($this->bytes($off, $n));
      $off += $n;
      $p = match ($ss) { 0 => ($vvv << 8) | $p, 1 => (($vvv << 16) | $p) + 2048, 2 => (($vvv << 24) | $p) + 526336, 3 => $p };
      return [$this->decode($base + $p, $base)[0], $off];
    }
    if ($type === 0) $type = 7 + ord($this->bytes($off++, 1));
    $size = $ctrl & 0x1F;
    if ($size >= 29) {
      $n = $size - 28;
      $x = self::uint($this->bytes($off, $n));
      $off += $n;
      $size = match ($n) { 1 => 29 + $x, 2 => 285 + $x, 3 => 65821 + $x };
    }
    switch ($type) {
      case 2: case 4: return [$this->bytes($off, $size), $off + $size];
      case 3: return [unpack('E', $this->bytes($off, 8))[1], $off + 8];
      case 15: return [unpack('G', $this->bytes($off, 4))[1], $off + 4];
      case 5: case 6: case 9: case 10: case 8:
        $b = $this->bytes($off, $size);
        return [$size > 7 ? bin2hex($b) : self::uint($b), $off + $size];
      case 7:
        $m = [];
        for ($i = 0; $i < $size; $i++) {
          [$k, $off] = $this->decode($off, $base);
          [$v, $off] = $this->decode($off, $base);
          $m[$k] = $v;
        }
        return [$m, $off];
      case 11:
        $a = [];
        for ($i = 0; $i < $size; $i++) { [$v, $off] = $this->decode($off, $base); $a[] = $v; }
        return [$a, $off];
      case 14: return [$size !== 0, $off];
    }
    return [null, $off];
  }
}

// DB-IP il adlarını Türkçe yazımına çevirir (Istanbul → İstanbul).
function tr_region(string $name): string {
  static $map = null;
  if ($map === null) {
    $map = [];
    foreach (['Adana','Adıyaman','Afyonkarahisar','Ağrı','Aksaray','Amasya','Ankara','Antalya','Ardahan','Artvin','Aydın','Balıkesir','Bartın','Batman','Bayburt','Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı','Çorum','Denizli','Diyarbakır','Düzce','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir','Gaziantep','Giresun','Gümüşhane','Hakkâri','Hatay','Iğdır','Isparta','İstanbul','İzmir','Kahramanmaraş','Karabük','Karaman','Kars','Kastamonu','Kayseri','Kilis','Kırıkkale','Kırklareli','Kırşehir','Kocaeli','Konya','Kütahya','Malatya','Manisa','Mardin','Mersin','Muğla','Muş','Nevşehir','Niğde','Ordu','Osmaniye','Rize','Sakarya','Samsun','Şanlıurfa','Siirt','Sinop','Şırnak','Sivas','Tekirdağ','Tokat','Trabzon','Tunceli','Uşak','Van','Yalova','Yozgat','Zonguldak'] as $p) $map[geo_fold($p)] = $p;
  }
  return $map[geo_fold($name)] ?? $name;
}

function geo_fold(string $s): string {
  return strtolower(preg_replace('/[^a-z]/i', '', strtr($s, ['ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u', 'â' => 'a', 'Â' => 'a'])));
}
