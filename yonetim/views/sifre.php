<form method="post" class="narrow">
  <?= hidden($tok, 'sifre') ?>
  <section class="card">
    <h2>Panel şifresini değiştir</h2>
    <label>Mevcut şifre<input type="password" name="old" required autocomplete="current-password"></label>
    <label>Yeni şifre<input type="password" name="p1" minlength="8" required autocomplete="new-password"></label>
    <label>Yeni şifre (tekrar)<input type="password" name="p2" minlength="8" required autocomplete="new-password"></label>
    <p class="hint">Şifrenizi unutursanız cPanel Dosya Yöneticisi'nde <code>data/auth.php</code> dosyasını silin; panele ilk girişte yeni şifre belirlersiniz.</p>
  </section>
  <div class="actions"><button class="btn">Şifreyi değiştir</button></div>
</form>
