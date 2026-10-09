<?php
// Ön bilgilendirme formu ve mesafeli satış sözleşmesi. Satıcı bilgileri Panel > Ayarlar > Satıcı bilgileri bölümünden gelir.
require_once __DIR__ . '/inc/bootstrap.php';
$c = content();
$v = fn(string $k) => trim((string) setting($k, '')) ?: '—';
$ct = $c['contact'] ?? [];
$brand = $c['brand']['name'] ?? 'İSE ATÖLYE';
$site = preg_replace('#^https?://#', '', site_url(''));
require_once __DIR__ . '/inc/paytr.php';
$kartOn = paytr_on();
$pol = trim((string) setting('cancel_policy', ''));
$title = 'Ön bilgilendirme ve mesafeli satış sözleşmesi · ' . $brand;
$description = 'Etkinlik ve atölye katılım hizmetleri için ön bilgilendirme formu ve mesafeli satış sözleşmesi.';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head">
    <p class="label">Koşullar</p>
    <h1 class="display display--md">Ön bilgilendirme formu ve mesafeli satış sözleşmesi</h1>
  </section>
  <section class="wrap page-body"><div class="prose">
    <h2>1. Satıcı bilgileri</h2>
    <ul>
      <li><strong>Unvan:</strong> <?= e($v('seller_title')) ?></li>
      <li><strong>Adres:</strong> <?= e($v('seller_address')) ?></li>
      <li><strong>Vergi dairesi ve numarası:</strong> <?= e($v('seller_tax')) ?></li>
      <?php if (trim((string) setting('seller_mersis', '')) !== ''): ?><li><strong>MERSİS no:</strong> <?= e(setting('seller_mersis')) ?></li><?php endif; ?>
      <li><strong>Telefon:</strong> <?= e(trim($ct['phone'] ?? '') ?: '—') ?></li>
      <li><strong>E-posta:</strong> <?= e(trim($ct['email'] ?? '') ?: '—') ?></li>
      <?php if (trim((string) setting('seller_kep', '')) !== ''): ?><li><strong>KEP adresi:</strong> <?= e(setting('seller_kep')) ?></li><?php endif; ?>
      <li><strong>Web sitesi:</strong> <?= e($site) ?></li>
    </ul>

    <h2>2. Alıcı</h2>
    <p>Etkinlik kayıt formunda adı soyadı, e-posta adresi, telefonu ve fatura bilgileri yer alan kişidir (bundan sonra "Alıcı").</p>

    <h2>3. Sözleşmenin konusu</h2>
    <p>Bu sözleşme, Alıcı'nın <?= e($site) ?> üzerinden kaydını oluşturduğu etkinlik, atölye ya da kursa katılım hizmetinin satışına ilişkin olarak 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği hükümleri gereğince tarafların hak ve yükümlülüklerini düzenler.</p>

    <h2>4. Hizmetin temel nitelikleri ve fiyatı</h2>
    <p>Hizmetin adı, tarihi, saati, yeri, içeriği, bilet türü, kişi sayısı ve vergiler dahil toplam fiyatı etkinlik sayfasında ve kayıt formundaki özet bölümünde gösterilir; kayıt sonrasında Alıcı'ya e-posta ile de gönderilir. Bu bilgiler sözleşmenin ayrılmaz parçasıdır. Fiyatlara KDV dahildir; ek bir teslimat ya da hizmet bedeli alınmaz.</p>

    <h2>5. Ödeme</h2>
    <p>Ödeme, kayıt formunda sunulan yöntemlerle yapılır: banka havalesi / EFT<?php if ($kartOn): ?> ya da PayTR Ödeme ve Elektronik Para Kuruluşu A.Ş. altyapısıyla kredi veya banka kartı. Kartla ödemelerde kart bilgileri PayTR'nin 3D Secure korumalı güvenli ödeme sayfasında girilir; Satıcı kart bilgilerini görmez ve saklamaz<?php endif; ?>. Havale ile ödemede açıklamaya katılım kodunun yazılması gerekir. Ödemesi belirtilen sürede ulaşmayan kayıtlar kontenjandan düşebilir.</p>

    <h2>6. Hizmetin ifası</h2>
    <p>Hizmet, etkinlik sayfasında belirtilen tarih, saat ve yerde (çevrim içi etkinliklerde belirtilen bağlantı üzerinden) verilir. Kayıt onaylandığında Alıcı'ya katılım kodunu içeren bir e-posta gönderilir; bu kod etkinlik girişinde istenebilir.</p>

    <h2>7. Cayma hakkı</h2>
    <p>Mesafeli Sözleşmeler Yönetmeliği'nin 15. maddesinin (g) bendi uyarınca, belirli bir tarihte veya dönemde yapılması gereken boş zamanın değerlendirilmesine ilişkin hizmet sözleşmelerinde cayma hakkı kullanılamaz. Etkinlik ve atölye kayıtları bu kapsamdadır. Alıcı'nın kaydını iptal etmesi hâlinde aşağıdaki iptal ve iade koşulları uygulanır.</p>

    <h2>8. İptal ve iade koşulları</h2>
    <?php if ($pol !== ''): ?><p><?= nl2br(e($pol)) ?></p><?php endif; ?>
    <p>Etkinliğin özel iptal koşulları varsa etkinlik sayfasında ayrıca belirtilir. Satıcı'nın etkinliği iptal etmesi ya da hizmetin ifasının imkânsızlaşması hâlinde Alıcı en geç 3 gün içinde bilgilendirilir ve ödediği tutarın tamamı en geç 14 gün içinde, ödemenin yapıldığı yönteme uygun şekilde iade edilir. Kartla yapılan ödemelerin iadesi karta yapılır; iadenin hesaba yansıma süresi bankaya göre değişebilir. Alıcı, kaydını kayıt sayfasındaki "Kaydı iptal et" bağlantısıyla ya da yukarıdaki iletişim bilgilerinden iptal edebilir.</p>

    <h2>9. Fatura</h2>
    <p>Ödemesi alınan kayıtlar için kayıt formunda verilen bilgilerle e-Arşiv fatura düzenlenir ve Alıcı'nın e-posta adresine gönderilir.</p>

    <h2>10. Kişisel veriler</h2>
    <p>Alıcı'nın kişisel verileri <a href="/gizlilik/">Gizlilik ve KVKK aydınlatma metninde</a> açıklanan amaçlarla işlenir.</p>

    <h2>11. Uyuşmazlıkların çözümü</h2>
    <p>Bu sözleşmeden doğan uyuşmazlıklarda, Ticaret Bakanlığı'nca her yıl ilan edilen parasal sınırlar dahilinde Alıcı'nın veya Satıcı'nın yerleşim yerindeki tüketici hakem heyetleri, bu sınırları aşan durumlarda tüketici mahkemeleri yetkilidir.</p>

    <h2>12. Yürürlük</h2>
    <p>Alıcı, kayıt formunda bu metni okuduğunu ve kabul ettiğini onaylayıp kaydını oluşturduğunda, ön bilgilendirme yapılmış ve sözleşme kurulmuş sayılır. Sözleşmenin bir örneği kayıt e-postası ve bu sayfa aracılığıyla Alıcı'nın erişimine sunulur.</p>
  </div></section>
<?php include __DIR__ . '/inc/footer.php'; ?>
