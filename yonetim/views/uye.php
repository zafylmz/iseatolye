<?php
/** @var array $regs */ /** @var string $tok */
$u = user_by_id((string) ($_GET['id'] ?? ''));
if (!$u): ?><section class="card"><p class="hint">Üye bulunamadı. <a href="./?s=uyeler">Üyelere dönün.</a></p></section><?php return; endif;
$mine = array_values(array_filter($regs, fn($r) => ($r['user'] ?? '') === $u['id']));
usort($mine, fn($a, $b) => strcmp($b['created'], $a['created']));
$cms = [];
foreach (json_read(COMMENTS_FILE) as $eid => $list) foreach ($list as $cm) if (($cm['user'] ?? '') === $u['id']) $cms[] = ['eid' => $eid] + $cm;
usort($cms, fn($a, $b) => strcmp($b['date'], $a['date']));
$thinks = array_values(array_filter(array_map('event_by_id', array_keys(user_interests($u['id'])))));
$active = ($u['status'] ?? 'aktif') === 'aktif';
?>
<div class="bar">
  <div><a class="back" href="./?s=uyeler">← Üyeler</a><h1 class="person person--lg"><?= avatar($u) ?><span><?= e($u['name']) ?><small>@<?= e($u['username']) ?></small></span></h1></div>
  <div class="bar__act"><?php if ($active): ?><a class="btn btn--ghost" href="<?= e(user_url($u)) ?>" target="_blank" rel="noopener">Profili gör ↗</a><?php endif; ?></div>
</div>
<?php if (!$active): ?><p class="flash flash--err">Bu üyelik askıya alındı: giriş yapamaz, profili ve yorumları sitede görünmez.</p><?php endif; ?>
<div class="grid2 grid2--top grid2--wide">
  <div>
    <section class="card">
      <h2>Etkinlikleri <span class="count"><?= count($mine) ?></span></h2>
      <?php if (!$mine): ?><p class="hint">Henüz kayıt yok.</p><?php endif; ?>
      <ul class="mini-list">
        <?php foreach ($mine as $r): $ev = event_by_id($r['event']); ?>
          <li><a href="./?s=kayit&amp;id=<?= e(urlencode($r['id'])) ?>"><strong><?= e($ev['title'] ?? 'Silinmiş etkinlik') ?></strong></a><span class="hint"><?= e(reg_session_label($r, $ev)) ?> · <?= (int) $r['seats'] ?> kişi</span><?= pill(REG_STATUS[$r['status']] ?? '', reg_tone($r['status'])) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="card">
      <h2>Katılmayı düşündükleri <span class="count"><?= count($thinks) ?></span></h2>
      <?php if (!$thinks): ?><p class="hint">Yok.</p><?php endif; ?>
      <ul class="mini-list"><?php foreach ($thinks as $ev): ?><li><a href="./?s=etkinlik&amp;id=<?= e(urlencode($ev['id'])) ?>"><?= e($ev['title']) ?></a><span class="hint"><?= e(event_when($ev)) ?></span></li><?php endforeach; ?></ul>
    </section>
    <section class="card">
      <h2>Yorumları <span class="count"><?= count($cms) ?></span></h2>
      <?php if (!$cms): ?><p class="hint">Yorum yok.</p><?php endif; ?>
      <ul class="mini-list"><?php foreach (array_slice($cms, 0, 20) as $cm): $ev = event_by_id($cm['eid']); ?><li><span><?= e(excerpt($cm['text'], 160)) ?></span><span class="hint"><?= e($ev['title'] ?? '') ?> · <?= e(time_ago($cm['date'])) ?></span></li><?php endforeach; ?></ul>
    </section>
  </div>
  <div>
    <section class="card">
      <h2>Bilgiler</h2>
      <dl class="dl">
        <div><dt>E-posta</dt><dd><a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a></dd></div>
        <div><dt>Telefon</dt><dd><?= !empty($u['phone']) ? '<a href="' . e(wa_href($u['phone'])) . '" target="_blank" rel="noopener">' . e($u['phone']) . '</a>' : '–' ?></dd></div>
        <div><dt>Şehir</dt><dd><?= e(($u['city'] ?? '') ?: '–') ?></dd></div>
        <div><dt>Hakkında</dt><dd><?= e(($u['bio'] ?? '') ?: '–') ?></dd></div>
        <div><dt>Instagram</dt><dd><?= e(($u['instagram'] ?? '') ?: '–') ?></dd></div>
        <div><dt>Profilde etkinlikler</dt><dd><?= ($u['show_events'] ?? true) ? 'Gösteriliyor' : 'Gizli' ?></dd></div>
        <div><dt>Üyelik</dt><dd><?= e(tr_datetime($u['created'] ?? '')) ?></dd></div>
        <div><dt>Son giriş</dt><dd><?= !empty($u['last_login']) ? e(tr_datetime($u['last_login'])) : '–' ?></dd></div>
      </dl>
    </section>
    <section class="card card--danger">
      <h2>Yönet</h2>
      <form method="post"><?= hidden($tok, 'uye-durum', ['id' => $u['id'], 'status' => $active ? 'engelli' : 'aktif']) ?><button class="btn btn--ghost"><?= $active ? 'Üyeliği askıya al' : 'Üyeliği yeniden aç' ?></button></form>
      <form method="post" data-confirm="Üye kalıcı olarak silinecek. Kayıtları etkinlik geçmişi için kalır. Emin misiniz?" class="stack">
        <?= hidden($tok, 'uye-sil', ['id' => $u['id']]) ?>
        <label class="check"><input type="checkbox" name="comments" value="1"> Yorumlarını da sil</label>
        <button class="btn btn--danger">Üyeyi sil</button>
      </form>
      <p class="hint">Askıya almak geri alınabilir; reklam ya da rahatsız edici yorumlar için önerilir.</p>
    </section>
  </div>
</div>
