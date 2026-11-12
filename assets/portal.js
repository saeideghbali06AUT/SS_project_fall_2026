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
