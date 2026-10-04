<?php
// Ay takvimi. $mode: 'full' (takvim sayfası ve açılır pencere) ya da 'mini' (ana sayfa).
require_once __DIR__ . '/events.php';
require_once __DIR__ . '/media.php';

// Takvim öğesinin görseli: etkinliğin kapak görselinin küçük kopyası; görsel yoksa sade bir yer tutucu.
function cal_img(array $ev, string $cls = 'cal__img'): string {
  $src = (string) ($ev['cover'] ?? '');
  if ($src === '') return '<span class="' . $cls . ' is-empty" aria-hidden="true">' . icon('nilufer') . '</span>';
  return '<span class="' . $cls . '" aria-hidden="true"><img src="' . e(thumb_url($src)) . '" alt="" width="720" height="540" loading="lazy" decoding="async"></span>';
}

function cal_ym(?string $s): array {
  if ($s && preg_match('/^(\d{4})-(\d{2})$/', $s, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) return [(int) $m[1], (int) $m[2]];
  return [(int) date('Y'), (int) date('n')];
}

function cal_shift(int $y, int $m, int $d): string {
  $t = mktime(0, 0, 0, $m + $d, 1, $y);
  return date('Y-m', $t);
}

function calendar_month(int $y, int $m, string $mode = 'full'): string {
  $items = month_items($y, $m);
  $first = mktime(0, 0, 0, $m, 1, $y);
  $days = (int) date('t', $first);
  $lead = ((int) date('N', $first)) - 1; // Pazartesi başlar
  $today = date('Y-m-d');
  $ym = sprintf('%04d-%02d', $y, $m);
  $prev = cal_shift($y, $m, -1); $next = cal_shift($y, $m, 1);
  $title = TR_MONTHS[$m] . ' ' . $y;
  $cells = (int) ceil(($lead + $days) / 7) * 7;
  ob_start();
  if ($mode === 'mini'): ?>
    <div class="mcal" data-ym="<?= $ym ?>">
      <div class="mcal__head"><span class="mcal__title"><?= e($title) ?></span><span class="mcal__open"><?= icon('izgara') ?>Büyük takvim</span></div>
      <div class="mcal__grid" aria-hidden="true">
        <?php foreach (['Pt', 'Sa', 'Ça', 'Pe', 'Cu', 'Ct', 'Pz'] as $w): ?><span class="mcal__w"><?= $w ?></span><?php endforeach; ?>
        <?php for ($i = 0; $i < $cells; $i++): $d = $i - $lead + 1; if ($d < 1 || $d > $days): ?><span></span><?php continue; endif; $date = sprintf('%s-%02d', $ym, $d); $has = !empty($items[$date]); ?>
          <span class="mcal__d<?= $has ? ' has' : '' ?><?= $date === $today ? ' is-today' : '' ?><?= $date < $today ? ' is-past' : '' ?>"><?= $d ?></span>
        <?php endfor; ?>
      </div>
      <p class="sr">Bu ay <?= array_sum(array_map('count', $items)) ?> etkinlik var. Büyük takvimi açmak için dokunun.</p>
    </div>
  <?php else: ?>
    <div class="cal" data-cal data-ym="<?= $ym ?>">
      <div class="cal__head">
        <h2 class="cal__title" aria-live="polite"><?= e($title) ?></h2>
        <div class="cal__nav">
          <a class="btn btn--ghost btn--icon" href="/takvim/?ay=<?= $prev ?>" data-cal-go="<?= $prev ?>" aria-label="Önceki ay"><?= icon('geri') ?></a>
          <?php if ($ym !== date('Y-m')): ?><a class="btn btn--ghost btn--sm" href="/takvim/" data-cal-go="<?= date('Y-m') ?>">Bu ay</a><?php endif; ?>
          <a class="btn btn--ghost btn--icon" href="/takvim/?ay=<?= $next ?>" data-cal-go="<?= $next ?>" aria-label="Sonraki ay"><?= icon('ileri') ?></a>
        </div>
      </div>
      <div class="cal__grid">
        <?php foreach (['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'] as $w): ?><div class="cal__w"><span class="cal__wl"><?= $w ?></span><span class="cal__ws"><?= mb_substr($w, 0, 3) ?></span></div><?php endforeach; ?>
        <?php for ($i = 0; $i < $cells; $i++): $d = $i - $lead + 1;
          if ($d < 1 || $d > $days): ?><div class="cal__day is-out"></div><?php continue; endif;
          $date = sprintf('%s-%02d', $ym, $d); $list = $items[$date] ?? []; ?>
          <div class="cal__day<?= $list ? ' has' : '' ?><?= $date === $today ? ' is-today' : '' ?><?= $date < $today ? ' is-past' : '' ?>">
            <span class="cal__num"><?= $d ?></span>
            <?php if ($list): ?><ul class="cal__evs">
              <?php foreach ($list as [$ev, $s]): $cancel = ($s['status'] ?? '') === 'iptal' || ($ev['status'] ?? '') === 'iptal'; ?>
                <li><a class="cal__ev<?= $cancel ? ' is-cancel' : '' ?>" href="<?= e(event_url($ev)) ?>" title="<?= e($ev['title'] . ' · ' . session_when($s, false)) ?>"><?= cal_img($ev) ?><span class="cal__tx"><?php if ($s['start'] ?? ''): ?><time><?= e($s['start']) ?></time><?php endif; ?><span class="cal__tt"><?= e($ev['title']) ?></span></span></a></li>
              <?php endforeach; ?>
            </ul><?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
      <div class="cal__list">
        <p class="label"><?= e($title) ?> etkinlikleri</p>
        <?php if (!$items): ?><p class="muted">Bu ay için henüz planlanmış etkinlik yok.</p><?php else: ?>
        <ol>
          <?php foreach ($items as $date => $list) foreach ($list as [$ev, $s]): $v = venue($s['venue'] ?? ''); $cancel = ($s['status'] ?? '') === 'iptal' || ($ev['status'] ?? '') === 'iptal'; ?>
            <li><a href="<?= e(event_url($ev)) ?>"<?= $date < $today ? ' class="is-past"' : '' ?>>
              <span class="cal__ld"><b><?= (int) substr($date, 8) ?></b><?= TR_DAYS_SHORT[(int) date('w', strtotime($date))] ?></span>
              <?= cal_img($ev, 'cal__lim') ?>
              <span class="cal__lt"><strong><?= e($ev['title']) ?><?= $cancel ? ' · İptal' : '' ?></strong><span><?= e(trim(($s['start'] ?? '') . (($s['end'] ?? '') ? ' – ' . $s['end'] : ''))) ?><?= ' · ' . e(session_place($s, false)) ?></span></span>
            </a></li>
          <?php endforeach; ?>
        </ol>
        <?php endif; ?>
      </div>
    </div>
  <?php endif;
  return (string) ob_get_clean();
}
