<?php
// Çizgi ikonlar (24x24). icon('takvim') gibi kullanılır.
function icon(string $name, string $cls = ''): string {
  static $p = [
    'takvim' => '<rect x="3.5" y="5" width="17" height="15.5" rx="1.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
    'saat' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    'konum' => '<path d="M12 21s-6.5-5.8-6.5-11A6.5 6.5 0 0 1 18.5 10c0 5.2-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
    'kisi' => '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c.8-3.6 3.6-5.5 7-5.5s6.2 1.9 7 5.5"/>',
    'kisiler' => '<circle cx="9" cy="9" r="3.2"/><path d="M3 19.5c.6-3.2 3-5 6-5s5.4 1.8 6 5"/><path d="M15.5 6.2a3 3 0 0 1 0 5.6M17.5 14.8c1.8.6 3 2.2 3.4 4.7"/>',
    'bilet' => '<path d="M4 7.5A1.5 1.5 0 0 1 5.5 6h13A1.5 1.5 0 0 1 20 7.5V10a2 2 0 0 0 0 4v2.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 16.5V14a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="1.6 2"/>',
    'yildiz' => '<path d="M12 4.5l2.2 4.6 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5L4.8 9.8l5-.7z"/>',
    'el' => '<path d="M12 20.5s-7.5-4.6-9.3-9.2C1.4 8 3.4 4.5 6.9 4.5c2 0 3.6 1.1 5.1 3 1.5-1.9 3.1-3 5.1-3 3.5 0 5.5 3.5 4.2 6.8-1.8 4.6-9.3 9.2-9.3 9.2z"/>',
    'onay' => '<circle cx="12" cy="12" r="8.5"/><path d="M8.3 12.3l2.4 2.4 5-5.1"/>',
    'tik' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
    'sol' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    'sag' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'geri' => '<path d="M15 5l-7 7 7 7"/>',
    'ileri' => '<path d="M9 5l7 7-7 7"/>',
    'yorum' => '<path d="M4.5 6.5A1.5 1.5 0 0 1 6 5h12a1.5 1.5 0 0 1 1.5 1.5v8.5A1.5 1.5 0 0 1 18 16.5h-7l-4.5 3.5v-3.5H6A1.5 1.5 0 0 1 4.5 15z"/>',
    'posta' => '<rect x="3.5" y="5.5" width="17" height="13" rx="1.5"/><path d="M4 7l8 6 8-6"/>',
    'telefon' => '<path d="M6.5 3.5h3l1.5 4-2 1.5a11 11 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2 2A16 16 0 0 1 4.5 5.5a2 2 0 0 1 2-2z"/>',
    'kapat' => '<path d="M6 6l12 12M18 6L6 18"/>',
    'arti' => '<path d="M12 5v14M5 12h14"/>',
    'dis' => '<path d="M14 4.5h5.5V10M19.5 4.5L11 13M17 14v4.5a1 1 0 0 1-1 1H5.5a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1H10"/>',
    'bilgi' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.8v.2"/>',
    'yaprak' => '<path d="M5 19c0-8 5-13.5 14.5-14-.5 9.5-6 14.5-14 14.5z"/><path d="M5 19l7.5-7.5"/>',
    'kalem' => '<path d="M4.5 19.5l1-4L15.8 5.2a2 2 0 0 1 2.9 0l.1.1a2 2 0 0 1 0 2.9L8.5 18.5z"/><path d="M14 7l3 3"/>',
    'el-emegi' => '<path d="M7 11V6.5a1.5 1.5 0 0 1 3 0V11M10 10V5a1.5 1.5 0 0 1 3 0v5M13 10V6a1.5 1.5 0 0 1 3 0v6.5"/><path d="M16 9.5a1.5 1.5 0 0 1 3 0V14a6.5 6.5 0 0 1-6.5 6.5h-.8A6 6 0 0 1 7 18l-2.6-4a1.5 1.5 0 0 1 2.4-1.8L7 13"/>',
    'nilufer' => '<path d="M12 19c-4 0-7.5-2-8.5-5 2.5-.5 5 0 6.5 1.5M12 19c4 0 7.5-2 8.5-5-2.5-.5-5 0-6.5 1.5M12 19c-2-2-3-5-2.5-9 1 .3 2 .9 2.5 1.8.5-.9 1.5-1.5 2.5-1.8.5 4-.5 7-2.5 9z"/><path d="M12 11.8V5.5"/>',
    'ekip' => '<circle cx="12" cy="7" r="2.6"/><circle cx="5.5" cy="10" r="2.1"/><circle cx="18.5" cy="10" r="2.1"/><path d="M8 19.5c.4-3 2-4.8 4-4.8s3.6 1.8 4 4.8M2.5 18c.3-2 1.4-3.4 3-3.4M21.5 18c-.3-2-1.4-3.4-3-3.4"/>',
    'isik' => '<path d="M9 17.5h6M10 20.5h4M12 3.5a5.5 5.5 0 0 0-3.5 9.8c.7.6 1 1.4 1 2.2h5c0-.8.3-1.6 1-2.2A5.5 5.5 0 0 0 12 3.5z"/>',
    'cikis' => '<path d="M14 4.5h4a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5h-4M10 16l-4-4 4-4M6 12h9"/>',
    'liste' => '<path d="M9 6.5h11M9 12h11M9 17.5h11M4.5 6.5h.01M4.5 12h.01M4.5 17.5h.01"/>',
    'izgara' => '<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/>',
    'ara' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>',
    'indir' => '<path d="M12 4v11M7 10.5l5 5 5-5M5 19.5h14"/>',
    'paylas' => '<circle cx="17.5" cy="6" r="2.5"/><circle cx="6.5" cy="12" r="2.5"/><circle cx="17.5" cy="18" r="2.5"/><path d="M8.8 10.8l6.4-3.6M8.8 13.2l6.4 3.6"/>',
    'ayar' => '<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.2M12 18.3v2.2M20.5 12h-2.2M5.7 12H3.5M18 6l-1.6 1.6M7.6 16.4L6 18M18 18l-1.6-1.6M7.6 7.6L6 6"/>',
    'cevrimici' => '<rect x="3.5" y="5" width="17" height="11.5" rx="1.5"/><path d="M8.5 20h7M12 16.5V20"/>',
    'instagram' => '<rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.6"/><path d="M16.8 7.2h.01"/>',
    'youtube' => '<rect x="3" y="6" width="18" height="12" rx="3.5"/><path d="M10.5 9.5v5l4.2-2.5z"/>',
    'linkedin' => '<rect x="4" y="4" width="16" height="16" rx="2.5"/><path d="M8.5 10.5v5.5M8.5 7.8v.2M12 16v-5.5M12 13c0-1.5 1-2.5 2.3-2.5s2.2.9 2.2 2.5v3"/>',
    'whatsapp' => '<path d="M4.5 19.5l1.2-3.7A8 8 0 1 1 8.6 18.6z"/><path d="M9.2 8.6c.2-.4.5-.4.8-.4h.4c.2 0 .3.1.4.4l.6 1.4c.1.2 0 .4-.1.6l-.5.6c.5 1 1.4 1.9 2.4 2.4l.6-.5c.2-.1.4-.2.6-.1l1.4.6c.3.1.4.2.4.4v.4c0 .3 0 .6-.4.8-.5.3-1.2.5-1.8.3-2.2-.6-4-2.4-4.6-4.6-.2-.6 0-1.3.3-1.8z"/>',
  ];
  $d = $p[$name] ?? $p['onay'];
  return '<svg class="ico' . ($cls !== '' ? ' ' . $cls : '') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

// Sosyal medya adı → ikon
function social_icon(string $label): string {
  $l = mb_strtolower($label);
  foreach (['instagram', 'youtube', 'linkedin', 'whatsapp'] as $k) if (str_contains($l, $k)) return icon($k);
  return icon('dis');
}
