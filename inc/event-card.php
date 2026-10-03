<?php
/** @var array $ev */
$__b = date_badge($ev);
$__s = next_session($ev);
$__v = venue($__s['venue'] ?? '');
$__vs = event_venues($ev);
$__cat = category($ev['category'] ?? '');
$__st = event_state($ev);
$__hint = seats_hint($ev);
$__int = count(interests_of($ev['id']));
?>
<article class="ecard<?= in_array($__st, ['iptal', 'gecti'], true) ? ' ecard--muted' : '' ?>" data-reveal>
  <a class="ecard__media" href="<?= e(event_url($ev)) ?>" tabindex="-1" aria-hidden="true">
    <?php if (!empty($ev['cover'])): ?><img src="<?= e($ev['cover']) ?>" alt="" width="1200" height="900" loading="lazy" decoding="async"><?php endif; ?>
    <span class="ecard__date"><b><?= e($__b['day']) ?></b><span><?= e($__b['mon']) ?></span></span>
    <?php if (in_array($__st, ['dolu', 'iptal', 'ertelendi'], true)): ?><span class="ecard__flag"><?= e(['dolu' => 'Kontenjan doldu', 'iptal' => 'İptal edildi', 'ertelendi' => 'Ertelendi'][$__st]) ?></span><?php elseif ($__hint !== ''): ?><span class="ecard__flag ecard__flag--soft"><?= e($__hint) ?></span><?php endif; ?>
  </a>
  <div class="ecard__body">
    <?php if ($__cat): ?><p class="label"><?= e($__cat['name']) ?></p><?php endif; ?>
    <h3 class="ecard__title"><a href="<?= e(event_url($ev)) ?>"><?= e($ev['title']) ?></a></h3>
    <ul class="ecard__meta">
      <li><?= icon('saat') ?><?= $__s ? e(TR_DAYS[(int) date('w', strtotime($__s['date']))] . ($__s['start'] ? ' · ' . $__s['start'] . ($__s['end'] ? ' – ' . $__s['end'] : '') : '')) : 'Tarih duyurulacak' ?><?php $__n = count(event_sessions($ev, false)); if ($__n > 1): ?> <span class="muted">· <?= is_package($ev) ? $__n . ' buluşma' : '+' . ($__n - 1) . ' tarih' ?></span><?php endif; ?></li>
      <li><?= icon(($__v['type'] ?? '') === 'online' ? 'cevrimici' : 'konum') ?><?= count($__vs) > 1 ? count($__vs) . ' farklı mekan' : e(session_place($__s)) ?></li>
    </ul>
    <div class="ecard__foot">
      <span class="ecard__price"><?= e(price_short($ev)) ?></span>
      <?php if ($__int > 0): ?><span class="ecard__int"><?= $__int ?> kişi düşünüyor</span><?php endif; ?>
    </div>
  </div>
</article>
