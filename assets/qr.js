/* Minimal QR encoder (byte mode, ECC level M, versions 1-10). No dependencies.
   Usage: FcpQR.svg('https://example.com', {size: 160}) -> SVG string
          FcpQR.mount(document) -> fills every [data-fcp-qr] element */
(function (root) {
  'use strict';
  var ECC_PER_BLOCK = [10, 16, 26, 18, 24, 16, 18, 22, 22, 26];
  var NUM_BLOCKS = [1, 1, 1, 2, 2, 4, 4, 4, 5, 5];

  function rawModules(v) {
    var r = (16 * v + 128) * v + 64;
    if (v >= 2) { var n = Math.floor(v / 7) + 2; r -= (25 * n - 10) * n - 55; if (v >= 7) r -= 36; }
    return r;
  }
  function dataCodewords(v) {
    return Math.floor(rawModules(v) / 8) - ECC_PER_BLOCK[v - 1] * NUM_BLOCKS[v - 1];
  }
  function alignPositions(v) {
    if (v === 1) return [];
    var n = Math.floor(v / 7) + 2, size = v * 4 + 17;
    var step = Math.ceil((v * 4 + 4) / (n * 2 - 2)) * 2, res = [6];
    for (var p = size - 7; res.length < n; p -= step) res.splice(1, 0, p);
    return res;
  }
  function gfMul(x, y) {
    var z = 0;
    for (var i = 7; i >= 0; i--) { z = (z << 1) ^ ((z >>> 7) * 0x11D); z ^= ((y >>> i) & 1) * x; }
    return z;
  }
  function rsDivisor(d) {
    var res = [], i, j, root = 1;
    for (i = 0; i < d - 1; i++) res.push(0);
    res.push(1);
    for (i = 0; i < d; i++) {
      for (j = 0; j < d; j++) { res[j] = gfMul(res[j], root); if (j + 1 < d) res[j] ^= res[j + 1]; }
      root = gfMul(root, 2);
    }
    return res;
  }
  function rsRemainder(data, div) {
    var res = div.map(function () { return 0; });
    data.forEach(function (b) {
      var f = b ^ res.shift(); res.push(0);
      div.forEach(function (c, i) { res[i] ^= gfMul(c, f); });
    });
    return res;
  }
  function utf8(str) {
    var s = unescape(encodeURIComponent(str)), out = [];
    for (var i = 0; i < s.length; i++) out.push(s.charCodeAt(i));
    return out;
  }

  function encode(text) {
    var bytes = utf8(text), v, cap;
    for (v = 1; v <= 10; v++) {
      cap = dataCodewords(v) * 8;
      if (4 + (v < 10 ? 8 : 16) + bytes.length * 8 <= cap) break;
    }
    if (v > 10) throw new Error('Text too long for QR (max ~210 bytes)');
    var bits = [];
    function put(val, len) { for (var i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
    put(4, 4); put(bytes.length, v < 10 ? 8 : 16);
    bytes.forEach(function (b) { put(b, 8); });
    put(0, Math.min(4, cap - bits.length));
    while (bits.length % 8) bits.push(0);
    var data = [];
    for (var i = 0; i < bits.length; i += 8) {
      var b = 0; for (var k = 0; k < 8; k++) b = (b << 1) | bits[i + k]; data.push(b);
    }
    for (var pad = 0xEC; data.length < cap / 8; pad ^= 0xEC ^ 0x11) data.push(pad);

    // error correction + interleave
    var nb = NUM_BLOCKS[v - 1], eccLen = ECC_PER_BLOCK[v - 1];
    var raw = Math.floor(rawModules(v) / 8), nShort = nb - raw % nb, shortLen = Math.floor(raw / nb);
    var blocks = [], div = rsDivisor(eccLen), pos = 0;
    for (i = 0; i < nb; i++) {
      var dat = data.slice(pos, pos + shortLen - eccLen + (i < nShort ? 0 : 1)); pos += dat.length;
      var ecc = rsRemainder(dat, div);
      if (i < nShort) dat.push(0);
      blocks.push(dat.concat(ecc));
    }
    var all = [];
    for (i = 0; i < blocks[0].length; i++)
      blocks.forEach(function (blk, j) { if (i !== shortLen - eccLen || j >= nShort) all.push(blk[i]); });

    // matrix
    var size = v * 4 + 17, m = [], fn = [], x, y;
    for (y = 0; y < size; y++) { m.push(new Array(size).fill(false)); fn.push(new Array(size).fill(false)); }
    function setFn(xx, yy, dark) { m[yy][xx] = dark; fn[yy][xx] = true; }
    for (i = 0; i < size; i++) { setFn(6, i, i % 2 === 0); setFn(i, 6, i % 2 === 0); }
    function finder(cx, cy) {
      for (var dy = -4; dy <= 4; dy++) for (var dx = -4; dx <= 4; dx++) {
        var d = Math.max(Math.abs(dx), Math.abs(dy)), xx = cx + dx, yy = cy + dy;
        if (xx >= 0 && xx < size && yy >= 0 && yy < size) setFn(xx, yy, d !== 2 && d !== 4);
      }
    }
    finder(3, 3); finder(size - 4, 3); finder(3, size - 4);
    var ap = alignPositions(v), na = ap.length;
    for (i = 0; i < na; i++) for (var j = 0; j < na; j++) {
      if ((i === 0 && j === 0) || (i === 0 && j === na - 1) || (i === na - 1 && j === 0)) continue;
      for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++)
        setFn(ap[i] + dx, ap[j] + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
    }
    function formatBits(mask) {
      var d = (0 << 3) | mask, rem = d, k;
      for (k = 0; k < 10; k++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
      var bt = ((d << 10) | rem) ^ 0x5412;
      function g(n) { return ((bt >>> n) & 1) !== 0; }
      for (k = 0; k <= 5; k++) setFn(8, k, g(k));
      setFn(8, 7, g(6)); setFn(8, 8, g(7)); setFn(7, 8, g(8));
      for (k = 9; k < 15; k++) setFn(14 - k, 8, g(k));
      for (k = 0; k < 8; k++) setFn(size - 1 - k, 8, g(k));
      for (k = 8; k < 15; k++) setFn(8, size - 15 + k, g(k));
      setFn(8, size - 8, true);
    }
    formatBits(0);
    if (v >= 7) {
      var r = v, k2;
      for (k2 = 0; k2 < 12; k2++) r = (r << 1) ^ ((r >>> 11) * 0x1F25);
      var vb = (v << 12) | r;
      for (k2 = 0; k2 < 18; k2++) {
        var a = size - 11 + (k2 % 3), b = Math.floor(k2 / 3), c = ((vb >>> k2) & 1) !== 0;
        setFn(a, b, c); setFn(b, a, c);
      }
    }
    // codewords
    var bi = 0;
    for (var right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      for (var vert = 0; vert < size; vert++) for (j = 0; j < 2; j++) {
        x = right - j; y = (((right + 1) & 2) === 0) ? size - 1 - vert : vert;
        if (!fn[y][x] && bi < all.length * 8) { m[y][x] = ((all[bi >>> 3] >>> (7 - (bi & 7))) & 1) !== 0; bi++; }
      }
    }
    // choose mask
    function applyMask(mask) {
      for (var yy = 0; yy < size; yy++) for (var xx = 0; xx < size; xx++) {
        var inv;
        switch (mask) {
          case 0: inv = (xx + yy) % 2 === 0; break;
          case 1: inv = yy % 2 === 0; break;
          case 2: inv = xx % 3 === 0; break;
          case 3: inv = (xx + yy) % 3 === 0; break;
          case 4: inv = (Math.floor(xx / 3) + Math.floor(yy / 2)) % 2 === 0; break;
          case 5: inv = xx * yy % 2 + xx * yy % 3 === 0; break;
          case 6: inv = (xx * yy % 2 + xx * yy % 3) % 2 === 0; break;
          default: inv = ((xx + yy) % 2 + xx * yy % 3) % 2 === 0;
        }
        if (!fn[yy][xx] && inv) m[yy][xx] = !m[yy][xx];
      }
    }
    function penalty() {
      var p = 0, yy, xx, run, dark = 0;
      for (yy = 0; yy < size; yy++) {
        run = 1;
        for (xx = 1; xx < size; xx++) { if (m[yy][xx] === m[yy][xx - 1]) { run++; if (run === 5) p += 3; else if (run > 5) p++; } else run = 1; }
      }
      for (xx = 0; xx < size; xx++) {
        run = 1;
        for (yy = 1; yy < size; yy++) { if (m[yy][xx] === m[yy - 1][xx]) { run++; if (run === 5) p += 3; else if (run > 5) p++; } else run = 1; }
      }
      for (yy = 0; yy < size - 1; yy++) for (xx = 0; xx < size - 1; xx++) {
        var c = m[yy][xx];
        if (c === m[yy][xx + 1] && c === m[yy + 1][xx] && c === m[yy + 1][xx + 1]) p += 3;
      }
      for (yy = 0; yy < size; yy++) for (xx = 0; xx < size; xx++) if (m[yy][xx]) dark++;
      p += Math.floor(Math.abs(dark * 20 - size * size * 10) / (size * size)) * 10;
      return p;
    }
    var best = 0, bestP = Infinity;
    for (var mk = 0; mk < 8; mk++) {
      applyMask(mk); formatBits(mk);
      var pn = penalty();
      if (pn < bestP) { bestP = pn; best = mk; }
      applyMask(mk);
    }
    applyMask(best); formatBits(best);
    return m;
  }

  function svg(text, opts) {
    opts = opts || {};
    var m = encode(text), n = m.length, q = 4, d = '';
    for (var y = 0; y < n; y++) for (var x = 0; x < n; x++) if (m[y][x]) d += 'M' + (x + q) + ' ' + (y + q) + 'h1v1h-1z';
    var s = n + q * 2, px = opts.size || 160;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + s + ' ' + s + '" width="' + px + '" height="' + px + '" shape-rendering="crispEdges" role="img" aria-label="QR code">' +
      '<rect width="' + s + '" height="' + s + '" fill="' + (opts.light || '#fff') + '"/><path d="' + d + '" fill="' + (opts.dark || '#000') + '"/></svg>';
  }
  function mount(scope) {
    (scope || document).querySelectorAll('[data-fcp-qr]').forEach(function (el) {
      try { el.innerHTML = svg(el.getAttribute('data-fcp-qr'), { size: parseInt(el.getAttribute('data-size') || '160', 10) }); } catch (e) { el.textContent = ''; }
    });
  }
  root.FcpQR = { svg: svg, matrix: encode, mount: mount };
  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { mount(); });
    else mount();
  }
})(typeof window !== 'undefined' ? window : globalThis);
