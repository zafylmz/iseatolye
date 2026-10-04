<?php
// Görsel kütüphanesi: uploads altındaki bütün görseller, açıklamaları, albümleri ve küçük kopyaları.
// Kütüphane dosya sisteminden okunur; medya.json yalnızca açıklama, albüm ve ekleniş bilgisini tutar.
declare(strict_types=1);

const MEDIA_FILE = DATA . '/medya.json';
const MEDIA_ROOT = ROOT . '/uploads';
const THUMB_SUB = '_kucuk';
const THUMB_SIZE = 720;
const MEDIA_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
const MEDIA_SKIP = ['uyeler', THUMB_SUB];

function media_meta(bool $fresh = false): array {
  static $m = null;
  if ($m === null || $fresh) $m = json_read(MEDIA_FILE, ['items' => [], 'albums' => []]) + ['items' => [], 'albums' => []];
  return $m;
}

// Site yolu (/uploads/...) güvenli ve var olan bir görsel mi? Değilse boş döner.
function media_valid(string $path): string {
  $path = trim($path);
  if (!str_starts_with($path, '/uploads/') || str_contains($path, '..') || str_contains($path, "\0")) return '';
  $first = explode('/', substr($path, 9))[0] ?? '';
  if (in_array($first, MEDIA_SKIP, true)) return '';
  if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), MEDIA_EXT, true)) return '';
  return is_file(ROOT . $path) ? $path : '';
}

// Bütün görseller, en yeni üstte. Her öğe: path, folder, size, time, title, album
function media_all(): array {
  if (!is_dir(MEDIA_ROOT)) return [];
  $meta = media_meta()['items'];
  $out = [];
  $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator(MEDIA_ROOT, FilesystemIterator::SKIP_DOTS),
    fn($f, $k, $iter) => !($iter->hasChildren() && $f->getPath() === MEDIA_ROOT && in_array($f->getFilename(), MEDIA_SKIP, true))
  ));
  foreach ($it as $f) {
    if (!$f->isFile() || !in_array(strtolower($f->getExtension()), MEDIA_EXT, true)) continue;
    $path = '/uploads/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($f->getPathname(), strlen(MEDIA_ROOT) + 1));
    $m = $meta[$path] ?? [];
    $folder = str_contains(substr($path, 9), '/') ? explode('/', substr($path, 9))[0] : '';
    $out[] = [
      'path' => $path, 'folder' => $folder, 'size' => $f->getSize(),
      'time' => strtotime((string) ($m['added'] ?? '')) ?: $f->getMTime(),
      'title' => (string) ($m['title'] ?? ''), 'album' => (string) ($m['album'] ?? ''),
    ];
  }
  usort($out, fn($a, $b) => $b['time'] <=> $a['time'] ?: strcmp($a['path'], $b['path']));
  return $out;
}

function media_item(string $path): ?array {
  foreach (media_all() as $m) if ($m['path'] === $path) return $m;
  return null;
}

// Küçük kopyanın adresi. Henüz yoksa ilk istekte kucuk.php üretir.
function thumb_url(string $path): string {
  if (!str_starts_with($path, '/uploads/') || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'gif') return $path;
  $t = thumb_path($path);
  return is_file(ROOT . $t) ? $t : '/kucuk.php?p=' . rawurlencode($path);
}

function thumb_path(string $path): string {
  return '/uploads/' . THUMB_SUB . '/' . substr($path, 9);
}

// Küçük kopyayı üretir; görsel zaten küçükse ya da GD yoksa orijinal yolu döner.
function make_thumb(string $path): string {
  $src = ROOT . $path;
  $info = @getimagesize($src);
  if (!$info || !function_exists('imagecreatetruecolor')) return $path;
  $type = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? '';
  if ($type === '') return $path;
  $dest = ROOT . thumb_path($path);
  if (is_file($dest)) return thumb_path($path);
  if (max($info[0], $info[1]) <= THUMB_SIZE && !exif_turn($src, $type)) return $path;
  $img = image_open($src, $type);
  if (!$img) return $path;
  $img = image_upright($img, $src, $type);
  $w = imagesx($img); $h = imagesy($img);
  $scale = min(1, THUMB_SIZE / max($w, $h));
  $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
  $dst = imagecreatetruecolor($nw, $nh);
  imagealphablending($dst, false); imagesavealpha($dst, true);
  imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
  if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0755, true);
  $ok = match ($type) { 'jpg' => imagejpeg($dst, $dest, 82), 'png' => imagepng($dst, $dest, 7), 'webp' => imagewebp($dst, $dest, 80) };
  return $ok ? thumb_path($path) : $path;
}

// ---------- Optimizasyon ----------
// Yüklenen görseller en fazla IMAGE_MAX piksel olacak şekilde küçültülür ve sıkıştırılarak kaydedilir; büyük orijinal tutulmaz.
const IMAGE_MAX = 1920;
const IMAGE_BIG = 400 * 1024; // bundan büyük dosyalar "büyük" sayılır

// PNG gerçekten saydamlık kullanıyor mu? (Kullanmıyorsa fotoğraf gibi JPG olarak saklanır.)
function png_has_alpha($img): bool {
  $w = imagesx($img); $h = imagesy($img);
  $stepX = max(1, intdiv($w, 40)); $stepY = max(1, intdiv($h, 40));
  for ($y = 0; $y < $h; $y += $stepY) for ($x = 0; $x < $w; $x += $stepX) if ((imagecolorat($img, $x, $y) >> 24) & 0x7F) return true;
  return false;
}

