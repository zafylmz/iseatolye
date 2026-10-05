  </main>
  <footer class="footer">
    <div class="wrap footer__grid">
      <div class="footer__brand">
        <a class="brand" href="/"><?php include __DIR__ . '/logo.php'; ?></a>
        <p><?= e($c['brand']['footer_text'] ?? '') ?></p>
        <?php $socials = array_filter($c['socials'] ?? [], fn($s) => trim($s['url'] ?? '') !== ''); if ($socials): ?>
        <nav class="footer__social" aria-label="Sosyal medya">
          <?php foreach ($socials as $s): ?><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['label']) ?>"><?= social_icon($s['label']) ?></a><?php endforeach; ?>
        </nav>
        <?php endif; ?>
      </div>
      <nav class="footer__col" aria-label="Etkinlikler">
        <p class="footer__title">Etkinlikler</p>
        <a href="/etkinlikler/">Yaklaşan etkinlikler</a>
        <a href="/takvim/">Etkinlik takvimi</a>
        <a href="/galeri/">Galeri</a>
        <a href="/mekanlar/">Mekanlar</a>
        <a href="/etkinlikler/?gecmis=1">Geçmiş etkinlikler</a>
      </nav>
      <nav class="footer__col" aria-label="Kurumsal">
        <p class="footer__title">İse Atölye</p>
        <a href="/hakkimizda/">Hakkımızda</a>
        <a href="/kurumsal/">Kurumsal etkinlikler</a>
        <?php if (isset($nav['/blog/'])): ?><a href="/blog/">Blog</a><?php endif; ?>
        <a href="/iletisim/">İletişim</a>
      </nav>
      <div class="footer__col">
        <p class="footer__title">İletişim</p>
        <?php if (trim($c['contact']['address'] ?? '') !== ''): ?><span><?= e($c['contact']['address']) ?></span><?php endif; ?>
        <?php if (trim($c['contact']['phone'] ?? '') !== ''): ?><a href="<?= e(phone_href($c['contact']['phone'])) ?>"><?= e($c['contact']['phone']) ?></a><?php endif; ?>
        <?php if (trim($c['contact']['email'] ?? '') !== ''): ?><a href="mailto:<?= e($c['contact']['email']) ?>"><?= e($c['contact']['email']) ?></a><?php endif; ?>
        <?php if (trim($c['contact']['hours'] ?? '') !== ''): ?><span class="muted"><?= e($c['contact']['hours']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="wrap footer__pay" aria-label="Ödeme yöntemleri">
      <img src="/assets/img/iyzico.png" alt="iyzico ile öde" width="56" height="24" loading="lazy">
      <img src="/assets/img/kartlar.png" alt="Mastercard, Visa, American Express, Troy" width="244" height="24" loading="lazy">
    </div>
    <div class="wrap footer__bottom">
      <span>© <?= date('Y') ?> <?= e($c['brand']['name'] ?? 'İSE ATÖLYE') ?>. Tüm hakları saklıdır.</span>
      <nav aria-label="Yasal"><a href="/gizlilik/">Gizlilik ve KVKK</a><a href="/katilim-kosullari/">Katılım koşulları</a><a href="/mesafeli-satis/">Mesafeli satış sözleşmesi</a><a class="footer__web" href="https://www.zaferyilmaz.com.tr/" target="_blank" rel="noopener" aria-label="Web: zaferyilmaz.com.tr">Web: <svg viewBox="0 0 300 300" width="16" height="16" aria-hidden="true"><path fill="#2dafe6" d="M299 26.54V213.39L140.72 213.58zM159.83 86.63L1.74 273.46H1V86.63z"/></svg></a></nav>
    </div>
  </footer>
  <?= $bodyEnd ?? '' ?>
  <script src="<?= e(asset('/assets/main.js')) ?>" defer></script>
  <script src="<?= e(asset('/assets/sayac.js')) ?>" defer></script>
</body>
</html>
