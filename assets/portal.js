/* Faculty Opportunities Portal v2 — scroll interactions (no dependencies) */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement;
  root.classList.add('fcp-js');
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var vh = function () { return window.innerHeight || root.clientHeight; };
  var clamp = function (n, a, b) { return Math.min(b, Math.max(a, n)); };
  var $ = function (s, c) { return (c || doc).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); };

  /* ---- Nav: border + mobile menu ---- */
  var nav = $('#fcp-nav');
  var menuBtn = $('[data-fcp-menu]');
  if (menuBtn && nav) {
    menuBtn.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      doc.body.style.overflow = open ? 'hidden' : '';
    });
  }

  /* ---- Search overlay (REST, debounced) ---- */
  var overlay = $('#fcp-search'), input = $('#fcp-search-input'), list = $('#fcp-search-results'), hint = $('#fcp-search-hint');
  var timer = null, seq = 0;
  function openSearch() {
    if (!overlay) return;
    overlay.hidden = false; doc.body.classList.add('fcp-search-open');
    setTimeout(function () { input && input.focus(); }, 30);
  }
  function closeSearch() {
    if (!overlay) return;
    overlay.hidden = true; doc.body.classList.remove('fcp-search-open');
  }
  function render(items) {
    list.textContent = '';
    if (!items.length) { hint.textContent = (window.FCP && FCP.noMatch) || ''; return; }
    hint.textContent = '';
    items.forEach(function (it) {
      var li = doc.createElement('li'), a = doc.createElement('a'), s = doc.createElement('strong'), m = doc.createElement('small');
      a.href = it.url; s.textContent = it.title;
      m.textContent = [it.category, it.author, it.note].filter(Boolean).join(' · ');
      a.appendChild(s); a.appendChild(m); li.appendChild(a); list.appendChild(li);
    });
  }
  function runSearch(q) {
    var my = ++seq;
    fetch(FCP.search + (FCP.search.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q), { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (d) { if (my === seq) render(Array.isArray(d) ? d : []); })
      .catch(function () { if (my === seq) { list.textContent = ''; hint.textContent = FCP.error; } });
  }
  $$('[data-fcp-search-open]').forEach(function (b) { b.addEventListener('click', openSearch); });
  $$('[data-fcp-search-close]').forEach(function (b) { b.addEventListener('click', closeSearch); });
  if (input) {
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { list.textContent = ''; hint.textContent = (window.FCP && FCP.hint) || ''; return; }
      timer = setTimeout(function () { runSearch(q); }, 220);
    });
  }
  doc.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeSearch(); if (nav && nav.classList.contains('is-open')) menuBtn.click(); }
    if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test((doc.activeElement || {}).tagName || '')) { e.preventDefault(); openSearch(); }
  });

  /* ---- Word-by-word reveal ---- */
  var words = [];
  $$('[data-fcp-words]').forEach(function (sec) {
    var p = $('p', sec);
    if (!p || sec.classList.contains('is-static')) return;
    var text = p.textContent.trim().split(/\s+/);
    p.textContent = '';
    var spans = text.map(function (w, i) {
      var s = doc.createElement('span'); s.className = 'w'; s.textContent = w;
      p.appendChild(s); if (i < text.length - 1) p.appendChild(doc.createTextNode(' '));
      return s;
    });
    words.push({ sec: sec, spans: spans });
  });

  /* ---- Stage, story, local nav (rAF-driven) ---- */
  var stage = $('[data-fcp-stage]');
  var story = $('[data-fcp-story]');
  var steps = story ? $$('.fcp-steps li', story) : [];
  var panes = story ? $$('.fcp-pane', story) : [];
  var localnav = $('[data-fcp-localnav]');
  var anchor = $('[data-fcp-localnav-anchor]');
  var ticking = false, lastStep = -1;

  function update() {
    ticking = false;
    var y = window.pageYOffset || root.scrollTop;
    if (nav) nav.classList.toggle('is-scrolled', y > 8);

    if (stage && !reduce) {
      var r = stage.getBoundingClientRect();
      var p = clamp((vh() - r.top) / (vh() * 0.78), 0, 1);
      stage.style.setProperty('--p', p.toFixed(3));
    }
    if (!reduce) {
      words.forEach(function (w) {
        var r2 = w.sec.getBoundingClientRect();
        var prog = clamp((vh() * 0.82 - r2.top) / (vh() * 0.5 + r2.height * 0.6), 0, 1);
        var n = Math.round(prog * w.spans.length);
        w.spans.forEach(function (s, i) { s.classList.toggle('on', i < n); });
      });
    }
    if (story && steps.length && !reduce) {
      var rs = story.getBoundingClientRect();
      var total = rs.height - vh();
      var prog2 = total > 0 ? clamp(-rs.top / total, 0, 0.999) : 0;
      var idx = Math.floor(prog2 * steps.length);
      if (idx !== lastStep) {
        lastStep = idx;
        steps.forEach(function (s, i) { s.classList.toggle('is-active', i === idx); });
        panes.forEach(function (s, i) { s.classList.toggle('is-active', i === idx); });
      }
    }
    if (localnav && anchor) {
      localnav.classList.toggle('is-visible', anchor.getBoundingClientRect().bottom < 0);
    }
  }
  function onScroll() { if (!ticking) { ticking = true; requestAnimationFrame(update); } }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  update();

  /* ---- Counters ---- */
  var fmt = function (n) { try { return n.toLocaleString('fa-IR'); } catch (e) { return String(n); } };
  var counters = $$('[data-fcp-count]');
  function runCount(el) {
    var target = parseInt(el.getAttribute('data-fcp-count'), 10) || 0, t0 = null, dur = 1400;
    if (reduce || target === 0) { el.textContent = fmt(target); return; }
    function tick(t) {
      if (t0 === null) t0 = t;
      var k = clamp((t - t0) / dur, 0, 1), eased = 1 - Math.pow(1 - k, 4);
      el.textContent = fmt(Math.round(target * eased));
      if (k < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  if (counters.length && 'IntersectionObserver' in window) {
    counters.forEach(function (c) { c.textContent = fmt(0); });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { runCount(en.target); io.unobserve(en.target); }
      });
    }, { threshold: 0.6 });
    counters.forEach(function (c) { io.observe(c); });
  }

  /* ---- Horizontal shelf ---- */
  $$('[data-fcp-shelf]').forEach(function (wrap) {
    var track = $('.fcp-shelf', wrap), btns = $$('.fcp-shelf-nav button', wrap);
    if (!track || !btns.length) return;
    var rtl = getComputedStyle(track).direction === 'rtl';
    function sync() {
      var max = track.scrollWidth - track.clientWidth - 2;
      var pos = Math.abs(track.scrollLeft);
      btns.forEach(function (b) {
        var d = parseInt(b.getAttribute('data-dir'), 10); // 1 = back toward start, -1 = forward
        b.disabled = d === 1 ? pos <= 2 : pos >= max;
      });
    }
    btns.forEach(function (b) {
      b.addEventListener('click', function () {
        var d = parseInt(b.getAttribute('data-dir'), 10);
        var step = Math.min(360, track.clientWidth * 0.8) * (rtl ? d : -d);
        track.scrollBy({ left: step, behavior: reduce ? 'auto' : 'smooth' });
      });
    });
    track.addEventListener('scroll', function () { requestAnimationFrame(sync); }, { passive: true });
    sync();
  });
})();

