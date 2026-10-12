// Panel davranışları: tekrarlanan satırlar, onay soruları, mobil menü.
(() => {
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  let uid = Date.now();

  // Satır ekle / kaldır (tarihler, biletler, sorular, kategoriler, bağlantılar)
  const addRow = (name, fill) => {
    const tpl = document.querySelector('template[data-tpl="' + name + '"]');
    const rows = document.querySelector('[data-rows="' + name + '"]');
    if (!tpl || !rows) return null;
    const html = tpl.innerHTML.replace(/__i__/g, 'n' + uid++);
    const box = document.createElement('div');
    box.innerHTML = html.trim();
    const row = box.firstElementChild;
    if (fill) fill(row);
    rows.append(row);
    const first = row.querySelector('input:not([type=hidden]), select, textarea');
    if (first) first.focus();
    syncPackage();
    return row;
  };
  document.addEventListener('click', (e) => {
    const add = e.target.closest('[data-add]');
    if (add) { addRow(add.dataset.add); return; }
    const dup = e.target.closest('[data-dup-last]');
    if (dup) {
      // Son tarihi bir hafta sonrasına kopyalar (haftalık tekrar eden atölyeler için)
      const name = dup.dataset.dupLast;
      const rows = $$('[data-rows="' + name + '"] [data-row]');
      const last = rows[rows.length - 1];
      if (!last) { addRow(name); return; }
      const val = (sel) => { const el = last.querySelector('[name$="[' + sel + ']"]'); return el ? el.value : ''; };
      addRow(name, (row) => {
        const set = (sel, v) => { const el = row.querySelector('[name$="[' + sel + ']"]'); if (el) el.value = v; };
        const d = val('date');
        if (d) { const dt = new Date(d + 'T12:00:00'); dt.setDate(dt.getDate() + 7); set('date', dt.toISOString().slice(0, 10)); }
        ['start', 'end', 'venue', 'place', 'capacity'].forEach((k) => set(k, val(k)));
      });
      return;
    }
    const rm = e.target.closest('[data-remove]');
    if (rm && !rm.disabled) {
      const row = rm.closest('[data-row]');
      if (row) row.remove();
      return;
    }
    const tg = e.target.closest('[data-toggle]');
    if (tg) {
      const el = document.querySelector(tg.dataset.toggle);
      if (el) { el.hidden = !el.hidden; if (!el.hidden) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); const f = el.querySelector('input:not([type=hidden]), textarea'); if (f) f.focus({ preventScroll: true }); } }
    }
  });

  // Mekan seçilince kontenjan boşsa mekanın kapasitesini öner
  document.addEventListener('change', (e) => {
    const sel = e.target.closest('[data-venue]');
    if (!sel) return;
    const row = sel.closest('[data-row]');
    const cap = row && row.querySelector('[name$="[capacity]"]');
    const opt = sel.selectedOptions[0];
    if (cap && opt && opt.dataset.cap && (cap.value === '' || cap.value === '0')) cap.value = opt.dataset.cap;
  });

  // Paket (kurs) seçeneği: oturum kontenjanı yerine tek kontenjan
  const editor = document.querySelector('[data-editor]');
  const pkg = document.querySelector('[data-package]');
  function syncPackage() { if (editor && pkg) editor.classList.toggle('is-package', pkg.checked); }
  if (pkg) pkg.addEventListener('change', syncPackage);
  syncPackage();

  // Kaydedilmemiş değişiklik uyarısı
  if (editor) {
    let dirty = false;
    editor.addEventListener('input', () => { dirty = true; });
    editor.addEventListener('submit', () => { dirty = false; });
    addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  }

  // Onay soruları
  document.addEventListener('submit', (e) => {
    const f = e.target.closest('form[data-confirm]');
    if (f && !confirm(f.dataset.confirm)) e.preventDefault();
    // Çift gönderimi önle
    if (!e.defaultPrevented) {
      const b = e.target.querySelector('button:not([type=button])');
      if (b) setTimeout(() => { b.disabled = true; }, 0);
    }
  });

  // Mobil menü
  const toggle = document.querySelector('.menu-toggle');
  const side = document.getElementById('panel-menu');
  if (toggle && side) {
    const set = (open) => { side.classList.toggle('is-open', open); toggle.setAttribute('aria-expanded', String(open)); };
    toggle.addEventListener('click', (e) => { e.stopPropagation(); set(!side.classList.contains('is-open')); });
    document.addEventListener('click', (e) => { if (side.classList.contains('is-open') && !e.target.closest('#panel-menu')) set(false); });
    addEventListener('keydown', (e) => { if (e.key === 'Escape') set(false); });
  }

  // ---------- Galeri: yükleme, seçici pencere, toplu işlemler ----------
  const csrf = (document.querySelector('meta[name=csrf]') || {}).content || '';

  // Büyük fotoğrafları yüklemeden önce tarayıcıda 1920 piksele küçültür (telefon fotoğrafları 5-10 MB olabiliyor)
  const shrink = async (file) => {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !window.createImageBitmap) return file;
    let bmp;
    try { bmp = await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch { return file; }
    const max = 1920, big = Math.max(bmp.width, bmp.height);
    if (big <= max && file.size < 1.8e6) { bmp.close && bmp.close(); return file; }
    const k = Math.min(1, max / big);
    const cv = document.createElement('canvas');
    cv.width = Math.round(bmp.width * k); cv.height = Math.round(bmp.height * k);
    cv.getContext('2d').drawImage(bmp, 0, 0, cv.width, cv.height);
    bmp.close && bmp.close();
    const type = file.type === 'image/png' && file.size < 4e6 ? 'image/png' : 'image/jpeg';
    const blob = await new Promise((r) => cv.toBlob(r, type, 0.88));
    if (!blob || blob.size >= file.size) return file;
    return new File([blob], file.name.replace(/\.(png|webp|jpe?g)$/i, '') + (type === 'image/png' ? '.png' : '.jpg'), { type });
  };

  // Görselleri tek tek gönderir; her biri bitince onDone(sonuç) çağrılır
  const uploadAll = async (files, box, album, onDone) => {
    const prog = box.querySelector('[data-upload-progress]');
    const bar = prog && prog.querySelector('.drop__bar span');
    const status = box.querySelector('[data-upload-status]');
    const list = Array.from(files).filter((f) => /^image\//.test(f.type));
    if (!list.length) return;
    if (prog) prog.hidden = false;
    let ok = 0; const errors = [];
    for (let i = 0; i < list.length; i++) {
      if (status) status.textContent = (i + 1) + ' / ' + list.length + ' yükleniyor: ' + list[i].name;
      if (bar) bar.style.width = Math.round((i / list.length) * 100) + '%';
      try {
        const fd = new FormData();
        fd.append('csrf', csrf); fd.append('action', 'medya-yukle'); fd.append('album', album || '');
        fd.append('file', await shrink(list[i]));
        const res = await fetch('./', { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await res.json().catch(() => ({ ok: false, error: 'Sunucu yanıt vermedi (dosya çok büyük olabilir).' }));
        if (d.ok) { ok++; onDone && onDone(d); } else errors.push(list[i].name + ': ' + d.error);
      } catch { errors.push(list[i].name + ': bağlantı hatası'); }
    }
    if (bar) bar.style.width = '100%';
    if (status) status.textContent = ok + ' fotoğraf yüklendi.' + (errors.length ? ' Yüklenemeyenler: ' + errors.join(' · ') : '');
    return { ok, errors };
  };

  const bindUploader = (box, onDone, onAll) => {
    const input = box.querySelector('[data-upload-input]');
    const albumSel = box.querySelector('[data-upload-album]');
    const go = async (files) => { const r = await uploadAll(files, box, albumSel ? albumSel.value : '', onDone); if (r && onAll) onAll(r); };
    box.addEventListener('click', (e) => { if (e.target.closest('[data-upload-open]')) input.click(); });
    input.addEventListener('change', () => { go(input.files); input.value = ''; });
    box.addEventListener('dragover', (e) => { e.preventDefault(); box.classList.add('is-over'); });
    box.addEventListener('dragleave', (e) => { if (!box.contains(e.relatedTarget)) box.classList.remove('is-over'); });
    box.addEventListener('drop', (e) => { e.preventDefault(); box.classList.remove('is-over'); go(e.dataTransfer.files); });
  };

  // Galeri sayfasındaki yükleme alanı: bitince sayfa yenilenir
  $$('[data-uploader]').forEach((box) => bindUploader(box, null, (r) => { if (r.ok) setTimeout(() => location.reload(), r.errors.length ? 2500 : 600); }));

  // Seçici pencere
  let mp = null;
  const picker = () => {
    if (mp) return mp;
    const dlg = document.createElement('dialog');
    dlg.className = 'mp';
    dlg.innerHTML = '<div class="mp__head"><h2>Galeri</h2><button type="button" class="mp__close" data-mp-close aria-label="Kapat">✕</button></div>'
      + '<div class="mp__body" data-mp-body><p class="hint">Yükleniyor…</p></div>'
      + '<div class="mp__foot"><span class="hint" data-mp-sel></span><button type="button" class="btn btn--ghost" data-mp-close>Vazgeç</button><button type="button" class="btn" data-mp-ok disabled>Seç</button></div>';
    document.body.append(dlg);
    const body = dlg.querySelector('[data-mp-body]');
    const okBtn = dlg.querySelector('[data-mp-ok]');
    const selInfo = dlg.querySelector('[data-mp-sel]');
    const state = { multi: false, sel: new Map(), cb: null, params: '' };
    const paint = () => {
      body.querySelectorAll('[data-mp-item]').forEach((t) => t.classList.toggle('is-on', state.sel.has(t.dataset.mpItem)));
      okBtn.disabled = !state.sel.size;
      okBtn.textContent = state.multi && state.sel.size > 1 ? state.sel.size + ' görseli ekle' : 'Seç';
      selInfo.textContent = state.sel.size ? state.sel.size + ' görsel seçili' : (state.multi ? 'Birden fazla görsel seçebilirsiniz' : 'Bir görsel seçin');
    };
    const load = async (params) => {
      state.params = params || '';
      body.classList.add('is-loading');
      try {
        const res = await fetch('./?s=galeri&parca=1' + (state.params ? '&' + state.params : ''), { credentials: 'same-origin' });
        body.innerHTML = await res.text();
      } catch { body.innerHTML = '<p class="flash flash--err">Galeri açılamadı. Sayfayı yenileyip tekrar deneyin.</p>'; }
      body.classList.remove('is-loading');
      const up = body.querySelector('[data-uploader]');
      if (up) bindUploader(up, (d) => {
        const grid = body.querySelector('[data-mp-grid]');
        const empty = body.querySelector('.mp__empty'); if (empty) empty.remove();
        const t = document.createElement('button');
        t.type = 'button'; t.className = 'mtile'; t.dataset.mpItem = d.path; t.dataset.thumb = d.thumb;
        t.innerHTML = '<img alt="">'; t.querySelector('img').src = d.thumb;
        grid.prepend(t);
        if (!state.multi) state.sel.clear();
        state.sel.set(d.path, d.thumb);
        paint();
      });
      paint();
    };
    let deb;
    const filterParams = (f) => new URLSearchParams(Array.from(new FormData(f)).filter(([k, v]) => k !== 's' && v !== '')).toString();
    body.addEventListener('input', (e) => { const f = e.target.closest('[data-mp-filter]'); if (!f) return; clearTimeout(deb); deb = setTimeout(() => load(filterParams(f)), e.target.type === 'search' ? 400 : 0); });
    body.addEventListener('submit', (e) => { const f = e.target.closest('[data-mp-filter]'); if (f) { e.preventDefault(); load(filterParams(f)); } });
    body.addEventListener('click', (e) => {
      const pg = e.target.closest('[data-mp-page]');
      if (pg) { e.preventDefault(); const u = new URL(pg.href, location.href); u.searchParams.delete('s'); u.searchParams.delete('parca'); load(u.searchParams.toString()); body.scrollTop = 0; return; }
      const t = e.target.closest('[data-mp-item]');
      if (!t) return;
      const p = t.dataset.mpItem;
      if (state.multi) { if (state.sel.has(p)) state.sel.delete(p); else state.sel.set(p, t.dataset.thumb); }
      else { state.sel.clear(); state.sel.set(p, t.dataset.thumb); }
      paint();
    });
    body.addEventListener('dblclick', (e) => { if (!state.multi && e.target.closest('[data-mp-item]')) okBtn.click(); });
    dlg.addEventListener('click', (e) => { if (e.target.closest('[data-mp-close]') || e.target === dlg) dlg.close(); });
    okBtn.addEventListener('click', () => { const out = Array.from(state.sel, ([path, thumb]) => ({ path, thumb })); dlg.close(); if (state.cb) state.cb(out); });
    mp = {
      open(multi, cb) {
        state.multi = multi; state.cb = cb; state.sel.clear();
        dlg.querySelector('h2').textContent = multi ? 'Galeriden görsel ekle' : 'Galeriden görsel seç';
        dlg.showModal();
        load(state.params);
      },
    };
    return mp;
  };

  // Görsel alanları
  const markDirty = (el) => { const f = el.closest('form'); if (f) f.dispatchEvent(new Event('input', { bubbles: true })); };
  const imgItem = (p, thumb, input) => {
    const fig = document.createElement('figure');
    fig.className = 'imgf__item'; fig.draggable = true;
    fig.innerHTML = '<img alt=""><input type="hidden"><button type="button" class="imgf__x" data-imgf-x aria-label="Kaldır" title="Kaldır">✕</button>';
    fig.querySelector('img').src = thumb || p;
    const h = fig.querySelector('input'); h.name = input; h.value = p;
    return fig;
  };
  document.addEventListener('click', (e) => {
    const pickBtn = e.target.closest('[data-imgf] [data-pick]');
    if (pickBtn) {
      const box = pickBtn.closest('[data-imgf]');
      const list = box.querySelector('[data-imgf-list]');
      const multi = box.dataset.multi === '1';
      picker().open(multi, (items) => {
        if (!multi) list.innerHTML = '';
        const have = new Set(Array.from(list.querySelectorAll('input')).map((i) => i.value));
        items.forEach((it) => { if (!have.has(it.path)) list.append(imgItem(it.path, it.thumb, box.dataset.input)); });
        markDirty(box);
      });
      return;
    }
    const x = e.target.closest('[data-imgf-x]');
    if (x) { const box = x.closest('[data-imgf]'); x.closest('.imgf__item').remove(); markDirty(box); return; }
    const ins = e.target.closest('[data-pick-insert]');
    if (ins) {
      const ta = document.querySelector(ins.dataset.pickInsert);
      if (!ta) return;
      const at = ta.selectionStart ?? ta.value.length;
      picker().open(true, (items) => {
        const text = items.map((it) => '![](' + it.path + ')').join('\n\n');
        const before = ta.value.slice(0, at), after = ta.value.slice(at);
        const pre = before && !before.endsWith('\n\n') ? (before.endsWith('\n') ? '\n' : '\n\n') : '';
        const post = after && !after.startsWith('\n') ? '\n\n' : '';
        ta.value = before + pre + text + post + after;
        ta.focus(); ta.selectionStart = ta.selectionEnd = (before + pre + text).length;
        markDirty(ta);
      });
    }
  });

  // Galerideki sırayı sürükleyerek değiştirme
  let dragged = null;
  document.addEventListener('dragstart', (e) => { const it = e.target.closest && e.target.closest('.imgf--multi .imgf__item'); if (it) { dragged = it; it.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; } });
  document.addEventListener('dragend', () => { if (dragged) { dragged.classList.remove('is-drag'); markDirty(dragged); } dragged = null; });
  document.addEventListener('dragover', (e) => {
    if (!dragged) return;
    const over = e.target.closest && e.target.closest('.imgf__item');
    if (!over || over === dragged || over.parentNode !== dragged.parentNode) return;
    e.preventDefault();
    const r = over.getBoundingClientRect();
    over.parentNode.insertBefore(dragged, e.clientX < r.left + r.width / 2 ? over : over.nextSibling);
  });

  // Galeri sayfası: toplu seçim
  $$('[data-bulk]').forEach((f) => {
    const bar = f.querySelector('[data-bulk-bar]');
    const count = f.querySelector('[data-bulk-count]');
    const items = () => $$('[data-bulk-item]', f);
    const sync = () => {
      const n = items().filter((i) => i.checked).length;
      bar.hidden = !n; count.textContent = n + ' seçili';
      items().forEach((i) => i.closest('.mcard').classList.toggle('is-on', i.checked));
    };
    f.addEventListener('change', (e) => {
      if (e.target.matches('[data-bulk-all]')) items().forEach((i) => { i.checked = e.target.checked; });
      sync();
    });
    f.addEventListener('click', (e) => { if (e.target.closest('[data-bulk-clear]')) { items().forEach((i) => { i.checked = false; }); const a = f.querySelector('[data-bulk-all]'); if (a) a.checked = false; sync(); } });
    f.addEventListener('submit', (e) => { if (f.op.value === 'sil' && !confirm('Seçilen görseller kalıcı olarak silinecek. Sitede kullanılanlar silinmez. Emin misiniz?')) e.preventDefault(); });
    sync();
  });

  // Filtreler değişince listeyi yenile
  $$('form[data-autosubmit]').forEach((f) => {
    let t;
    f.addEventListener('change', (e) => { if (e.target.matches('select')) f.submit(); });
    f.addEventListener('input', (e) => { if (e.target.type === 'search') { clearTimeout(t); t = setTimeout(() => f.submit(), 700); } });
  });

  // Kopyala
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-copy]');
    if (!b) return;
    try { await navigator.clipboard.writeText(new URL(b.dataset.copy, location.href).href); b.textContent = 'Kopyalandı'; } catch { /* eski tarayıcı */ }
  });

  // Grafikler en yeni güne kaydırılmış açılsın
  $$('.chart-wrap').forEach((el) => { el.scrollLeft = el.scrollWidth; });
})();

// Katılımlar: toplu seçim ve silme
(() => {
  const all = document.querySelector('[data-regsel-all]');
  const btn = document.querySelector('[data-regsel-btn]');
  const boxes = [...document.querySelectorAll('[data-regsel]')];
  if (!btn || !boxes.length) return;
  const sync = () => { const n = boxes.filter((b) => b.checked).length; btn.disabled = !n; btn.textContent = n ? 'Seçilenleri sil (' + n + ')' : 'Seçilenleri sil'; if (all) all.checked = n === boxes.length; };
  boxes.forEach((b) => b.addEventListener('change', sync));
  if (all) all.addEventListener('change', () => { boxes.forEach((b) => { b.checked = all.checked; }); sync(); });
})();
