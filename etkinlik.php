<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/icons.php';
$c = content();
$ev = event_by_slug((string) ($_GET['slug'] ?? ''));
if (!$ev || !event_visible($ev)) { http_response_code(404); include __DIR__ . '/404.php'; exit; }

$me = current_user();
$sessions = event_sessions($ev);
$next = next_session($ev);
$cat = category($ev['category'] ?? '');
$state = event_state($ev);
$tickets = event_tickets($ev);
$myReg = $me ? user_has_reg($me['id'], $ev['id']) : null;
$interested = interested_users($ev['id']);
$iThink = $me && isset(interests_of($ev['id'])[$me['id']]);
$attendees = !empty($ev['show_attendees']) ? public_attendees($ev) : [];
$comments = event_comments($ev['id']);
$commentsOpen = ($ev['comments'] ?? true) !== false;
$attendedIds = attendee_ids($ev, true);
$instructors = array_values(array_filter(array_map('instructor', $ev['instructors'] ?? [])));
$venueList = event_venues($ev);
$past = $state === 'gecti';
$bookable = bookable_sessions($ev);
$flash = $me ? member_flash() : null;
$tok = $me ? member_csrf() : '';
$policy = trim($ev['cancel_policy'] ?? '') ?: (string) setting('cancel_policy', '');
$similar = array_slice(array_values(array_filter(upcoming_events(), fn($x) => $x['id'] !== $ev['id'] && ($x['category'] ?? '') === ($ev['category'] ?? ''))), 0, 3);
if (count($similar) < 3) $similar = array_slice(array_values(array_filter(upcoming_events(), fn($x) => $x['id'] !== $ev['id'])), 0, 3);
$joinUrl = event_url($ev) . 'katil/';

// Yorumları üst yorum + yanıtlar olarak grupla
$threads = []; $replies = [];
foreach ($comments as $cm) { if (($cm['parent'] ?? '') !== '') $replies[$cm['parent']][] = $cm; else $threads[] = $cm; }

