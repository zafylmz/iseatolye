# İSE ATÖLYE · www.iseatolye.com.tr

Atölye ve etkinlik sitesi: etkinlikler, oturumlar, kontenjan, bilet, üyelik, yorumlar, galeri, blog ve yönetim paneli (`/yonetim/`).

- Düz PHP 8 (çerçeve yok). İçerik JSON dosyalarında, üyeler ve kayıtlar MySQL'de (panelden bağlanır).
- Kurulum ve kullanım: [KURULUM.txt](KURULUM.txt)

## Depoda olmayanlar

Canlı sunucudaki `data/` (üyeler, kayıtlar, ayarlar, veritabanı ve e-posta bağlantı bilgileri) ve `uploads/` (yüklenen fotoğraflar) klasörleri depoya girmez. Canlı sunucu bu verilerin tek kaynağıdır. Güncelleme yüklerken bu iki klasörün üzerine asla yazılmaz.

Sıfırdan kurulumda ilk içerik `kurulum/ilk-veri/` klasöründen `data/` içine kopyalanır.
