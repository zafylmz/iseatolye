      <?php
        $filter = (string) ($_GET['f'] ?? 'bekleyen');
        $titles = array_column(blog_all(), 'title', 'slug');
        $rows = [];
        foreach (json_read(BLOG_COMMENTS_FILE) as $s => $list) foreach ($list as $cm) $rows[] = ['slug' => $s] + $cm;
        usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));
        $pendingCount = count(array_filter($rows, fn($r) => $r['status'] !== 'approved'));
        if ($filter === 'bekleyen') $rows = array_values(array_filter($rows, fn($r) => $r['status'] !== 'approved'));
      ?>
      <div class="bar"><h2 class="h-sub">Blog yorumları</h2>
        <nav class="seg"><a href="./?s=yorumlar&amp;t=blog&amp;f=bekleyen"<?= $filter === 'bekleyen' ? ' aria-current="page"' : '' ?>>Onay bekleyen (<?= $pendingCount ?>)</a><a href="./?s=yorumlar&amp;t=blog&amp;f=tum"<?= $filter !== 'bekleyen' ? ' aria-current="page"' : '' ?>>Tümü</a></nav>
      </div>
      <p class="hint">Blog yorumları üyelik gerektirmez; siz onaylayana kadar sitede görünmez. Botlar gizli alan, süre kontrolü, tarayıcı doğrulaması, bağlantı sınırı ve IP başına hız sınırıyla zaten elenir.</p>
      <?php if (!$rows): ?><section class="card"><p class="hint"><?= $filter === 'bekleyen' ? 'Onay bekleyen yorum yok.' : 'Henüz yorum yok.' ?></p></section><?php endif; ?>
      <?php foreach ($rows as $r): $q = '&t=blog&f=' . urlencode($filter); ?>
        <section class="card comment-admin">
          <div class="comment-admin__head">
            <strong><?= e($r['name']) ?></strong>
            <?php if (($r['email'] ?? '') !== ''): ?><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><?php endif; ?>
            <span class="hint"><?= e($r['date']) ?> · <a href="/blog/<?= e($r['slug']) ?>/" target="_blank" rel="noopener"><?= e($titles[$r['slug']] ?? $r['slug']) ?></a></span>
            <span class="pill<?= $r['status'] === 'approved' ? ' pill--on' : '' ?>"><?= $r['status'] === 'approved' ? 'Yayında' : 'Onay bekliyor' ?></span>
          </div>
          <p class="comment-admin__text"><?= nl2br(e($r['text'])) ?></p>
          <form method="post" action="./?s=yorumlar<?= e($q) ?>" class="comment-admin__reply">
            <input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="yorum"><input type="hidden" name="slug" value="<?= e($r['slug']) ?>"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><input type="hidden" name="do" value="yanit">
            <label>Yanıtınız (isteğe bağlı, yorumun altında "Yazar" olarak görünür)<textarea name="reply" rows="2"><?= e($r['reply'] ?? '') ?></textarea></label>
            <button class="btn btn--ghost">Yanıtı kaydet</button>
          </form>
          <div class="plist__act">
            <?php foreach (($r['status'] === 'approved' ? ['gizle' => 'Yayından kaldır'] : ['onayla' => 'Onayla']) + ['sil' => 'Sil'] as $do => $lbl): ?>
              <form method="post" action="./?s=yorumlar<?= e($q) ?>"<?= $do === 'sil' ? ' onsubmit="return confirm(\'Bu yorumu silmek istediğinize emin misiniz?\')"' : '' ?>><input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="yorum"><input type="hidden" name="slug" value="<?= e($r['slug']) ?>"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><input type="hidden" name="do" value="<?= $do ?>"><button class="btn<?= $do === 'sil' ? ' btn--danger' : ($do === 'gizle' ? ' btn--ghost' : '') ?>"><?= $lbl ?></button></form>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>

