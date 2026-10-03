<?php
// KVKK aydınlatma metni.
require_once __DIR__ . '/inc/bootstrap.php';
$c = content();
$title = 'Gizlilik ve KVKK · ' . $c['brand']['name'];
$description = 'Üyelik, etkinlik kayıtları ve site ziyaretleriyle ilgili hangi bilgilerin, neden ve ne kadar süre tutulduğu.';
$mail = $c['contact']['email'] ?? '';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head">
    <p class="label">Gizlilik</p>
    <h1 class="display display--md">Kişisel verileriniz</h1>
  </section>
  <section class="wrap page-body">
    <div class="prose">
      <p>Bu metin, 6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında sizi bilgilendirmek içindir. Veri sorumlusu <?= e($c['brand']['name']) ?>'dir.</p>
      <h2>Üyelik ve etkinlik kayıtları</h2>
      <ul>
        <li>Üye olurken: ad soyad, e-posta, isteğe bağlı telefon ve şifrenizin geri çevrilemez özeti (şifrenin kendisi saklanmaz)</li>
        <li>Profilinize eklerseniz: fotoğraf, şehir, kısa tanıtım, Instagram kullanıcı adı</li>
        <li>Etkinliğe katılırken: seçtiğiniz etkinlik ve tarih, bilet türü, kişi sayısı, telefon, notunuz, ödeme durumu</li>
        <li>Şirket adına fatura isterseniz: şirket unvanı, vergi dairesi, vergi numarası ya da T.C. kimlik numarası ve fatura adresi. Bu bilgiler yalnızca fatura düzenlemek için kullanılır ve vergi mevzuatının öngördüğü süre boyunca saklanır.</li>
        <li>Yorumlarınız ve "katılmayı düşünüyorum" işaretleriniz</li>
      </ul>
      <p>Bu bilgiler etkinlik kaydınızı oluşturmak, sizinle etkinlik hakkında iletişim kurmak ve topluluk özelliklerini (profil, yorum, katılımcı listesi) sunmak için, sözleşmenin kurulması ve ifası ile meşru menfaat kapsamında işlenir. Adınız, profil fotoğrafınız, yorumlarınız ve düşündüğünüz etkinlikler diğer ziyaretçilere görünür. Katıldığınız etkinliklerin görünmesini profil ayarlarından kapatabilirsiniz. E-posta ve telefonunuz hiçbir zaman herkese açık gösterilmez.</p>
      <h2>İletişim formları</h2>
      <p>Formla gönderdiğiniz ad, e-posta, telefon ve mesaj yalnızca talebinize dönüş yapmak için kullanılır.</p>
      <h2>Ziyaret istatistikleri</h2>
      <ul>
        <li>Açtığınız sayfa, ziyaret zamanı ve sayfada kaldığınız süre</li>
        <li>Siteye geldiğiniz site (örneğin Google ya da Instagram) ve cihaz türü</li>
        <li>IP adresinizin son kısmı gizlenmiş hali (örneğin 88.241.12.x) ve buna göre tahmin edilen şehir ve ülke</li>
      </ul>
      <p>Tam IP adresiniz kaydedilmez, reklam ve profil çıkarma yapılmaz. Gizlenmiş IP adresi 30 gün sonra silinir; isimsiz sayılar en fazla 13 ay saklanır.</p>
      <h2>Çerezler</h2>
      <p>Yalnızca sitenin çalışması için zorunlu çerezler kullanılır: giriş yaptığınızda oturumunuzu ve "beni hatırla" seçeneğini tutan çerezler, beğeni çerezi. Reklam ya da takip çerezi kullanılmaz.</p>
      <h2>Saklama, aktarım ve güvenlik</h2>
      <p>Bilgiler sitenin kendi sunucusunda saklanır, üçüncü kişilerle paylaşılmaz ve yurt dışına aktarılmaz. Ödemeler sitede alınmaz; havale ya da ödeme bağlantısıyla yaptığınız ödemelerde ilgili bankanın ya da ödeme kuruluşunun koşulları geçerlidir. Üyeliğinizi dilediğiniz zaman Hesabım &gt; Hesap ve güvenlik bölümünden silebilirsiniz; geçmiş katılım kayıtları muhasebe yükümlülükleri için kimliğinizden ayrılarak saklanır.</p>
      <h2>Haklarınız</h2>
      <p>KVKK'nın 11. maddesindeki haklarınızla ilgili sorularınız ve talepleriniz için <?php if ($mail): ?><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a><?php else: ?>iletişim sayfasındaki adres<?php endif; ?> üzerinden bize yazabilirsiniz.</p>
    </div>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
