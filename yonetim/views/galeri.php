<?php
// Galeri: sitedeki bütün görseller. Yükleme, albümler, açıklama, kullanım yerleri ve toplu işlemler.
// ?parca=1 ile görsel seçici penceresi için yalnızca liste döner.
/** @var string $tok */
$meta = media_meta();
$albums = $meta['albums'];
$albumName = array_column($albums, 'name', 'slug');
$folders = ['galeri' => 'Galeri yüklemeleri', 'etkinlik' => 'Etkinlik', 'mekan' => 'Mekan', 'egitmen' => 'Eğitmen', 'blog' => 'Blog', 'sayfa' => 'Sayfalar', 'ornek' => 'Örnek görseller', '' => 'Diğer'];
$all = media_all();
$use = media_usage();

$fq = trim((string) ($_GET['q'] ?? ''));
$fa = preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['album'] ?? ''));
$ff = preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['klasor'] ?? ''));
$fu = (string) ($_GET['kullanim'] ?? '');
$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 120;
$pick = !empty($_GET['parca']);

$list = array_values(array_filter($all, function ($m) use ($fq, $fa, $ff, $fu, $use) {
  if ($fa === '_yok' && $m['album'] !== '') return false;
  if ($fa !== '' && $fa !== '_yok' && $m['album'] !== $fa) return false;
  if ($ff !== '' && ($ff === '_diger' ? $m['folder'] !== '' : $m['folder'] !== $ff)) return false;
  if ($fu === 'bos' && !empty($use[$m['path']])) return false;
  if ($fu === 'var' && empty($use[$m['path']])) return false;
  if ($fq !== '' && !str_contains(mb_strtolower($m['path'] . ' ' . $m['title']), mb_strtolower($fq))) return false;
  return true;
}));
$total = count($list);
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);
$shown = array_slice($list, ($page - 1) * $per, $per);
$q = ['s' => 'galeri', 'q' => $fq, 'album' => $fa, 'klasor' => $ff, 'kullanim' => $fu];
$fname = fn($p) => basename($p);
$kb = fn(int $b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';

$filters = function () use ($q, $fq, $fa, $ff, $fu, $albums, $folders, $pick) { ?>
  <form class="toolbar toolbar--filters" method="get" data-<?= $pick ? 'mp-filter' : 'autosubmit' ?>>
    <input type="hidden" name="s" value="galeri">
    <input type="search" name="q" value="<?= e($fq) ?>" placeholder="Dosya adı ya da açıklama">
    <select name="album" aria-label="Albüm">
      <option value="">Tüm albümler</option>
      <?php foreach ($albums as $a): ?><option value="<?= e($a['slug']) ?>"<?= $fa === $a['slug'] ? ' selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?>
      <option value="_yok"<?= $fa === '_yok' ? ' selected' : '' ?>>Albümsüz</option>
    </select>
    <select name="klasor" aria-label="Nereden yüklendi">
      <option value="">Tüm yüklemeler</option>
      <?php foreach ($folders as $k => $l): ?><option value="<?= $k === '' ? '_diger' : e($k) ?>"<?= $ff === ($k === '' ? '_diger' : $k) ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <select name="kullanim" aria-label="Kullanım">
      <option value="">Kullanılan ve kullanılmayan</option>
      <option value="var"<?= $fu === 'var' ? ' selected' : '' ?>>Sitede kullanılanlar</option>
      <option value="bos"<?= $fu === 'bos' ? ' selected' : '' ?>>Hiçbir yerde kullanılmayanlar</option>
    </select>
    <?php if (!$pick): ?><noscript><button class="btn btn--ghost">Filtrele</button></noscript><?php endif; ?>
  </form>
<?php };

$uploader = function (string $album = '') use ($albums, $pick) { ?>
  <div class="drop" data-uploader>
    <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden data-upload-input>
    <div class="drop__text">
      <strong>Fotoğrafları buraya sürükleyin</strong>
      <span>ya da <button type="button" class="link" data-upload-open>bilgisayardan seçin</button>. Aynı anda yüzlerce fotoğraf seçebilirsiniz; büyük fotoğraflar yüklenmeden önce küçültülür.</span>
    </div>
    <?php if ($albums): ?>
      <label class="drop__album">Albüm<select data-upload-album>
        <option value="">Albümsüz</option>
        <?php foreach ($albums as $a): ?><option value="<?= e($a['slug']) ?>"<?= $album === $a['slug'] ? ' selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?>
      </select></label>
    <?php endif; ?>
    <div class="drop__progress" data-upload-progress hidden><div class="drop__bar"><span></span></div><p class="hint" data-upload-status></p></div>
  </div>
<?php };

// ---------- Seçici penceresi için liste ----------
if ($pick) { ?>
  <div class="mp__top">
    <?php $filters(); ?>
    <?php $uploader(); ?>
  </div>
  <p class="hint mp__count"><?= $total ?> görsel<?= $pages > 1 ? ' · sayfa ' . $page . '/' . $pages : '' ?></p>
  <?php if (!$shown): ?><p class="hint mp__empty"><?= $all ? 'Aramaya uyan görsel yok.' : 'Henüz görsel yok. Yukarıdan yükleyin.' ?></p><?php endif; ?>
  <div class="mgrid mgrid--pick" data-mp-grid>
    <?php foreach ($shown as $m): ?>
      <button type="button" class="mtile" data-mp-item="<?= e($m['path']) ?>" data-thumb="<?= e(thumb_url($m['path'])) ?>" title="<?= e($m['title'] ?: $fname($m['path'])) ?>">
        <img src="<?= e(thumb_url($m['path'])) ?>" alt="" loading="lazy">
        <?php if (!empty($use[$m['path']])): ?><span class="mtile__use">Kullanılıyor</span><?php endif; ?>
      </button>
    <?php endforeach; ?>
  </div>
  <?php if ($pages > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a href="<?= e(panel_url($q + ['p' => $i, 'parca' => 1])) ?>" data-mp-page<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
  <?php return;
}

// ---------- Tek görsel ----------
$one = media_valid((string) ($_GET['gorsel'] ?? ''));
if ($one !== '') {
  $m = media_item($one);
  $size = @getimagesize(ROOT . $one);
  $where = $use[$one] ?? [];
  $back = (string) ($_GET['geri'] ?? '');
  $back = preg_match('#^\./\?[A-Za-z0-9_=&%.+-]*$#', $back) ? $back : './?s=galeri';
  $idx = array_search($one, array_column($list, 'path'), true);
  $prev = $idx !== false && $idx > 0 ? $list[$idx - 1]['path'] : '';
  $next = $idx !== false && $idx < $total - 1 ? $list[$idx + 1]['path'] : '';
  $self = fn($p) => panel_url($q + ['gorsel' => $p, 'geri' => $back]);
  ?>
  <a class="back" href="<?= e($back) ?>">← Galeri</a>
  <div class="bar"><h1 class="h-break"><?= e($m['title'] ?: $fname($one)) ?></h1>
    <div class="bar__act"><?php if ($prev): ?><a class="btn btn--ghost btn--sm" href="<?= e($self($prev)) ?>">← Önceki</a><?php endif; ?><?php if ($next): ?><a class="btn btn--ghost btn--sm" href="<?= e($self($next)) ?>">Sonraki →</a><?php endif; ?></div>
  </div>
  <div class="grid2 grid2--wide grid2--top">
    <section class="card mview"><a href="<?= e($one) ?>" target="_blank" rel="noopener"><img src="<?= e($one) ?>" alt=""></a></section>
    <div class="stack-cards">
      <form class="card" method="post">
        <?= hidden($tok, 'medya', ['path' => $one, 'back' => $self($one)]) ?>
        <h2>Bilgiler</h2>
        <label>Açıklama <span class="opt">(galeride fotoğrafın altında ve görme engelliler için okunur)</span><textarea name="title" rows="2" maxlength="200"><?= e($m['title'] ?? '') ?></textarea></label>
        <label>Albüm<select name="album"><option value="">Albümsüz</option><?php foreach ($albums as $a): ?><option value="<?= e($a['slug']) ?>"<?= ($m['album'] ?? '') === $a['slug'] ? ' selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
        <div class="actions actions--start"><button class="btn">Kaydet</button></div>
      </form>
      <section class="card">
        <h2>Dosya</h2>
        <dl class="dl">
          <div><dt>Ad</dt><dd class="mono-s"><?= e($fname($one)) ?></dd></div>
          <?php if ($size): ?><div><dt>Ölçü</dt><dd><?= $size[0] ?> × <?= $size[1] ?> piksel</dd></div><?php endif; ?>
          <div><dt>Boyut</dt><dd><?= $kb((int) @filesize(ROOT . $one)) ?></dd></div>
          <div><dt>Eklendi</dt><dd><?= e(date('d.m.Y H:i', $m['time'])) ?></dd></div>
          <div><dt>Yüklendiği yer</dt><dd><?= e($folders[$m['folder']] ?? $m['folder']) ?></dd></div>
        </dl>
        <div class="copy-row"><input class="mono-s" value="<?= e($one) ?>" readonly><button type="button" class="btn btn--ghost btn--sm" data-copy="<?= e($one) ?>">Adresi kopyala</button></div>
      </section>
      <section class="card">
        <h2>Kullanıldığı yerler</h2>
        <?php if (!$where): ?><p class="hint">Bu görsel sitede hiçbir yerde kullanılmıyor<?= ($m['album'] ?? '') !== '' ? '; yalnızca "' . e($albumName[$m['album']] ?? $m['album']) . '" albümünde görünür' : '' ?>.</p>
        <?php else: ?><ul class="links"><?php foreach ($where as $w): ?><li><a href="<?= e($w['url']) ?>"><?= e($w['label']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
      </section>
      <form class="card card--danger" method="post" data-confirm="Bu görsel kalıcı olarak silinecek. Emin misiniz?">
        <?= hidden($tok, 'medya-sil', ['path' => $one, 'back' => $back]) ?>
        <h2>Sil</h2>
        <?php if ($where): ?><p class="hint">Kullanıldığı yerlerden kaldırmadan silemezsiniz.</p><?php endif; ?>
        <div><button class="btn btn--danger"<?= $where ? ' disabled' : '' ?>>Görseli sil</button></div>
      </form>
    </div>
  </div>
  <?php return;
}

// ---------- Liste ----------
$curAlbum = null;
foreach ($albums as $a) if ($a['slug'] === $fa) $curAlbum = $a;
$counts = [];
foreach ($all as $m) if ($m['album'] !== '') $counts[$m['album']] = ($counts[$m['album']] ?? 0) + 1;
$events = events_all();
usort($events, fn($a, $b) => strcmp(max(array_column($b['sessions'] ?? [], 'date') ?: ['']), max(array_column($a['sessions'] ?? [], 'date') ?: [''])));
$selfUrl = panel_url($q + ['p' => $page > 1 ? $page : null]);
$albumForm = function (array $a) use ($tok) { ?>
  <form method="post" class="subform">
    <?= hidden($tok, 'album', ['orig' => $a['slug'] ?? '']) ?>
    <div class="grid2">
      <label>Albüm adı<input name="name" value="<?= e($a['name'] ?? '') ?>" required maxlength="120" placeholder="Ör. Yaz atölyeleri 2026"></label>
      <label>Tarih <span class="opt">(sıralama için)</span><input type="date" name="date" value="<?= e($a['date'] ?? '') ?>"></label>
    </div>
    <label>Kısa açıklama <span class="opt">(isteğe bağlı)</span><textarea name="text" rows="2" maxlength="600"><?= e($a['text'] ?? '') ?></textarea></label>
    <label class="check"><input type="checkbox" name="public" value="1"<?= !isset($a['public']) || !empty($a['public']) ? ' checked' : '' ?>> Sitedeki Galeri sayfasında göster</label>
    <div class="plist__act plist__act--start"><button class="btn"><?= empty($a['slug']) ? 'Albümü oluştur' : 'Kaydet' ?></button></div>
  </form>
<?php };
?>
<div class="bar"><h1>Galeri <span class="count"><?= count($all) ?></span></h1><div class="bar__act"><a class="btn btn--ghost btn--sm" href="/galeri/" target="_blank" rel="noopener">Sitede gör ↗</a></div></div>
<?php $big = media_big(); if ($big): ?>
<form method="post" class="notice" data-confirm="Büyük görseller aynı adla, küçültülmüş hâlleriyle değiştirilecek (en fazla 1920 piksel). Orijinaller saklanmaz. Devam edilsin mi?">
  <?= hidden($tok, 'medya-optimize') ?>
  <span><?= count($big) ?> görsel 400 KB'tan büyük (toplam <?= round(array_sum(array_column($big, 'size')) / 1048576, 1) ?> MB). Sayfaların hızlı açılması için küçültülebilir.</span>
  <button class="btn btn--sm">Büyük görselleri küçült</button>
</form>
<?php endif; ?>
<p class="hint">Yüklediğiniz fotoğraflar otomatik olarak en fazla 1920 piksele küçültülür ve sıkıştırılır; büyük orijinal sunucuda tutulmaz.</p>
<p class="hint">Sitedeki bütün fotoğraflar burada. Etkinlik, blog ya da sayfalarda görsel seçerken de bu galeri açılır; bir fotoğrafı bir kez yükleyip istediğiniz yerde kullanabilirsiniz. Etkinlik galerilerine eklenen fotoğraflar sitedeki Galeri sayfasında etkinlik albümü olarak kendiliğinden görünür.</p>

<section class="card">
  <h2>Fotoğraf yükle</h2>
  <?php $uploader($fa !== '_yok' ? $fa : ''); ?>
</section>

<section class="card">
  <div class="card__head"><h2>Albümler</h2><button type="button" class="btn btn--ghost btn--sm" data-toggle="#yeni-album">+ Yeni albüm</button></div>
  <div id="yeni-album" hidden><?php $albumForm([]); ?></div>
  <?php if (!$albums): ?><p class="hint">Albüm, etkinlik dışındaki fotoğrafları gruplamak içindir (ör. "Kurumsal etkinlikler", "Atölyeden kareler"). Etkinlik fotoğrafları için albüm açmanıza gerek yok; etkinliğin galerisine eklemeniz yeterli.</p>
  <?php else: ?>
    <nav class="chips">
      <a href="<?= e(panel_url(['s' => 'galeri'])) ?>"<?= $fa === '' ? ' aria-current="page"' : '' ?>>Tümü <small><?= count($all) ?></small></a>
      <?php foreach ($albums as $a): ?><a href="<?= e(panel_url(['s' => 'galeri', 'album' => $a['slug']])) ?>"<?= $fa === $a['slug'] ? ' aria-current="page"' : '' ?>><?= e($a['name']) ?> <small><?= $counts[$a['slug']] ?? 0 ?></small><?= empty($a['public']) ? ' <span class="tag">gizli</span>' : '' ?></a><?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <?php if ($curAlbum): ?>
    <details class="howto"><summary>"<?= e($curAlbum['name']) ?>" albümünü düzenle</summary><?php $albumForm($curAlbum); ?>
      <form method="post" data-confirm="Albüm silinecek; içindeki fotoğraflar galeride kalır. Emin misiniz?"><?= hidden($tok, 'album-sil', ['slug' => $curAlbum['slug']]) ?><button class="btn btn--danger btn--sm">Albümü sil</button></form>
    </details>
  <?php endif; ?>
</section>

<?php $filters(); ?>

<?php if (!$shown): ?>
  <section class="card"><p class="hint"><?= $all ? 'Seçtiğiniz filtreye uyan görsel yok.' : 'Henüz görsel yok. Yukarıdan yükleyebilirsiniz.' ?></p></section>
<?php else: ?>
  <form method="post" class="mlist" data-bulk>
    <?= hidden($tok, 'medya-toplu', ['back' => $selfUrl]) ?>
    <div class="mlist__head">
      <label class="check"><input type="checkbox" data-bulk-all> Bu sayfadakilerin tümünü seç</label>
      <span class="hint"><?= $total ?> görsel<?= $pages > 1 ? ' · sayfa ' . $page . '/' . $pages : '' ?></span>
    </div>
    <div class="mgrid">
      <?php foreach ($shown as $m): $u = $use[$m['path']] ?? []; ?>
        <div class="mcard">
          <label class="mcard__check" title="Seç"><input type="checkbox" name="paths[]" value="<?= e($m['path']) ?>" data-bulk-item><span class="sr">Seç</span></label>
          <a class="mcard__img" href="<?= e(panel_url($q + ['gorsel' => $m['path'], 'geri' => $selfUrl])) ?>"><img src="<?= e(thumb_url($m['path'])) ?>" alt="" loading="lazy"></a>
          <div class="mcard__meta">
            <span class="mcard__name" title="<?= e($fname($m['path'])) ?>"><?= e($m['title'] ?: $fname($m['path'])) ?></span>
            <span class="mcard__tags"><?php if ($u): ?><span class="tag tag--on" title="<?= e(implode("\n", array_column($u, 'label'))) ?>">Kullanılıyor<?= count($u) > 1 ? ' · ' . count($u) : '' ?></span><?php endif; ?><?php if ($m['album'] !== '' && isset($albumName[$m['album']])): ?><span class="tag"><?= e($albumName[$m['album']]) ?></span><?php endif; ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="bulkbar" data-bulk-bar hidden>
      <strong data-bulk-count>0 seçili</strong>
      <select name="op" required aria-label="Seçilenlere uygula">
        <option value="">Seçilenlere ne yapılsın?</option>
        <?php if ($events): ?><optgroup label="Etkinlik galerisine ekle"><?php foreach ($events as $ev): $d = array_column($ev['sessions'] ?? [], 'date'); ?><option value="etkinlik:<?= e($ev['id']) ?>"><?= e($ev['title']) ?><?= $d ? ' · ' . e(tr_date(max($d))) : '' ?></option><?php endforeach; ?></optgroup><?php endif; ?>
        <?php if ($albums): ?><optgroup label="Albüme taşı"><?php foreach ($albums as $a): ?><option value="album:<?= e($a['slug']) ?>"><?= e($a['name']) ?></option><?php endforeach; ?><option value="album:">Albümden çıkar</option></optgroup><?php endif; ?>
        <option value="sil">Sil (kullanılmayanları)</option>
      </select>
      <button class="btn btn--sm">Uygula</button>
      <button type="button" class="link" data-bulk-clear>Seçimi kaldır</button>
    </div>
  </form>
  <?php if ($pages > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a href="<?= e(panel_url($q + ['p' => $i])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
<?php endif; ?>
