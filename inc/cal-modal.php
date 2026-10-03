<?php // Büyük takvim açılır penceresi: içerik /takvim/?parca=1 adresinden yüklenir. ?>
<dialog class="cal-modal" id="takvim-penceresi" aria-labelledby="takvim-penceresi-baslik">
  <div class="cal-modal__box">
    <div class="cal-modal__top">
      <p class="label" id="takvim-penceresi-baslik">Etkinlik takvimi</p>
      <div class="cal-modal__end"><a href="/takvim/" class="cal-modal__page">Takvim sayfası<?= icon('sag') ?></a><button type="button" class="btn btn--ghost btn--icon" data-cal-close aria-label="Takvimi kapat"><?= icon('kapat') ?></button></div>
    </div>
    <div class="cal-modal__body" data-cal-body><?= calendar_month(...cal_ym($calYm ?? null)) ?></div>
  </div>
</dialog>
