// Site içi ziyaretçi sayacı: sayfa görüntülemesini ve sayfada görünür kalınan süreyi gönderir.
// Çerez kullanmaz; botlar ve otomatik tarayıcılar sayılmaz.
(() => {
  if (navigator.webdriver || !navigator.sendBeacon) return;
  const send = (data) => {
    try { navigator.sendBeacon('/sayac.php', new Blob([JSON.stringify(data)], { type: 'text/plain' })); } catch (e) {}
  };
  let id, shown, total;
  const start = () => {
    id = Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
    total = 0;
    shown = document.visibilityState === 'visible' ? Date.now() : 0;
    send({ e: 'hit', i: id, p: location.pathname, t: document.title, r: document.referrer });
  };
  const leave = () => {
    if (shown) { total += Date.now() - shown; shown = 0; }
    if (total > 0) send({ e: 'leave', i: id, s: Math.round(total / 1000) });
  };
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') leave();
    else if (!shown) shown = Date.now();
  });
  addEventListener('pagehide', leave);
  addEventListener('pageshow', (ev) => { if (ev.persisted) start(); });
  start();
})();
