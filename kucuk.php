<?php
// Görselin küçük kopyasını ilk istekte üretir ve gönderir. Sonraki isteklerde dosya doğrudan sunulur.
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/media.php';

$path = media_valid((string) ($_GET['p'] ?? ''));
if ($path === '') { http_response_code(404); exit; }
$out = make_thumb($path);
$file = ROOT . $out;
$type = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=2592000');
header('X-Content-Type-Options: nosniff');
readfile($file);
