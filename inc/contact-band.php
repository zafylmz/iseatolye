<?php $cc = $c['contact'] ?? []; ?>
<section class="wrap contact-band-wrap" aria-labelledby="iletisim-baslik">
  <div class="contact-band" data-reveal>
    <div>
      <p class="label">İletişim</p>
      <h2 class="h2" id="iletisim-baslik"><?= e($cc['heading'] ?? '') ?></h2>
      <p class="muted"><?= e(trim(($cc['address'] ?? '') . (($cc['hours'] ?? '') ? ' · ' . $cc['hours'] : ''))) ?></p>
    </div>
    <div class="contact-band__act">
      <?php if (trim($cc['whatsapp'] ?? '') !== ''): ?><a class="btn" href="<?= e(wa_href($cc['whatsapp'])) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?>WhatsApp'tan yazın</a><?php endif; ?>
      <?php if (trim($cc['phone'] ?? '') !== ''): ?><a class="btn btn--ghost" href="<?= e(phone_href($cc['phone'])) ?>"><?= icon('telefon') ?><?= e($cc['phone']) ?></a><?php endif; ?>
      <?php if (trim($cc['email'] ?? '') !== ''): ?><a class="btn btn--ghost" href="mailto:<?= e($cc['email']) ?>"><?= icon('posta') ?><?= e($cc['email']) ?></a><?php endif; ?>
    </div>
  </div>
</section>