$title = (trim($ev['seo_title'] ?? '') ?: $ev['title']) . ' · ' . $c['brand']['name'];
$description = trim($ev['summary'] ?? '') ?: excerpt($ev['body'] ?? '');
$image = $ev['cover'] ?: '/og.jpg';
$ld = [];
foreach ($sessions as $s) {
  $v = venue($s['venue'] ?? '');
  $online = ($v['type'] ?? '') === 'online';
  $ld[] = [
    '@context' => 'https://schema.org', '@type' => 'Event', 'name' => $ev['title'], 'description' => $description,
    'startDate' => date('c', session_ts($s)), 'endDate' => date('c', session_ts($s, true)),
    'eventStatus' => 'https://schema.org/' . (($ev['status'] === 'iptal' || ($s['status'] ?? '') === 'iptal') ? 'EventCancelled' : ($ev['status'] === 'ertelendi' ? 'EventPostponed' : 'EventScheduled')),
    'eventAttendanceMode' => 'https://schema.org/' . ($online ? 'OnlineEventAttendanceMode' : 'OfflineEventAttendanceMode'),
    'location' => $online ? ['@type' => 'VirtualLocation', 'url' => site_url(event_url($ev))] : ['@type' => 'Place', 'name' => $v['name'] ?? (trim($s['place'] ?? '') ?: 'Silivri'), 'address' => trim(implode(', ', array_filter([$v['address'] ?? '', $v['district'] ?? 'Silivri', $v['city'] ?? 'İstanbul'])))],
    'image' => [site_url($image)], 'url' => site_url(event_url($ev)),
    'organizer' => ['@type' => 'Organization', 'name' => $c['brand']['name'], 'url' => site_url('/')],
    'offers' => array_map(fn($t) => ['@type' => 'Offer', 'name' => $t['name'], 'price' => (float) $t['price'], 'priceCurrency' => 'TRY', 'url' => site_url($joinUrl), 'availability' => 'https://schema.org/' . (session_state($ev, $s) === 'dolu' ? 'SoldOut' : 'InStock')], $tickets),
  ] + ($instructors ? ['performer' => array_map(fn($i) => ['@type' => 'Person', 'name' => $i['name']], $instructors)] : []);
}
$headExtra = '<script type="application/ld+json">' . json_encode(count($ld) === 1 ? $ld[0] : $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
$ogType = 'article';
include __DIR__ . '/inc/header.php';

$cta = function () use ($ev, $state, $myReg, $joinUrl, $me, $bookable) {
  if ($myReg) return '<a class="btn btn--block" href="' . e(ticket_url($myReg)) . '">' . icon('bilet') . 'Kaydımı gör</a>';
  if ($state === 'acik') return '<a class="btn btn--block" href="' . e($joinUrl) . '">Etkinliğe katıl</a>';
  if ($state === 'dolu' && !empty($ev['waitlist'])) return '<a class="btn btn--block btn--ghost" href="' . e($joinUrl) . '">Yedek listeye yazıl</a>';
  return '<span class="btn btn--block btn--off" aria-disabled="true">' . e(state_label($state, $ev)) . '</span>';
};
?>
  <article class="event">
    <header class="wrap event__head">
      <a class="back" href="/etkinlikler/"><?= icon('sol') ?>Tüm etkinlikler</a>
      <?php if ($cat): ?><p class="label"><a href="/etkinlikler/?kategori=<?= e($cat['id']) ?>"><?= e($cat['name']) ?></a></p><?php endif; ?>
      <h1 class="display display--md"><?= e($ev['title']) ?></h1>
      <?php if (trim($ev['summary'] ?? '') !== ''): ?><p class="lead"><?= e($ev['summary']) ?></p><?php endif; ?>
      <?php if (in_array($ev['status'], ['iptal', 'ertelendi'], true)): ?><p class="notice notice--warn"><?= icon('bilgi') ?><?= $ev['status'] === 'iptal' ? 'Bu etkinlik iptal edildi. Kayıtlı katılımcılarla iletişime geçilecektir.' : 'Bu etkinlik ertelendi. Yeni tarih duyurulduğunda kayıtlı katılımcılara haber verilecektir.' ?></p><?php endif; ?>
      <?php if ($flash): ?><p class="notice notice--<?= e($flash[1]) ?>"><?= e($flash[0]) ?></p><?php endif; ?>
    </header>

    <div class="wrap event__grid">
      <div class="event__main">
        <?php if (!empty($ev['cover'])): ?>
          <figure class="event__cover"><img src="<?= e($ev['cover']) ?>" alt="<?= e($ev['title']) ?>" width="1600" height="1100" data-zoom></figure>
        <?php endif; ?>
        <?php if (!empty($ev['gallery'])): ?>
          <?php require_once __DIR__ . '/inc/media.php'; $gm = media_meta()['items']; ?><div class="event__gallery" data-zoom-group><?php foreach ($ev['gallery'] as $g): ?><button type="button" class="event__thumb" data-zoom-src="<?= e($g) ?>" data-zoom-cap="<?= e($gm[$g]['title'] ?? '') ?>"><img src="<?= e(thumb_url($g)) ?>" alt="<?= e($gm[$g]['title'] ?? '') ?>" loading="lazy"></button><?php endforeach; ?></div><?php if (count($ev['gallery']) > 8): ?><a class="link-more" href="/galeri/<?= e(rawurlencode($ev['slug'])) ?>/">Bütün fotoğrafları gör (<?= count($ev['gallery']) ?>)</a><?php endif; ?>
        <?php endif; ?>

        <div class="prose"><?= md($ev['body'] ?? '') ?></div>

        <?php if (count($sessions) > 1 || is_package($ev)): ?>
        <section class="event__block" id="tarihler" aria-labelledby="tarihler-baslik">
          <h2 class="h3" id="tarihler-baslik"><?= is_package($ev) ? 'Buluşmalar' : 'Tarihler ve mekanlar' ?></h2>
          <?php if (is_package($ev)): ?><p class="muted">Tek kayıtla <?= count($sessions) ?> buluşmanın tamamına katılırsınız.</p><?php endif; ?>
          <ul class="slist">
            <?php foreach ($sessions as $s): $st = session_state($ev, $s); $left = seats_left($ev, $s['id']); $v = venue($s['venue'] ?? ''); ?>
              <li class="slist__row<?= in_array($st, ['gecti', 'iptal'], true) ? ' is-off' : '' ?>">
                <span class="slist__date"><b><?= (int) date('j', strtotime($s['date'])) ?></b><?= TR_MONTHS_SHORT[(int) date('n', strtotime($s['date']))] ?></span>
                <span class="slist__info">
                  <strong><?= e(TR_DAYS[(int) date('w', strtotime($s['date']))]) ?> · <?= e(trim($s['start'] . ($s['end'] ? ' – ' . $s['end'] : ''))) ?><?= trim($s['note'] ?? '') !== '' ? ' · ' . e($s['note']) : '' ?></strong>
                  <span><?= icon('konum') ?><?php if ($v): ?><a href="<?= e(venue_url($v)) ?>"><?= e(venue_label($v)) ?></a><?php else: ?><?= e(session_place($s)) ?><?php endif; ?></span>
                </span>
                <?php if (!is_package($ev)): ?>
                <span class="slist__act">
                  <?php if ($st === 'acik'): ?>
                    <?php if (!empty($ev['show_left']) && $left !== null): ?><small><?= $left ?> kişilik yer</small><?php endif; ?>
                    <?php if (!$myReg): ?><a class="btn btn--sm" href="<?= e($joinUrl . '?oturum=' . rawurlencode($s['id'])) ?>">Katıl</a><?php endif; ?>
                  <?php elseif ($st === 'dolu'): ?><small>Kontenjan doldu</small><?php if (!empty($ev['waitlist']) && !$myReg): ?><a class="btn btn--ghost btn--sm" href="<?= e($joinUrl . '?oturum=' . rawurlencode($s['id'])) ?>">Yedek liste</a><?php endif; ?>
                  <?php else: ?><small><?= e(['gecti' => 'Tamamlandı', 'iptal' => 'İptal', 'kapali' => 'Kayıt kapandı'][$st] ?? '') ?></small><?php endif; ?>
                </span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
        <?php endif; ?>

        <?php if (array_filter($ev['includes'] ?? []) || array_filter($ev['bring'] ?? [])): ?>
        <section class="event__block event__lists">
          <?php if (array_filter($ev['includes'] ?? [])): ?><div><h2 class="h3">Ücrete dahil olanlar</h2><ul class="checks"><?php foreach ($ev['includes'] as $x): if (trim($x) === '') continue; ?><li><?= icon('tik') ?><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
          <?php if (array_filter($ev['bring'] ?? [])): ?><div><h2 class="h3">Yanınızda getirin</h2><ul class="checks checks--plain"><?php foreach ($ev['bring'] as $x): if (trim($x) === '') continue; ?><li><?= icon('arti') ?><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($instructors): ?>
        <section class="event__block" aria-labelledby="egitmen">
          <h2 class="h3" id="egitmen"><?= count($instructors) > 1 ? 'Eğitmenler' : 'Eğitmen' ?></h2>
          <div class="people">
            <?php foreach ($instructors as $i): ?>
              <div class="person">
                <?= avatar(['id' => $i['id'], 'name' => $i['name'], 'avatar' => $i['photo'] ?? ''], 'lg') ?>
                <div><strong><?= e($i['name']) ?></strong><?php if (trim($i['title'] ?? '') !== ''): ?><span class="muted"><?= e($i['title']) ?></span><?php endif; ?><?php if (trim($i['bio'] ?? '') !== ''): ?><p><?= e($i['bio']) ?></p><?php endif; ?><?php if (trim($i['instagram'] ?? '') !== ''): ?><a href="<?= e($i['instagram']) ?>" target="_blank" rel="noopener" class="link-more"><?= icon('instagram') ?>Instagram</a><?php endif; ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($venueList): ?>
        <section class="event__block" aria-labelledby="mekan">
          <h2 class="h3" id="mekan"><?= count($venueList) > 1 ? 'Mekanlar' : 'Mekan' ?></h2>
          <div class="vboxes">
            <?php foreach ($venueList as $v): $m = map_url($v); ?>
              <div class="vbox">
                <?php if (!empty($v['photo'])): ?><img src="<?= e($v['photo']) ?>" alt="" loading="lazy"><?php endif; ?>
                <div>
                  <strong><a href="<?= e(venue_url($v)) ?>"><?= e($v['name']) ?></a></strong>
                  <span class="muted"><?= e(trim(implode(', ', array_filter([$v['address'] ?? '', $v['district'] ?? '', $v['city'] ?? ''])))) ?></span>
                  <?php if (trim($v['note'] ?? '') !== ''): ?><p><?= e($v['note']) ?></p><?php endif; ?>
                  <?php if ($m !== ''): ?><a class="link-more" href="<?= e($m) ?>" target="_blank" rel="noopener"><?= icon('konum') ?>Haritada aç</a><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($ev['faq'])): ?>
        <section class="event__block" aria-labelledby="sss">
          <h2 class="h3" id="sss">Sık sorulanlar</h2>
          <div class="faq"><?php foreach ($ev['faq'] as $f): if (trim($f['q'] ?? '') === '') continue; ?><details><summary><?= e($f['q']) ?></summary><p><?= nl2br(e($f['a'] ?? '')) ?></p></details><?php endforeach; ?></div>
        </section>
        <?php endif; ?>

        <?php if ($policy !== '' && !$past): ?>
        <section class="event__block">
          <h2 class="h3">İptal ve iade</h2>
          <p class="muted"><?= nl2br(e($policy)) ?> <a class="link" href="/katilim-kosullari/">Katılım koşulları</a></p>
        </section>
        <?php endif; ?>

        <section class="event__block community" id="katilimcilar" aria-labelledby="kimler">
          <h2 class="h3" id="kimler">Kimler geliyor?</h2>
          <div class="community__grid">
            <div class="community__col">
              <p class="label">Katılan üyeler · <?= count($attendees) ?></p>
              <?php if ($attendees): ?><div class="faces"><?php foreach (array_slice($attendees, 0, 18) as $u): ?><a href="<?= e(user_url($u)) ?>" title="<?= e($u['name']) ?>"><?= avatar($u, 'sm') ?></a><?php endforeach; ?><?php if (count($attendees) > 18): ?><span class="faces__more">+<?= count($attendees) - 18 ?></span><?php endif; ?></div>
              <?php else: ?><p class="muted small"><?= $past ? 'Katılımcı listesi gizli.' : 'İlk katılan siz olun.' ?></p><?php endif; ?>
            </div>
            <div class="community__col" data-interest-box>
              <p class="label">Katılmayı düşünüyor · <span data-interest-count><?= count($interested) ?></span></p>
              <div class="faces" data-interest-faces><?php foreach (array_slice($interested, 0, 18) as $u): ?><a href="<?= e(user_url($u)) ?>" title="<?= e($u['name']) ?>"<?= $me && $u['id'] === $me['id'] ? ' data-me' : '' ?>><?= avatar($u, 'sm') ?></a><?php endforeach; ?></div>
              <?php if (!$past && !$myReg): ?>
                <?php if ($me): ?>
                  <form method="post" action="/uye-api.php" data-interest>
                    <input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="ilgi"><input type="hidden" name="event" value="<?= e($ev['id']) ?>">
                    <button class="chip<?= $iThink ? ' is-on' : '' ?>" type="submit" aria-pressed="<?= $iThink ? 'true' : 'false' ?>" data-me-avatar="<?= e(avatar($me, 'sm')) ?>" data-me-url="<?= e(user_url($me)) ?>"><?= icon('yildiz') ?><span><?= $iThink ? 'Düşünüyorum' : 'Katılmayı düşünüyorum' ?></span></button>
                  </form>
                  <p class="muted small">Kesin kayıt değildir; ilgilendiğinizi diğer katılımcılara gösterir.</p>
                <?php else: ?>
                  <a class="chip" href="/giris/?donus=<?= e(rawurlencode(event_url($ev) . '#katilimcilar')) ?>"><?= icon('yildiz') ?><span>Katılmayı düşünüyorum</span></a>
                  <p class="muted small">İşaretlemek için <a href="/giris/?donus=<?= e(rawurlencode(event_url($ev))) ?>">giriş yapın</a> ya da <a href="/uye-ol/?donus=<?= e(rawurlencode(event_url($ev))) ?>">üye olun</a>.</p>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <section class="event__block comments" id="yorumlar" aria-labelledby="yorum-baslik">
          <h2 class="h3" id="yorum-baslik">Yorumlar<?php if ($comments): ?> <span class="count"><?= count($comments) ?></span><?php endif; ?></h2>
          <?php if ($threads): ?>
          <ol class="clist">
            <?php foreach ($threads as $cm) { $sub = $replies[$cm['id']] ?? []; include __DIR__ . '/inc/comment.php'; } ?>
          </ol>
          <?php else: ?><p class="muted">Henüz yorum yok. <?= $past ? 'Katıldıysanız deneyiminizi paylaşın.' : 'Merak ettiklerinizi sorun ya da düşüncelerinizi paylaşın.' ?></p><?php endif; ?>
          <?php if ($commentsOpen): ?>
            <?php if ($me): ?>
              <form class="cform" method="post" action="/uye-api.php" data-comment>
                <input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="yorum"><input type="hidden" name="event" value="<?= e($ev['id']) ?>"><input type="hidden" name="parent" value="">
                <div class="cform__who"><?= avatar($me, 'sm') ?><span><strong><?= e($me['name']) ?></strong><span class="muted small" data-reply-to hidden></span></span></div>
                <label><span class="sr">Yorumunuz</span><textarea name="text" rows="3" maxlength="1500" required placeholder="<?= $past ? 'Etkinlik nasıldı?' : 'Bir soru sorun ya da düşüncenizi yazın' ?>"></textarea></label>
                <div class="cform__foot"><button class="btn btn--sm" type="submit">Gönder</button><button class="btn btn--ghost btn--sm" type="button" data-reply-cancel hidden>Yanıtı iptal et</button><p class="cform__msg" role="status" data-form-msg></p></div>
              </form>
            <?php else: ?>
              <p class="cform cform--guest"><?= icon('yorum') ?><span>Yorum yazmak için <a href="/giris/?donus=<?= e(rawurlencode(event_url($ev) . '#yorumlar')) ?>">giriş yapın</a> ya da <a href="/uye-ol/?donus=<?= e(rawurlencode(event_url($ev) . '#yorumlar')) ?>">ücretsiz üye olun</a>.</span></p>
            <?php endif; ?>
          <?php else: ?><p class="muted small">Bu etkinlik yorumlara kapalı.</p><?php endif; ?>
        </section>
      </div>

      <aside class="event__side">
        <div class="book">
          <div class="book__price"><span class="label">Katılım</span><strong><?= e(price_label($ev)) ?></strong></div>
          <ul class="book__meta">
            <li><?= icon('takvim') ?><span><?= e($next ? tr_date($next['date'], true) : 'Tarih duyurulacak') ?><?php if (count(event_sessions($ev, false)) > 1): ?><small><?= is_package($ev) ? count($sessions) . ' buluşma · ' . tr_date_short($sessions[0]['date']) . ' – ' . tr_date_short(end($sessions)['date']) : count($sessions) . ' farklı tarih' ?></small><?php endif; ?></span></li>
            <?php if ($next && $next['start']): ?><li><?= icon('saat') ?><span><?= e($next['start'] . ($next['end'] ? ' – ' . $next['end'] : '')) ?><?php if (trim($ev['duration'] ?? '') !== ''): ?><small><?= e($ev['duration']) ?></small><?php endif; ?></span></li><?php endif; ?>
            <li><?= icon('konum') ?><span><?= count($venueList) > 1 ? count($venueList) . ' farklı mekan' : e(session_place($next)) ?><?php if (count($venueList) > 1): ?><small><?= e(implode(', ', array_column($venueList, 'name'))) ?></small><?php endif; ?></span></li>
            <?php if (($ev['level'] ?? '') !== '' || trim($ev['age'] ?? '') !== ''): ?><li><?= icon('kisi') ?><span><?= e(implode(' · ', array_filter([LEVELS[$ev['level'] ?? ''] ?? '', trim($ev['age'] ?? '') !== '' ? $ev['age'] : '']))) ?></span></li><?php endif; ?>
          </ul>
          <?php if (count($tickets) > 1): ?>
            <ul class="book__tickets"><?php foreach ($tickets as $t): $sale = ticket_on_sale($t); ?><li<?= !$sale ? ' class="is-off"' : '' ?>><span><?= e($t['name']) ?><?php if (trim($t['note'] ?? '') !== ''): ?><small><?= e($t['note']) ?></small><?php endif; ?></span><b><?= (float) $t['price'] > 0 ? money($t['price']) : 'Ücretsiz' ?></b></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?php $hint = seats_hint($ev); if ($state === 'acik' && $hint !== ''): ?><p class="book__left"><?= icon('kisiler') ?><?= e($hint) ?></p><?php endif; ?>
          <?php if ($myReg): ?><p class="book__mine"><?= icon('onay') ?><span>Kaydınız var · <b><?= e($myReg['code']) ?></b><small><?= e(REG_STATUS[$myReg['status']] ?? '') ?></small></span></p><?php endif; ?>
          <?= $cta() ?>
          <?php if (!$myReg && $state !== 'acik' && $state !== 'dolu'): ?><p class="muted small center"><?= e(state_label($state, $ev)) ?></p><?php elseif ($state === 'dolu'): ?><p class="muted small center"><?= e(state_label($state, $ev)) ?></p><?php endif; ?>
          <?php if (!$me && in_array($state, ['acik', 'dolu'], true) && setting('require_login', true)): ?><p class="muted small center">Katılım için ücretsiz üyelik gerekir.</p><?php endif; ?>
          <div class="book__links">
            <?php if ($next && !$past): ?><a href="<?= e(event_url($ev) . 'takvim.ics?o=' . rawurlencode($next['id'])) ?>"><?= icon('indir') ?>Takvime ekle</a><a href="<?= e(gcal_url($ev, $next)) ?>" target="_blank" rel="noopener"><?= icon('takvim') ?>Google Takvim</a><?php endif; ?>
            <button type="button" data-share data-title="<?= e($ev['title']) ?>" data-url="<?= e(site_url(event_url($ev))) ?>"><?= icon('paylas') ?>Paylaş</button>
          </div>
        </div>
        <?php if (trim($c['contact']['whatsapp'] ?? '') !== ''): ?><a class="side-help" href="<?= e(wa_href($c['contact']['whatsapp'], $ev['title'] . ' hakkında bilgi almak istiyorum.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><span>Sorunuz mu var?<small>WhatsApp'tan yazın</small></span></a><?php endif; ?>
      </aside>
    </div>
  </article>

  <?php if ($similar): ?>
  <section class="section wrap" aria-labelledby="benzer">
    <div class="section-head"><div><p class="label">Bunlar da ilginizi çekebilir</p><h2 class="h2" id="benzer">Diğer etkinlikler</h2></div><a class="link-more" href="/etkinlikler/">Tümü<?= icon('sag') ?></a></div>
    <div class="egrid"><?php foreach ($similar as $ev2) { $evBak = $ev; $ev = $ev2; include __DIR__ . '/inc/event-card.php'; $ev = $evBak; } ?></div>
  </section>
  <?php endif; ?>

  <?php if (!$past && in_array($state, ['acik', 'dolu'], true) && !$myReg): ?>
  <div class="mbar"><div><strong><?= e(price_short($ev)) ?></strong><span><?= e($next ? tr_date_short($next['date']) . ($next['start'] ? ' · ' . $next['start'] : '') : '') ?></span></div><?= str_replace('btn--block', '', $cta()) ?></div>
  <?php endif; ?>
<?php include __DIR__ . '/inc/footer.php'; ?>
