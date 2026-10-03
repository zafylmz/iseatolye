<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$k = $c['corporate'];
$title = 'Kurumsal · ' . $c['brand']['name'];
$description = 'Kurumlar için iyi oluş, ekip içi bağ kurma ve yaratıcılık odaklı atölyeler. Ülker, Starbucks, B/S/H gibi markalarla iş birlikleri.';
include __DIR__ . '/inc/header.php';
?>
  <section class="wrap page-head page-head--split">
    <div>
      <p class="label"><?= e($k['title'] ?? 'Kurumsal') ?></p>
      <h1 class="display display--md"><?= e($k['lead'] ?? '') ?></h1>
      <div class="actions"><a class="btn" href="/iletisim/?konu=kurumsal#form">Teklif isteyin</a><?php if (trim($c['contact']['whatsapp'] ?? '') !== ''): ?><a class="btn btn--ghost" href="<?= e(wa_href($c['contact']['whatsapp'], 'Merhaba, kurumsal atölye hakkında bilgi almak istiyorum.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?>WhatsApp</a><?php endif; ?></div>
    </div>
    <?php if (!empty($k['image'])): ?><div class="page-head__img"><img src="<?= e($k['image']) ?>" alt="" width="1200" height="900"></div><?php endif; ?>
  </section>

  <section class="wrap section section--tight">
    <div class="split">
      <div class="prose prose--lead"><?= paragraphs($k['body'] ?? '') ?></div>
      <?php if (!empty($k['brands'])): ?>
        <aside class="brands"><p class="label">İş birliği yaptığımız markalardan bazıları</p><ul><?php foreach ($k['brands'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul></aside>
      <?php endif; ?>
    </div>
  </section>

  <section class="section section--sand">
    <div class="wrap">
      <div class="section-head" data-reveal><div><p class="label">Neler kazandırır?</p><h2 class="h2"><?= e($k['services_title'] ?? 'Hizmet alanları') ?></h2></div></div>
      <div class="features">
        <?php foreach ($k['services'] ?? [] as $s): ?>
          <article class="feature" data-reveal><span class="feature__ico"><?= icon($s['icon'] ?? 'yaprak') ?></span><h3 class="h3"><?= e($s['title']) ?></h3><p><?= e($s['text']) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if (!empty($k['process'])): ?>
  <section class="section wrap">
    <div class="section-head" data-reveal><div><p class="label">Süreç</p><h2 class="h2"><?= e($k['process_title'] ?? 'Nasıl çalışıyoruz?') ?></h2></div></div>
    <ol class="steps"><?php foreach ($k['process'] as $i => $s): ?><li data-reveal><span class="steps__n"><?= sprintf('%02d', $i + 1) ?></span><h3><?= e($s['title']) ?></h3><p><?= e($s['text']) ?></p></li><?php endforeach; ?></ol>
  </section>
  <?php endif; ?>

  <section class="section wrap section--line contact-grid" id="teklif">
    <div class="contact-info">
      <p class="label">Kurumsal iletişim</p>
      <h2 class="h2">Ekibiniz için bir atölye planlayalım</h2>
      <ul class="cinfo">
        <?php if (trim($k['contact_name'] ?? '') !== ''): ?><li><?= icon('kisi') ?><span><span class="label">Yetkili</span><?= e($k['contact_name']) ?></span></li><?php endif; ?>
        <?php if (trim($k['contact_phone'] ?? '') !== ''): ?><li><?= icon('telefon') ?><span><span class="label">Telefon</span><a href="<?= e(phone_href($k['contact_phone'])) ?>"><?= e($k['contact_phone']) ?></a></span></li><?php endif; ?>
        <?php if (trim($k['contact_email'] ?? '') !== ''): ?><li><?= icon('posta') ?><span><span class="label">E-posta</span><a href="mailto:<?= e($k['contact_email']) ?>"><?= e($k['contact_email']) ?></a></span></li><?php endif; ?>
      </ul>
    </div>
    <?php $formTopic = 'kurumsal'; include __DIR__ . '/inc/contact-form.php'; ?>
  </section>
<?php include __DIR__ . '/inc/footer.php'; ?>
