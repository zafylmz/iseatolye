// İSE ATÖLYE: sayfa davranışları. Kütüphane kullanılmaz; her parça yalnızca ilgili öğe sayfadaysa çalışır.
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  const setMsg = (el, text, ok) => {
    if (!el) return;
    el.textContent = text || '';
    el.classList.toggle('is-ok', !!text && ok);
    el.classList.toggle('is-err', !!text && !ok);
  };

  const postJson = async (form, extra) => {
    const body = new FormData(form);
    if (extra) for (const [k, v] of Object.entries(extra)) body.set(k, v);
    const res = await fetch(form.getAttribute('action') || location.href, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    try { return await res.json(); } catch { return { ok: false, message: 'Bir sorun oluştu. Sayfayı yenileyip tekrar deneyin.' }; }
  };

  // ---------- Görünür olunca beliren bölümler ----------
  const reveals = $$('[data-reveal]');
  if (reveals.length) {
    if (reduce || !('IntersectionObserver' in window)) reveals.forEach((el) => el.classList.add('is-in'));
    else {
      const io = new IntersectionObserver((entries) => {
        entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
      reveals.forEach((el) => io.observe(el));
    }
  }

  // ---------- Mobil menü ----------
  const menuBtn = $('.menu-btn');
  const nav = $('#menu');
  if (menuBtn && nav) {
    const setMenu = (open) => {
      nav.classList.toggle('is-open', open);
      menuBtn.setAttribute('aria-expanded', String(open));
      menuBtn.setAttribute('aria-label', open ? 'Menüyü kapat' : 'Menüyü aç');
      document.body.classList.toggle('has-menu', open);
    };
    menuBtn.addEventListener('click', () => setMenu(!nav.classList.contains('is-open')));
    nav.addEventListener('click', (e) => { if (e.target.closest('a')) setMenu(false); });
    addEventListener('keydown', (e) => { if (e.key === 'Escape' && nav.classList.contains('is-open')) { setMenu(false); menuBtn.focus(); } });
    matchMedia('(min-width: 1080px)').addEventListener('change', (m) => { if (m.matches) setMenu(false); });
  }

  // ---------- Üye menüsü ----------
  const umBtn = $('.usermenu__btn');
  const umPanel = $('#usermenu');
  if (umBtn && umPanel) {
    const setUm = (open) => { umPanel.hidden = !open; umBtn.setAttribute('aria-expanded', String(open)); };
    umBtn.addEventListener('click', (e) => { e.stopPropagation(); setUm(umPanel.hidden); });
    document.addEventListener('click', (e) => { if (!umPanel.hidden && !e.target.closest('.usermenu')) setUm(false); });
    addEventListener('keydown', (e) => { if (e.key === 'Escape' && !umPanel.hidden) { setUm(false); umBtn.focus(); } });
  }

  // ---------- Takvim: ay değiştirme (sayfada ve açılır pencerede) ----------
  const loadMonth = async (holder, ym, push) => {
    const cal = $('[data-cal]', holder);
    if (cal) cal.classList.add('is-loading');
    try {
      const res = await fetch('/takvim/?ay=' + encodeURIComponent(ym) + '&parca=1', { credentials: 'same-origin' });
      if (!res.ok) throw new Error();
      holder.innerHTML = await res.text();
      if (push) history.replaceState(null, '', ym === new Date().toISOString().slice(0, 7) ? '/takvim/' : '/takvim/?ay=' + ym);
    } catch {
      location.href = '/takvim/?ay=' + encodeURIComponent(ym);
    }
  };
  const bindCal = (holder, push) => {
    holder.addEventListener('click', (e) => {
      const go = e.target.closest('[data-cal-go]');
      if (!go || e.metaKey || e.ctrlKey || e.shiftKey) return;
      e.preventDefault();
      loadMonth(holder, go.dataset.calGo, push);
    });
  };
  const calPage = $('[data-cal-page]');
  if (calPage) bindCal(calPage, true);

  const modal = $('#takvim-penceresi');
  if (modal) {
    const body = $('[data-cal-body]', modal);
    bindCal(body, false);
    let opener = null;
    const close = () => { if (modal.open) modal.close(); };
    modal.addEventListener('close', () => { document.body.classList.remove('has-modal'); if (opener) opener.focus(); });
    $$('[data-cal-open]').forEach((b) => b.addEventListener('click', (e) => {
      if (typeof modal.showModal !== 'function') return; // eski tarayıcı: bağlantı takvim sayfasına gider
      e.preventDefault();
      opener = b;
      modal.showModal();
      document.body.classList.add('has-modal');
    }));
    $$('[data-cal-close]', modal).forEach((b) => b.addEventListener('click', close));
    // Pencerenin dışına (arka plana) tıklayınca kapanır
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
  }

  // ---------- Filtreler: seçim değişince gönder ----------
  $$('form[data-autosubmit]').forEach((f) => {
    let t;
    f.addEventListener('change', (e) => { if (e.target.matches('select, input[type=checkbox], input[type=radio]')) f.requestSubmit ? f.requestSubmit() : f.submit(); });
    f.addEventListener('input', (e) => {
      if (e.target.type !== 'search') return;
      clearTimeout(t);
      t = setTimeout(() => { if (e.target.value.trim().length !== 1) f.requestSubmit ? f.requestSubmit() : f.submit(); }, 600);
    });
  });

  // ---------- Bot koruması: tarayıcıda küçük bir işlem çözülür ----------
  const sha256 = async (s) => {
    const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(s));
    return Array.from(new Uint8Array(buf), (b) => b.toString(16).padStart(2, '0')).join('');
  };
  const solve = async (sig) => {
    for (let n = 0; n < 5e6; n++) {
      if ((await sha256(sig + ':' + n)).startsWith('000')) return String(n);
    }
    return '';
  };
  const loadedAt = Date.now();
  $$('form[data-guard-form]').forEach((form) => {
    const sigEl = form.querySelector('[name=sig]');
    const powEl = form.querySelector('[name=pow]');
    if (!sigEl || !powEl) return;
    let solving = null;
    const ensure = () => (solving ||= (window.crypto && crypto.subtle ? solve(sigEl.value) : Promise.resolve('')).then((p) => { powEl.value = p; }));
    form.addEventListener('focusin', ensure, { once: true });
    form.addEventListener('submit', async (e) => {
      if (form.dataset.ready === '1') return;
      e.preventDefault();
      if (!form.checkValidity()) { form.reportValidity(); return; }
      const msg = form.querySelector('[data-form-msg]');
      const btn = form.querySelector('[type=submit]');
      if (btn) btn.disabled = true;
      setMsg(msg, 'Gönderiliyor…', true);
      await ensure();
      const wait = 3500 - (Date.now() - loadedAt);
      if (wait > 0) await new Promise((r) => setTimeout(r, wait));
      // Blog yorumu sayfa yenilenmeden gönderilir; diğer formlar normal gönderilir
      if (form.classList.contains('comment-form')) {
        const d = await postJson(form, { action: 'comment' });
        setMsg(msg, d.message, d.ok);
        if (btn) btn.disabled = false;
        if (d.ok) form.reset();
        return;
      }
      form.dataset.ready = '1';
      if (btn) btn.disabled = false;
      // requestSubmit, iptal edilmiş ilk gönderimden sonra Chrome'da formu göndermiyor; doğrudan gönderilir
      if (btn && btn.name) { const h = document.createElement('input'); h.type = 'hidden'; h.name = btn.name; h.value = btn.value; form.append(h); }
      HTMLFormElement.prototype.submit.call(form);
    });
  });

  // ---------- Katılmayı düşünüyorum ----------
  $$('form[data-interest]').forEach((form) => {
    const box = form.closest('[data-interest-box]');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('button');
      btn.disabled = true;
      const d = await postJson(form);
      btn.disabled = false;
      if (!d.ok) { alert(d.message); return; }
      btn.classList.toggle('is-on', d.on);
      btn.setAttribute('aria-pressed', String(d.on));
      btn.querySelector('span').textContent = d.on ? 'Düşünüyorum' : 'Katılmayı düşünüyorum';
      if (!box) return;
      const count = $('[data-interest-count]', box);
      if (count) count.textContent = d.count;
      const faces = $('[data-interest-faces]', box);
      if (faces) {
        const mine = $('[data-me]', faces);
        if (d.on && !mine) {
          const a = document.createElement('a');
          a.href = btn.dataset.meUrl; a.title = 'Siz'; a.dataset.me = '';
          a.innerHTML = btn.dataset.meAvatar;
          faces.prepend(a);
        } else if (!d.on && mine) mine.remove();
      }
    });
  });

  // ---------- Etkinlik yorumları ----------
  const cform = $('form[data-comment]');
  if (cform) {
    const parent = cform.querySelector('[name=parent]');
    const replyTo = $('[data-reply-to]', cform);
    const cancel = $('[data-reply-cancel]', cform);
    const ta = cform.querySelector('textarea');
    const msg = $('[data-form-msg]', cform);
    const resetReply = () => { parent.value = ''; replyTo.hidden = true; replyTo.textContent = ''; cancel.hidden = true; };
    document.addEventListener('click', (e) => {
      const r = e.target.closest('[data-reply]');
      if (!r) return;
      parent.value = r.dataset.reply;
      replyTo.textContent = ' · ' + r.dataset.replyName + ' adlı kişiye yanıt';
      replyTo.hidden = false; cancel.hidden = false;
      cform.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
      ta.focus({ preventScroll: true });
    });
    cancel.addEventListener('click', resetReply);
    cform.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!cform.checkValidity()) { cform.reportValidity(); return; }
      const btn = cform.querySelector('[type=submit]');
      btn.disabled = true;
      setMsg(msg, 'Gönderiliyor…', true);
      const d = await postJson(cform);
      btn.disabled = false;
      setMsg(msg, d.message, d.ok);
      if (!d.ok) return;
      ta.value = '';
      if (d.html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = d.html.trim();
        const li = tmp.firstElementChild;
        if (d.parent) {
          const host = document.getElementById('yorum-' + d.parent);
          let sub = host && host.querySelector(':scope > .clist--sub');
          if (host && !sub) { sub = document.createElement('ol'); sub.className = 'clist clist--sub'; host.append(sub); }
          (sub || document.body).append(li);
        } else {
          let list = $('#yorumlar .clist');
          if (!list) {
            list = document.createElement('ol'); list.className = 'clist';
            const empty = $('#yorumlar > p.muted');
            if (empty) empty.replaceWith(list); else cform.before(list);
          }
          list.append(li);
        }
        li.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
      }
      resetReply();
    });
  }

  // ---------- Katılım formu: adet ve özet ----------
  const join = $('form[data-join]');
  if (join) {
    const qty = $('#qty', join);
    const others = $('[data-others]', join);
    const sumT = $('[data-sum-ticket]');
    const sumQ = $('[data-sum-qty]');
    const sumTotal = $('[data-sum-total]');
    const fmt = (n) => n.toLocaleString('tr-TR', { maximumFractionDigits: 2 }) + ' ₺';
    const update = () => {
      const max = parseInt(qty.max || '10', 10) || 10;
      let q = parseInt(qty.value || '1', 10);
      if (!(q >= 1)) q = 1;
      if (q > max) q = max;
      if (String(q) !== qty.value) qty.value = q;
      const t = join.querySelector('input[name=ticket]:checked');
      const price = t ? parseFloat(t.dataset.price || '0') : 0;
      const seats = t ? parseInt(t.dataset.seats || '1', 10) : 1;
      if (sumT) sumT.textContent = t ? t.closest('label').querySelector('strong').textContent : '–';
      if (sumQ) sumQ.textContent = q + (seats > 1 ? ' (' + q * seats + ' kişi)' : '');
      if (sumTotal) sumTotal.textContent = !t ? '–' : price > 0 ? fmt(price * q) : 'Ücretsiz';
      if (others) others.hidden = q * seats < 2;
      $$('[data-qty]', join).forEach((b) => { b.disabled = b.dataset.qty === '-1' ? q <= 1 : q >= max; });
    };
    $$('[data-qty]', join).forEach((b) => b.addEventListener('click', () => {
      qty.value = (parseInt(qty.value || '1', 10) || 1) + parseInt(b.dataset.qty, 10);
      update();
    }));
    join.addEventListener('input', update);
    join.addEventListener('change', update);
    update();
    // Üye formunda çift gönderimi önle (misafir formunu bot koruması yönetir)
    if (!join.hasAttribute('data-guard-form')) join.addEventListener('submit', () => {
      const b = $('[data-join-btn]', join);
      if (b) setTimeout(() => { b.disabled = true; }, 0);
    });
  }

  // ---------- İletişim formu: kurumsal alanlar ----------
  $$('.cform-contact').forEach((f) => {
    const sync = () => { const t = f.querySelector('[data-topic]:checked'); f.classList.toggle('is-corp', !!t && t.value === 'kurumsal'); };
    f.addEventListener('change', sync);
    sync();
  });

  // ---------- Büyük görsel (aynı gruptaki fotoğraflar arasında oklarla, kaydırarak geçilir) ----------
  const lightbox = (items, start) => {
    let i = start;
    const box = document.createElement('div');
    box.className = 'lightbox';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    const many = items.length > 1;
    box.innerHTML = '<figure class="lightbox__fig"><img alt=""><figcaption></figcaption></figure><button type="button" class="lightbox__close" aria-label="Kapat">✕</button>'
      + (many ? '<button type="button" class="lightbox__nav lightbox__nav--prev" aria-label="Önceki">‹</button><button type="button" class="lightbox__nav lightbox__nav--next" aria-label="Sonraki">›</button><span class="lightbox__count"></span>' : '');
    const img = box.querySelector('img');
    const cap = box.querySelector('figcaption');
    const count = box.querySelector('.lightbox__count');
    const show = () => {
      const it = items[i];
      img.src = it.src; img.alt = it.cap || '';
      cap.textContent = it.cap || ''; cap.hidden = !it.cap;
      if (count) count.textContent = (i + 1) + ' / ' + items.length;
      [items[i + 1], items[i - 1]].forEach((n) => { if (n) { const p = new Image(); p.src = n.src; } });
    };
    const go = (d) => { if (!many) return; i = (i + d + items.length) % items.length; show(); };
    const prev = document.activeElement;
    const close = () => { box.remove(); document.body.classList.remove('has-modal'); removeEventListener('keydown', key); if (prev) prev.focus(); };
    const key = (e) => { if (e.key === 'Escape') close(); else if (e.key === 'ArrowRight') go(1); else if (e.key === 'ArrowLeft') go(-1); };
    box.addEventListener('click', (e) => {
      if (e.target.closest('.lightbox__nav--prev')) return go(-1);
      if (e.target.closest('.lightbox__nav--next')) return go(1);
      if (e.target !== img) close();
    });
    let x0 = null;
    box.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', (e) => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1); x0 = null; });
    addEventListener('keydown', key);
    show();
    document.body.append(box);
    document.body.classList.add('has-modal');
    box.querySelector('.lightbox__close').focus();
  };
  $$('img[data-zoom]').forEach((img) => { img.style.cursor = 'zoom-in'; img.addEventListener('click', () => lightbox([{ src: img.currentSrc || img.src, cap: img.alt }], 0)); });
  $$('[data-zoom-src]').forEach((b) => b.addEventListener('click', () => {
    const group = b.closest('[data-zoom-group]');
    const all = group ? $$('[data-zoom-src]', group) : [b];
    lightbox(all.map((x) => ({ src: x.dataset.zoomSrc, cap: x.dataset.zoomCap || '' })), Math.max(0, all.indexOf(b)));
  }));

  // ---------- Paylaş ve kopyala ----------
  const copy = async (text, btn) => {
    try { await navigator.clipboard.writeText(text); } catch {
      const ta = document.createElement('textarea'); ta.value = text; document.body.append(ta); ta.select(); document.execCommand('copy'); ta.remove();
    }
    if (btn) { const old = btn.textContent; btn.textContent = 'Kopyalandı'; setTimeout(() => { btn.textContent = old; }, 1600); }
  };
  $$('[data-copy]').forEach((b) => b.addEventListener('click', () => copy(b.dataset.copy, b)));
  $$('[data-share]').forEach((b) => b.addEventListener('click', async () => {
    const data = { title: b.dataset.title || document.title, url: b.dataset.url || location.href };
    if (navigator.share) { try { await navigator.share(data); } catch {} return; }
    await copy(data.url);
    const label = b.lastChild;
    if (label && label.nodeType === 3) { const old = label.textContent; label.textContent = 'Bağlantı kopyalandı'; setTimeout(() => { label.textContent = old; }, 1800); }
  }));

  // ---------- Blog: beğeni, okuma çubuğu, içindekiler, arama ----------
  const like = $('form[data-like]');
  if (like) like.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = like.querySelector('button');
    btn.disabled = true;
    const d = await postJson(like);
    btn.disabled = false;
    if (!d.ok) { $('[data-like-hint]', like).textContent = d.message; return; }
    btn.setAttribute('aria-pressed', String(d.liked));
    $('[data-like-count]', like).textContent = d.count;
    $('[data-like-hint]', like).textContent = d.message;
  });

  const bar = $('.read-progress span');
  const postBody = $('[data-post-body]');
  if (bar && postBody) {
    let tick = false;
    const draw = () => {
      tick = false;
      const r = postBody.getBoundingClientRect();
      const total = r.height - innerHeight * 0.6;
      const p = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : 1;
      bar.style.setProperty('--p', p.toFixed(3));
    };
    addEventListener('scroll', () => { if (!tick) { tick = true; requestAnimationFrame(draw); } }, { passive: true });
    draw();
  }

  const toc = $('[data-toc]');
  if (toc && 'IntersectionObserver' in window) {
    const links = $$('a', toc);
    const map = new Map(links.map((a) => [decodeURIComponent(a.hash.slice(1)), a]));
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (!en.isIntersecting) return;
        links.forEach((a) => a.classList.remove('is-active'));
        const a = map.get(en.target.id);
        if (a) a.classList.add('is-active');
      });
    }, { rootMargin: '0px 0px -70% 0px' });
    map.forEach((_, id) => { const h = document.getElementById(id); if (h) io.observe(h); });
  }

  const sideSearch = $('[data-side-search]');
  if (sideSearch) {
    const items = $$('[data-side-list] > li');
    const empty = $('[data-side-empty]');
    const norm = (s) => s.toLocaleLowerCase('tr-TR').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i');
    sideSearch.addEventListener('input', () => {
      const q = norm(sideSearch.value.trim());
      let shown = 0;
      items.forEach((li) => { const ok = !q || norm(li.textContent).includes(q); li.hidden = !ok; if (ok) shown++; });
      if (empty) empty.hidden = shown > 0;
    });
  }
})();

// Fatura türü: bireysel / şirket adına alanlarını değiştir
document.querySelectorAll('[data-inv]').forEach((box) => {
  const sync = () => {
    const t = (box.querySelector('[data-inv-type]:checked') || {}).value || 'bireysel';
    box.querySelectorAll('[data-inv-group]').forEach((g) => { g.hidden = g.dataset.invGroup !== t; });
  };
  box.querySelectorAll('[data-inv-type]').forEach((r) => r.addEventListener('change', sync));
  sync();
});
