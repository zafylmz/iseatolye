      <?php
        $ranges = [1 => 'Bugün', 7 => '7 gün', 30 => '30 gün', 90 => '90 gün'];
        $rg = (int) ($_GET['r'] ?? 7); if (!isset($ranges[$rg])) $rg = 7;
        $st = stats_summary($rg);
        $avg = $st['dur_n'] ? (int) round($st['dur_sum'] / $st['dur_n']) : 0;
        $per = $st['visitors'] ? number_format($st['views'] / $st['visitors'], 1, ',', '') : '–';
        $nf = fn($n) => number_format((int) $n, 0, ',', '.');
        $skip = !empty($_COOKIE[STATS_SKIP_COOKIE]);
        // Grafik: bugün için saat saat, diğerlerinde gün gün tekil ziyaretçi
        $series = $rg === 1
          ? array_map(fn($h, $x) => ['label' => sprintf('%02d:00', $h), 'short' => (string) $h, 'visitors' => $x['visitors'], 'views' => $x['views']], array_keys($st['hours']), $st['hours'])
          : array_map(fn($x) => ['label' => tr_date($x['day']), 'short' => (string) (int) substr($x['day'], 8), 'visitors' => $x['visitors'], 'views' => $x['views']], $st['days']);
        $max = max(1, ...array_column($series, 'visitors'));
        $step = $max <= 4 ? 1 : (int) ceil($max / 4);
        $top = $step * (int) ceil($max / $step);
        $n = count($series); $W = 900; $H = 220; $padL = 34; $padB = 24; $gap = 2;
        $bw = ($W - $padL) / $n;
        $labelEvery = $n > 31 ? 7 : ($n > 12 ? 3 : 1);
        $devLabels = ['masaustu' => 'Masaüstü', 'mobil' => 'Mobil', 'tablet' => 'Tablet'];
        $devTotal = max(1, array_sum($st['devices']));
        $direct = $st['views'] - array_sum($st['refs']);
      ?>
      <div class="bar">
        <h1>İstatistikler</h1>
        <nav class="seg" aria-label="Zaman aralığı"><?php foreach ($ranges as $k => $l): ?><a href="./?s=istatistik&amp;r=<?= $k ?>"<?= $rg === $k ? ' aria-current="page"' : '' ?>><?= $l ?></a><?php endforeach; ?></nav>
      </div>
      <p class="hint"><span class="live-dot" aria-hidden="true"></span> Şu an sitede: <b><?= $nf($st['active']) ?></b> kişi (son 5 dakika). Sayaç çerez kullanmaz, IP adresi saklamaz; botlar sayılmaz.</p>

      <section class="kpis">
        <div class="kpi"><span>Ziyaretçi</span><strong><?= $nf($st['visitors']) ?></strong><small><?= $rg === 1 ? 'bugün, tekil' : 'günlük tekil toplamı' ?></small></div>
        <div class="kpi"><span>Sayfa görüntüleme</span><strong><?= $nf($st['views']) ?></strong><small>ziyaretçi başına <?= $per ?></small></div>
        <div class="kpi"><span>Ort. kalma süresi</span><strong><?= stats_duration($avg) ?></strong><small>sayfa başına, sekme açıkken</small></div>
        <div class="kpi"><span>Gezilen sayfa</span><strong><?= $nf(count($st['pages'])) ?></strong><small>farklı adres</small></div>
      </section>

      <section class="card">
        <h2><?= $rg === 1 ? 'Bugün saat saat ziyaretçi' : 'Günlük ziyaretçi' ?></h2>
        <?php if (!$st['views']): ?><p class="hint">Bu aralıkta henüz ziyaret kaydı yok. Sayaç yeni kuruldu; ziyaretler geldikçe burada görünecek.</p><?php endif; ?>
        <div class="chart-wrap"><svg class="chart" viewBox="0 0 <?= $W ?> <?= $H + $padB ?>" role="img" aria-label="<?= $rg === 1 ? 'Saatlik' : 'Günlük' ?> tekil ziyaretçi grafiği">
          <?php for ($y = 0; $y <= $top; $y += $step): $yy = $H - $y / $top * ($H - 10); ?>
            <line class="chart__grid" x1="<?= $padL ?>" x2="<?= $W ?>" y1="<?= $yy ?>" y2="<?= $yy ?>"/>
            <text class="chart__axis" x="<?= $padL - 8 ?>" y="<?= $yy + 4 ?>" text-anchor="end"><?= $y ?></text>
          <?php endfor; ?>
          <?php foreach ($series as $k => $p): $x = $padL + $k * $bw; $bh = $p['visitors'] / $top * ($H - 10); ?>
            <g class="chart__col">
              <rect class="chart__hit" x="<?= $x ?>" y="0" width="<?= $bw ?>" height="<?= $H ?>"/>
              <?php if ($bh > 0): $r = min(4, $bh, ($bw - $gap) / 2); $w = $bw - $gap; $bx = $x + $gap / 2; ?>
                <path class="chart__bar" d="M<?= $bx ?> <?= $H ?>V<?= $H - $bh + $r ?>q0 -<?= $r ?> <?= $r ?> -<?= $r ?>h<?= $w - 2 * $r ?>q<?= $r ?> 0 <?= $r ?> <?= $r ?>V<?= $H ?>z"/>
              <?php endif; ?>
              <?php if ($k % $labelEvery === 0): ?><text class="chart__axis" x="<?= $x + $bw / 2 ?>" y="<?= $H + 17 ?>" text-anchor="middle"><?= e($p['short']) ?></text><?php endif; ?>
              <title><?= e($p['label']) ?>: <?= $nf($p['visitors']) ?> ziyaretçi, <?= $nf($p['views']) ?> görüntüleme</title>
            </g>
          <?php endforeach; ?>
        </svg></div>
        <script>document.querySelectorAll('.chart-wrap').forEach(el => { el.scrollLeft = el.scrollWidth; });</script>
        <details class="tbl-toggle"><summary>Tablo olarak göster</summary>
          <table class="tbl"><thead><tr><th><?= $rg === 1 ? 'Saat' : 'Gün' ?></th><th>Ziyaretçi</th><th>Görüntüleme</th></tr></thead><tbody>
            <?php foreach (array_reverse($series) as $p): ?><tr><td><?= e($p['label']) ?></td><td><?= $nf($p['visitors']) ?></td><td><?= $nf($p['views']) ?></td></tr><?php endforeach; ?>
          </tbody></table>
        </details>
      </section>

      <section class="card">
        <h2>En çok gezilen sayfalar</h2>
        <?php if (!$st['pages']): ?><p class="hint">Henüz kayıt yok.</p><?php else: ?>
          <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>Sayfa</th><th>Görüntüleme</th><th>Ziyaretçi</th><th>Ort. süre</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($st['pages'], 0, 25, true) as $path => $p): ?>
                <tr>
                  <td class="tbl__page"><a href="<?= e($path) ?>" target="_blank" rel="noopener"><?= e($p['title'] !== '' ? preg_replace('/\s*[·|–-]\s*' . preg_quote((string) ($c['brand']['name'] ?? ''), '/') . '.*$/u', '', $p['title']) ?: $path : $path) ?></a><small><?= e(rawurldecode((string) $path)) ?></small></td>
                  <td><?= $nf($p['views']) ?></td><td><?= $nf($p['visitors']) ?></td><td><?= stats_duration($p['dur_n'] ? (int) round($p['dur_sum'] / $p['dur_n']) : 0) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </section>

      <div class="grid2 grid2--top">
        <section class="card">
          <h2>Nereden geldiler</h2>
          <?php if (!$st['views']): ?><p class="hint">Henüz kayıt yok.</p><?php else: ?>
            <ul class="meter">
              <?php foreach (['Doğrudan / bilinmiyor' => $direct] + array_slice($st['refs'], 0, 9, true) as $name => $cnt): if ($cnt <= 0) continue; ?>
                <li><span><?= e((string) $name) ?></span><b><?= $nf($cnt) ?></b><i style="width:<?= round($cnt / max(1, $st['views']) * 100, 1) ?>%"></i></li>
              <?php endforeach; ?>
            </ul>
            <p class="hint">Görüntüleme sayısına göre. Google, Instagram gibi siteler adıyla görünür.</p>
          <?php endif; ?>
        </section>
        <section class="card">
          <h2>Cihaz türü</h2>
          <?php if (!$st['views']): ?><p class="hint">Henüz kayıt yok.</p><?php else: ?>
            <ul class="meter">
              <?php foreach ($st['devices'] as $k => $cnt): ?>
                <li><span><?= $devLabels[$k] ?? e($k) ?></span><b>%<?= round($cnt / $devTotal * 100) ?></b><?php if ($cnt): ?><i style="width:<?= round($cnt / $devTotal * 100, 1) ?>%"></i><?php endif; ?></li>
              <?php endforeach; ?>
            </ul>
            <p class="hint">Tekil ziyaretçilere göre.</p>
          <?php endif; ?>
        </section>
      </div>

      <div class="grid2 grid2--top">
        <section class="card">
          <h2>Şehirler</h2>
          <?php if (!$st['cities']): ?><p class="hint">Henüz konum kaydı yok.</p><?php else: $cTotal = max(1, array_sum($st['countries'])); ?>
            <ul class="meter">
              <?php foreach (array_slice($st['cities'], 0, 10, true) as $k => $cnt): [$ci, $rgn, $cc] = explode('|', $k) + ['', '', '']; $label = implode(', ', array_unique(array_filter([$ci, $rgn]))); ?>
                <li><span><?= e($label) ?><?= $cc !== '' && $cc !== 'TR' ? ' <small>' . e(country_name($cc)) . '</small>' : '' ?></span><b><?= $nf($cnt) ?></b><i style="width:<?= round($cnt / $cTotal * 100, 1) ?>%"></i></li>
              <?php endforeach; ?>
            </ul>
            <p class="hint">Tekil ziyaretçilere göre. Konum IP adresinden tahmin edilir; mobil hatlarda en yakın büyük şehir görünebilir.</p>
          <?php endif; ?>
        </section>
        <section class="card">
          <h2>Ülkeler</h2>
          <?php if (!$st['countries']): ?><p class="hint">Henüz kayıt yok.</p><?php else: $cTotal = max(1, array_sum($st['countries'])); ?>
            <ul class="meter">
              <?php foreach (array_slice($st['countries'], 0, 10, true) as $cc => $cnt): ?>
                <li><span><?= e($cc === '' ? 'Bilinmiyor' : country_name((string) $cc)) ?></span><b><?= $nf($cnt) ?></b><i style="width:<?= round($cnt / $cTotal * 100, 1) ?>%"></i></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </div>

      <section class="card">
        <h2>Son ziyaretler</h2>
        <?php if (!$st['recent']): ?><p class="hint">Henüz kayıt yok.</p><?php else: ?>
          <div class="tbl-wrap"><table class="tbl tbl--recent">
            <thead><tr><th>Zaman</th><th>Sayfa</th><th>Konum</th><th>IP</th><th>Cihaz</th><th>Süre</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($st['recent'], 0, 25) as $v): $vt = (new DateTimeImmutable('@' . (int) $v['t']))->setTimezone(stats_tz()); ?>
                <tr>
                  <td><?= $vt->format('Y-m-d') === date_create('now', stats_tz())->format('Y-m-d') ? $vt->format('H:i') : e(tr_date($vt->format('Y-m-d'))) . ' ' . $vt->format('H:i') ?></td>
                  <td class="tbl__page"><a href="<?= e($v['p']) ?>" target="_blank" rel="noopener"><?= e(rawurldecode((string) $v['p'])) ?></a><?php if (($v['r'] ?? '') !== ''): ?><small><?= e($v['r']) ?> üzerinden</small><?php endif; ?></td>
                  <td><?= e(implode(', ', array_unique(array_filter([$v['ci'] ?? '', $v['rg'] ?? '']))) ?: '–') ?><?php if (($v['c'] ?? '') !== '' && $v['c'] !== 'TR'): ?><small><?= e(country_name($v['c'])) ?></small><?php endif; ?></td>
                  <td class="tbl__ip"><?= e($v['ip'] ?? '–') ?></td>
                  <td><?= $devLabels[$v['d'] ?? ''] ?? '–' ?></td>
                  <td><?= stats_duration((int) ($v['s'] ?? 0)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
          <p class="hint">IP adresinin son kısmı gizlenerek saklanır ve <?= STATS_IP_DAYS ?> gün sonra kayıtlardan silinir. Ziyaretçiler <a href="/gizlilik/" target="_blank" rel="noopener">Gizlilik</a> sayfasında bilgilendirilir.</p>
        <?php endif; ?>
      </section>

      <section class="card">
        <h2>Kendi ziyaretleriniz</h2>
        <p class="hint"><?= $skip ? 'Bu tarayıcıdan siteye girdiğinizde sayılmıyorsunuz, böylece rakamlar yalnızca ziyaretçileri gösterir. Panele giriş yaptığınız her tarayıcıda bu otomatik açılır.' : 'Bu tarayıcıdan yaptığınız ziyaretler de sayılıyor.' ?></p>
        <form method="post"><input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="sayma"><input type="hidden" name="on" value="<?= $skip ? '0' : '1' ?>"><button class="btn btn--ghost"><?= $skip ? 'Beni de say' : 'Bu tarayıcıyı sayma' ?></button></form>
      </section>

      <section class="card">
        <h2>Konum verisi</h2>
        <?php $geoAge = is_file(GEO_FILE) ? (int) floor((time() - filemtime(GEO_FILE)) / 86400) : -1; ?>
        <p class="hint"><?= $geoAge < 0 ? 'Konum veritabanı henüz indirilmedi; ziyaretlerde şehir görünmez.' : 'Konum veritabanı ' . ($geoAge === 0 ? 'bugün' : $geoAge . ' gün önce') . ' güncellendi.' . ($geoAge > 40 ? ' Ayda bir güncellemeniz önerilir.' : '') ?> IP adresleri hiçbir dış servise gönderilmez; konum sunucudaki veritabanından bulunur. <a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a></p>
        <form method="post" onsubmit="this.querySelector('button').textContent='İndiriliyor, lütfen bekleyin...'"><input type="hidden" name="csrf" value="<?= e($tok) ?>"><input type="hidden" name="action" value="geo-guncelle"><button class="btn btn--ghost">Konum verisini güncelle</button></form>
      </section>