/* ===================================================================
   v2.1 — Advice: tabs, filters, helpful votes, bookmarks, mouse effects
   =================================================================== */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fine = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var T = window.FCP || {};
  var vh = function () { return window.innerHeight || root.clientHeight; };
  var clamp = function (n, a, b) { return Math.min(b, Math.max(a, n)); };
  var $ = function (s, c) { return (c || doc).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); };
  var fmt = function (n) { try { return Number(n).toLocaleString('fa-IR'); } catch (e) { return String(n); } };

  /* ---- Toast ---- */
  var toastEl = null, toastTimer = null;
  function toast(msg) {
    if (!msg) return;
    if (!toastEl) {
      toastEl = doc.createElement('div');
      toastEl.className = 'fcp-toast';
      toastEl.setAttribute('role', 'status');
      doc.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.classList.add('is-show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-show'); }, 2200);
  }

  /* ---- Bookmarks (private to this browser, localStorage) ---- */
  var KEY = 'fcp_saved_advice';
  function loadSaved() {
    try {
      var a = JSON.parse(localStorage.getItem(KEY) || '[]');
      return Array.isArray(a) ? a.filter(function (n) { return typeof n === 'number' && isFinite(n); }) : [];
    } catch (e) { return []; }
  }
  var saved = loadSaved();
  function storeSaved() { try { localStorage.setItem(KEY, JSON.stringify(saved.slice(-200))); } catch (e) { /* storage blocked */ } }
  function isSaved(id) { return saved.indexOf(id) > -1; }
  function paintSaved() {
    $$('[data-fcp-save]').forEach(function (b) {
      var on = isSaved(parseInt(b.getAttribute('data-fcp-save'), 10));
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    $$('[data-fcp-saved-n]').forEach(function (n) { n.textContent = fmt(saved.length); });
  }
  paintSaved();

  doc.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-fcp-save]');
    if (b) {
      e.preventDefault();
      var id = parseInt(b.getAttribute('data-fcp-save'), 10);
      if (!id) return;
      var i = saved.indexOf(id);
      if (i > -1) { saved.splice(i, 1); toast(T.unsaved); } else { saved.push(id); toast(T.saved); }
      storeSaved(); paintSaved();
      doc.dispatchEvent(new CustomEvent('fcp:saved'));
      return;
    }
    var c = e.target.closest && e.target.closest('[data-fcp-copy]');
    if (c) {
      var url = c.getAttribute('data-fcp-copy');
      var done = function () { toast(T.copied); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done, function () { fallbackCopy(url); done(); });
      } else { fallbackCopy(url); done(); }
      return;
    }
    var h = e.target.closest && e.target.closest('[data-fcp-helpful]');
    if (h) { vote(h); }
  });
  function fallbackCopy(text) {
    var ta = doc.createElement('textarea');
    ta.value = text; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;opacity:0;top:0';
    doc.body.appendChild(ta); ta.select();
    try { doc.execCommand('copy'); } catch (err) { /* ignore */ }
    doc.body.removeChild(ta);
  }

  /* ---- "Helpful" vote (server decides; UI follows the response) ---- */
  function vote(btn) {
    if (btn.classList.contains('is-busy') || !T.vote) return;
    var id = parseInt(btn.getAttribute('data-fcp-helpful'), 10);
    if (!id) return;
    btn.classList.add('is-busy');
    fetch(T.vote + id + '/helpful', { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (d) {
        var on = !!d.voted;
        $$('[data-fcp-helpful="' + id + '"]').forEach(function (b) {
          b.classList.toggle('is-on', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
          var n = $('[data-fcp-helpful-n]', b);
          if (n) n.textContent = fmt(d.count);
        });
        if (on && !reduce) {
          btn.classList.remove('is-pop'); void btn.offsetWidth; btn.classList.add('is-pop');
        }
      })
      .catch(function () { toast(T.voteErr); })
      .then(function () { btn.classList.remove('is-busy'); });
  }

  /* ---- Mouse: spotlight glow + 3D tilt + hero parallax ---- */
  doc.addEventListener('pointermove', function (e) {
    if (e.pointerType === 'touch') return;
    var el = e.target.closest && e.target.closest('[data-fcp-glow]');
    if (!el) return;
    var r = el.getBoundingClientRect();
    el.style.setProperty('--mx', (e.clientX - r.left) + 'px');
    el.style.setProperty('--my', (e.clientY - r.top) + 'px');
    if (!reduce && fine && el.hasAttribute('data-fcp-tilt')) {
      var px = (e.clientX - r.left) / r.width - 0.5, py = (e.clientY - r.top) / r.height - 0.5;
      el.style.setProperty('--ry', (px * 9).toFixed(2) + 'deg');
      el.style.setProperty('--rx', (-py * 9).toFixed(2) + 'deg');
    }
  }, { passive: true });
  $$('[data-fcp-tilt]').forEach(function (el) {
    el.addEventListener('pointerleave', function () { el.style.setProperty('--rx', '0deg'); el.style.setProperty('--ry', '0deg'); });
  });
  $$('[data-fcp-parallax]').forEach(function (el) {
    if (reduce || !fine) return;
    el.addEventListener('pointermove', function (e) {
      var r = el.getBoundingClientRect();
      el.style.setProperty('--ox', (((e.clientX - r.left) / r.width - 0.5) * 2).toFixed(3));
      el.style.setProperty('--oy', (((e.clientY - r.top) / r.height - 0.5) * 2).toFixed(3));
    }, { passive: true });
  });

  /* ---- Advice listing: tabs, topic chips, search, sort, saved ---- */
  $$('[data-fcp-advice]').forEach(function (rootEl) {
    var tabs = $$('[data-fcp-tab]', rootEl), panels = $$('[data-fcp-panel]', rootEl), thumb = $('.fcp-tabs-thumb', rootEl);
    var search = $('[data-fcp-adv-search]', rootEl), sortBtns = $$('[data-fcp-sort]', rootEl);
    var savedBtn = $('[data-fcp-saved-toggle]', rootEl), empty = $('[data-fcp-adv-empty]', rootEl);
    var st = { aud: rootEl.getAttribute('data-active') || 'newcomer', topic: rootEl.getAttribute('data-topic') || 'all', q: '', sort: 'new', saved: false };

    function panel() { return panels.filter(function (p) { return p.getAttribute('data-fcp-panel') === st.aud; })[0]; }
    function moveThumb(instant) {
      var t = tabs.filter(function (x) { return x.getAttribute('data-fcp-tab') === st.aud; })[0];
      if (!t || !thumb) return;
      if (instant) thumb.style.transition = 'none';
      thumb.style.width = t.offsetWidth + 'px';
      thumb.style.transform = 'translateX(' + t.offsetLeft + 'px)';
      if (instant) { void thumb.offsetWidth; thumb.style.transition = ''; }
    }
    var ready = false;
    function syncUrl() {
      if (!ready || !window.history || !history.replaceState) return;
      try {
        var u = new URL(window.location.href);
        u.searchParams.set('for', st.aud);
        if (st.topic && st.topic !== 'all') u.searchParams.set('topic', st.topic); else u.searchParams.delete('topic');
        history.replaceState(null, '', u.toString());
      } catch (err) { /* old browser */ }
    }
    function apply() {
      var p = panel();
      if (!p) return;
      var grid = $('[data-fcp-grid]', p);
      var cards = grid ? $$('.fcp-advice-card', grid) : [];
      cards.sort(function (a, b) {
        var ta = +a.getAttribute('data-time'), tb = +b.getAttribute('data-time');
        if (st.sort === 'helpful') {
          var d = (+b.getAttribute('data-helpful')) - (+a.getAttribute('data-helpful'));
          if (d) return d;
        }
        return tb - ta;
      }).forEach(function (c) { grid.appendChild(c); });
      var shown = 0, q = st.q.toLowerCase();
      cards.forEach(function (c) {
        var ok = (st.topic === 'all' || c.getAttribute('data-topic') === st.topic) &&
                 (!q || (c.getAttribute('data-title') || '').indexOf(q) > -1) &&
                 (!st.saved || isSaved(parseInt(c.getAttribute('data-id'), 10)));
        c.hidden = !ok;
        if (ok) shown++;
      });
      $$('[data-fcp-topic]', p).forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-fcp-topic') === st.topic); });
      if (empty) { empty.hidden = !(cards.length && !shown); if (!empty.hidden) { var m = $('p', empty); if (m && T.noAdvice) m.textContent = T.noAdvice; } }
    }
    function setAud(aud, keepTopic) {
      st.aud = aud;
      if (!keepTopic) st.topic = 'all';
      tabs.forEach(function (t) {
        var on = t.getAttribute('data-fcp-tab') === aud;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
      });
      panels.forEach(function (p) { p.hidden = p.getAttribute('data-fcp-panel') !== aud; });
      moveThumb(false);
      apply();
      syncUrl();
    }
    tabs.forEach(function (t) {
      t.addEventListener('click', function (e) { e.preventDefault(); setAud(t.getAttribute('data-fcp-tab'), false); });
      t.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        e.preventDefault();
        var i = tabs.indexOf(t), n = tabs.length;
        var next = tabs[(i + (e.key === 'ArrowLeft' ? 1 : -1) + n) % n];
        setAud(next.getAttribute('data-fcp-tab'), false); next.focus();
      });
    });
    rootEl.addEventListener('click', function (e) {
      var chip = e.target.closest && e.target.closest('[data-fcp-topic]');
      if (chip) { st.topic = chip.getAttribute('data-fcp-topic'); apply(); syncUrl(); }
    });
    var st_timer = null;
    if (search) search.addEventListener('input', function () {
      clearTimeout(st_timer);
      st_timer = setTimeout(function () { st.q = search.value.trim().slice(0, 60); apply(); }, 120);
    });
    sortBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        st.sort = b.getAttribute('data-fcp-sort');
        sortBtns.forEach(function (x) { x.classList.toggle('is-active', x === b); });
        apply();
      });
    });
    if (savedBtn) savedBtn.addEventListener('click', function () {
      st.saved = !st.saved;
      savedBtn.classList.toggle('is-active', st.saved);
      savedBtn.setAttribute('aria-pressed', st.saved ? 'true' : 'false');
      apply();
    });
    doc.addEventListener('fcp:saved', function () { if (st.saved) apply(); });
    window.addEventListener('resize', function () { moveThumb(true); });
    if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(function () { moveThumb(true); });
    setAud(st.aud, true);
    moveThumb(true);
    ready = true;
  });

  /* ---- Home: interactive student path ---- */
  $$('[data-fcp-path]').forEach(function (rootEl) {
    var nodes = $$('[data-fcp-node]', rootEl), panes = $$('[data-fcp-pane]', rootEl), line = $('.fcp-path-line', rootEl);
    var n = nodes.length;
    if (!n) return;
    function go(i) {
      nodes.forEach(function (nd, k) {
        nd.classList.toggle('is-active', k === i);
        nd.classList.toggle('is-passed', k < i);
        nd.setAttribute('aria-selected', k === i ? 'true' : 'false');
        nd.tabIndex = k === i ? 0 : -1;
      });
      panes.forEach(function (p, k) { p.classList.toggle('is-active', k === i); });
      if (line) line.style.setProperty('--pf', (n > 1 ? i / (n - 1) : 0).toFixed(3));
    }
    nodes.forEach(function (nd, i) {
      nd.addEventListener('click', function () { go(i); });
      nd.addEventListener('mouseenter', function () { if (fine) go(i); });
      nd.addEventListener('keydown', function (e) {
        var d = e.key === 'ArrowLeft' ? 1 : (e.key === 'ArrowRight' ? -1 : 0); // RTL: left = next stage
        if (!d) return;
        e.preventDefault();
        var j = clamp(i + d, 0, n - 1);
        go(j); nodes[j].focus();
      });
    });
    go(0);
  });

  /* ---- Single advice: reading progress + live table of contents ---- */
  var prog = $('[data-fcp-progress]'), art = $('[data-fcp-article]'), toc = $('[data-fcp-toc]');
  if (prog || toc) {
    var heads = art ? $$('.fcp-advice-prose h3', art) : [];
    var links = toc ? $$('a', toc) : [];
    var tick = false;
    var updateAdv = function () {
      tick = false;
      if (prog && art) {
        var r = art.getBoundingClientRect();
        var total = r.height - vh() * 0.5;
        prog.style.setProperty('--rp', (total > 0 ? clamp(-r.top / total, 0, 1) : 1).toFixed(3));
      }
      if (links.length && heads.length) {
        var idx = 0;
        heads.forEach(function (h, i) { if (h.getBoundingClientRect().top < 130) idx = i; });
        links.forEach(function (a, i) { a.classList.toggle('is-on', i === idx); });
      }
    };
    window.addEventListener('scroll', function () { if (!tick) { tick = true; requestAnimationFrame(updateAdv); } }, { passive: true });
    window.addEventListener('resize', updateAdv);
    updateAdv();
  }

  /* ---- Professor form: audience <-> topic, counters, safe live preview ---- */
  function renderPreview(text, out) {
    out.textContent = '';
    var para = [], list = null;
    function flushP() {
      if (!para.length) return;
      var p = doc.createElement('p');
      para.forEach(function (l, i) { if (i) p.appendChild(doc.createElement('br')); p.appendChild(doc.createTextNode(l)); });
      out.appendChild(p); para = [];
    }
    text.split(/\r\n|\r|\n/).forEach(function (line) {
      if (!line.trim()) { flushP(); list = null; return; }
      if (line.indexOf('## ') === 0) {
        flushP(); list = null;
        var h = doc.createElement('h3'); h.textContent = line.slice(3).trim(); out.appendChild(h); return;
      }
      if (line.indexOf('- ') === 0) {
        flushP();
        if (!list) { list = doc.createElement('ul'); list.className = 'fcp-bullets'; out.appendChild(list); }
        var li = doc.createElement('li'); li.textContent = line.slice(2).trim(); list.appendChild(li); return;
      }
      list = null; para.push(line);
    });
    flushP();
  }
  $$('[data-fcp-advice-form]').forEach(function (form) {
    var radios = $$('input[name="fcp_audience"]', form), sel = $('[data-fcp-topic-select]', form), prompt = $('[data-fcp-prompt] span', form);
    function syncAud() {
      var cur = radios.filter(function (r) { return r.checked; })[0];
      var aud = cur ? cur.value : '';
      $$('optgroup', sel).forEach(function (g) { var off = g.getAttribute('data-aud') !== aud; g.hidden = off; g.disabled = off; });
      var chosen = sel.options[sel.selectedIndex];
      if (chosen && chosen.value && chosen.getAttribute('data-aud') !== aud) sel.value = '';
      var lab = cur && cur.closest('label');
      if (lab && prompt) prompt.textContent = lab.getAttribute('data-prompt') || '';
    }
    radios.forEach(function (r) { r.addEventListener('change', syncAud); });
    if (sel) syncAud();

    $$('[data-fcp-count-for]', form).forEach(function (ta) {
      var key = ta.getAttribute('data-fcp-count-for');
      var out = $('[data-fcp-counter="' + key + '"]', form);
      if (!out) return;
      var max = parseInt(out.getAttribute('data-max'), 10) || 0, min = parseInt(out.getAttribute('data-min'), 10) || 0;
      function upd() {
        var len = ta.value.length;
        var txt = fmt(len) + ' / ' + fmt(max);
        var bad = min && len > 0 && len < min;
        if (bad) txt += ' — حداقل ' + fmt(min) + ' نویسه';
        out.textContent = txt;
        out.classList.toggle('is-bad', !!bad);
      }
      ta.addEventListener('input', upd); upd();
    });

    var src = $('[data-fcp-preview-src]', form), pv = $('[data-fcp-preview]', form), pout = $('[data-fcp-preview-out]', form);
    if (src && pv && pout) {
      var pt = null;
      var draw = function () { if (pv.open) renderPreview(src.value, pout); };
      src.addEventListener('input', function () { clearTimeout(pt); pt = setTimeout(draw, 150); });
      pv.addEventListener('toggle', draw);
    }
  });
})();