// $src dosyasını küçültüp sıkıştırarak $destBase + uzantı olarak yazar. $keepType: PNG'yi JPG'ye çevirme (yerinde optimizasyon).
// Dönen değer yazılan uzantı; yazılamazsa ''. Sonuç orijinalden büyükse ve küçültme/döndürme gerekmiyorsa '' döner (orijinal kullanılır).
function image_optimize(string $src, string $type, string $destBase, bool $keepType = false): string {
  if (!function_exists('imagecreatetruecolor') || !in_array($type, ['jpg', 'png', 'webp'], true)) return '';
  $info = @getimagesize($src);
  if (!$info) return '';
  $turn = exif_turn($src, $type);
  $img = image_open($src, $type);
  if (!$img) return '';
  if (!imageistruecolor($img)) { imagealphablending($img, false); imagesavealpha($img, true); imagepalettetotruecolor($img); }
  $img = image_upright($img, $src, $type);
  $sw = imagesx($img); $sh = imagesy($img);
  $scale = min(1, IMAGE_MAX / max($sw, $sh));
  $w = max(1, (int) round($sw * $scale)); $h = max(1, (int) round($sh * $scale));
  $alpha = $type !== 'jpg' && png_has_alpha($img);
  $out = $type === 'png' && !$alpha && !$keepType ? 'jpg' : $type;
  $dst = imagecreatetruecolor($w, $h);
  if ($out === 'jpg') imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
  else { imagealphablending($dst, false); imagesavealpha($dst, true); }
  imagecopyresampled($dst, $img, 0, 0, 0, 0, $w, $h, $sw, $sh);
  if ($out === 'jpg') imageinterlace($dst, true);
  $tmp = $destBase . '.opt-' . bin2hex(random_bytes(3));
  $ok = match ($out) { 'jpg' => imagejpeg($dst, $tmp, 82), 'png' => imagepng($dst, $tmp, 9), 'webp' => imagewebp($dst, $tmp, 80) };
  if (!$ok || !is_file($tmp)) { @unlink($tmp); return ''; }
  if ($scale === 1 && $turn === 0 && $out === $type && filesize($tmp) >= filesize($src)) { @unlink($tmp); return ''; }
  if (!@rename($tmp, $destBase . '.' . $out)) { @unlink($tmp); return ''; }
  @chmod($destBase . '.' . $out, 0644);
  return $out;
}

// Daha önce yüklenmiş, optimize edilmemiş büyük görseller (GIF hariç)
function media_big(): array {
  $meta = media_meta()['items'];
  return array_values(array_filter(media_all(), fn($m) => $m['size'] > IMAGE_BIG && empty($meta[$m['path']]['opt']) && strtolower(pathinfo($m['path'], PATHINFO_EXTENSION)) !== 'gif'));
}

function image_open(string $file, string $type) {
  return match ($type) { 'jpg' => @imagecreatefromjpeg($file), 'png' => @imagecreatefrompng($file), 'webp' => @imagecreatefromwebp($file), default => false };
}

// Telefon fotoğraflarındaki yön bilgisi (EXIF). Döndürülmesi gerekiyorsa açıyı verir.
function exif_turn(string $file, string $type): int {
  if ($type !== 'jpg' || !function_exists('exif_read_data')) return 0;
  $o = (int) (@exif_read_data($file)['Orientation'] ?? 1);
  return [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
}

function image_upright($img, string $file, string $type) {
  $deg = exif_turn($file, $type);
  if ($deg === 0) return $img;
  $r = imagerotate($img, $deg, 0);
  return $r ?: $img;
}

function media_delete_files(string $path): void {
  foreach ([$path, thumb_path($path)] as $p) {
    $file = realpath(ROOT . $p);
    $base = realpath(MEDIA_ROOT);
    if ($file && $base && str_starts_with($file, $base . DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
  }
}

// ---------- Albümler ----------
// Herkese açık galeri: görseli olan etkinlikler ve paneldeki albümler, en yenisi üstte.
function gallery_albums(): array {
  $out = [];
  $byAlbum = [];
  foreach (media_all() as $m) if ($m['album'] !== '') $byAlbum[$m['album']][] = $m['path'];
  foreach (media_meta()['albums'] as $a) {
    if (empty($a['public']) || empty($byAlbum[$a['slug']])) continue;
    $imgs = $byAlbum[$a['slug']];
    $cover = in_array($a['cover'] ?? '', $imgs, true) ? $a['cover'] : $imgs[0];
    $out[] = ['slug' => $a['slug'], 'name' => $a['name'], 'date' => $a['date'] ?? '', 'text' => $a['text'] ?? '', 'images' => $imgs, 'cover' => $cover, 'event' => null];
  }
  foreach (events_all() as $ev) {
    if (empty($ev['gallery']) || ($ev['status'] ?? '') === 'taslak') continue;
    $dates = array_column($ev['sessions'] ?? [], 'date');
    $imgs = array_values(array_filter($ev['gallery'], fn($g) => is_file(ROOT . $g)));
    if (!$imgs) continue;
    $out[] = ['slug' => $ev['slug'], 'name' => $ev['title'], 'date' => $dates ? max($dates) : '', 'text' => $ev['summary'] ?? '', 'images' => $imgs, 'cover' => $imgs[0], 'event' => $ev];
  }
  usort($out, fn($a, $b) => strcmp($b['date'], $a['date']));
  return $out;
}

function gallery_album(string $slug): ?array {
  foreach (gallery_albums() as $a) if ($a['slug'] === $slug) return $a;
  return null;
}
