<?php
// Ana sayfanın en üstündeki duyuru bannerı (ör. yılbaşı haftası).
// İçerik content.json > home > banner içinde durur; panelde Sayfalar > Ana sayfa'dan düzenlenir.

function banner_defaults(): array {
  return [
    'on' => true,
    'title' => "Yılbaşı Büyüsü\nİSE Atölye’de Başlıyor",
    'text' => '25–30 Aralık tarihleri arasında, el emeğinin, sanatın ve yaratıcılığın buluştuğu özel bir yılbaşı haftasına davetlisiniz. Eğitmenlerimizin hazırladığı özgün tasarımlar, birbirinden güzel hediyelikler ve gün boyunca gerçekleşecek yaratıcı atölyelerle yeni yıl heyecanını birlikte yaşayacağız.',
    'date' => '25–30 Aralık · Özel Yılbaşı Etkinlikleri',
    'note' => 'Mekân, atölye programı ve tüm sürprizlerimizle ilgili detaylar çok yakında.',
    'url' => '',
    'image' => '/assets/yilbasi-banner.jpg',
    'until' => '2026-12-30',
  ];
}

// Panelde hiç kaydedilmediyse varsayılanlar kullanılır.
function home_banner(): array {
  $b = content()['home']['banner'] ?? null;
  return is_array($b) ? $b + array_map(fn($v) => is_bool($v) ? false : '', banner_defaults()) : banner_defaults();
}

// Açık mı, başlığı var mı, bitiş tarihi geçmedi mi?
function banner_visible(array $b): bool {
  if (empty($b['on']) || trim((string) ($b['title'] ?? '')) === '') return false;
  $until = (string) ($b['until'] ?? '');
  return $until === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) || date('Y-m-d') <= $until;
}
