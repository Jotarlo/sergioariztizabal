(function () {
  /* Las presentaciones se administran desde el menú «Mapa presentaciones» de WordPress. */
  var CFG = window.SA_MAPA || { items: [] };
  var PRESENTACIONES = CFG.items;
  var DATA_URL = CFG.dataUrl;
  var GOLD = '#cc9902', GRAY = '#3a3a3a', NS = 'http://www.w3.org/2000/svg';
  var $ = function (s) { return document.querySelector(s); };
  var n = function (s) { return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().trim(); };
  var svg = $('#mp-svg'), dsvg = $('#mp-dsvg'), modal = $('#mp-modal'), photos = $('#mp-photos');
  var byMuni = {}, deptos = {};
  zoomable(svg); zoomable(dsvg);
  PRESENTACIONES.forEach(function (p) {
    var o = byMuni[n(p.d) + '|' + n(p.k || p.m)] = byMuni[n(p.d) + '|' + n(p.k || p.m)] || { m: p.m, gals: [], links: [] };
    (p.gals || []).forEach(function (x) { if (o.gals.indexOf(x) < 0) o.gals.push(x); });
    o.links = o.links.concat(p.links || []);
    deptos[n(p.d)] = 1;
  });

  function el(tag, attrs, parent) {
    var e = document.createElementNS(NS, tag);
    for (var a in attrs) e.setAttribute(a, attrs[a]);
    if (parent) parent.appendChild(e);
    return e;
  }
  function polys(g) { return g.type === 'Polygon' ? [g.coordinates] : g.coordinates; }
  function path(g) {
    return polys(g).map(function (p) { return p.map(function (r) {
      return r.map(function (c, i) { return (i ? 'L' : 'M') + c[0].toFixed(3) + ' ' + (-c[1]).toFixed(3); }).join('') + 'Z';
    }).join(''); }).join('');
  }
  function center(g) {
    var best = null, ba = 0;
    polys(g).forEach(function (p) {
      var r = p[0], a = 0, cx = 0, cy = 0;
      for (var i = 0, j = r.length - 1; i < r.length; j = i++) {
        var f = r[j][0] * r[i][1] - r[i][0] * r[j][1];
        a += f; cx += (r[j][0] + r[i][0]) * f; cy += (r[j][1] + r[i][1]) * f;
      }
      if (a && Math.abs(a) > Math.abs(ba)) { ba = a; best = [cx / (3 * a), cy / (3 * a)]; }
    });
    return best ? [best[0], -best[1]] : [0, 0];
  }
  function bw(g) {
    var a = 1e9, z = -1e9;
    polys(g).forEach(function (p) { p[0].forEach(function (c) { a = Math.min(a, c[0]); z = Math.max(z, c[0]); }); });
    return z - a;
  }
  var DEP = { ATLANTICO: 'Atlántico', BOLIVAR: 'Bolívar', BOYACA: 'Boyacá', CAQUETA: 'Caquetá', CHOCO: 'Chocó', CORDOBA: 'Córdoba',
    GUAINIA: 'Guainía', NARINO: 'Nariño', QUINDIO: 'Quindío', VAUPES: 'Vaupés', 'SANTAFE DE BOGOTA D.C': 'Bogotá D.C.',
    'ARCHIPIELAGO DE SAN ANDRES PROVIDENCIA Y SANTA CATALINA': 'San Andrés' };
  function tc(s) {
    return s.toLowerCase().replace(/(^|\s)(\S)/g, function (m, a, b) { return a + b.toUpperCase(); })
      .replace(/ (De|Del|La|Y|El|Los|Las) /g, function (m) { return m.toLowerCase(); });
  }
  /* Nombres con tamaño constante en pantalla: al hacer zoom aparecen los de zonas pequeñas. */
  function addLabels(s, items, baseFs) {
    var list = items.map(function (it) {
      var t = el('text', { x: it.x, y: it.y, 'class': it.on ? '' : 'dim' }, s);
      t.textContent = it.t;
      return { t: t, w: it.w, len: it.t.length, on: it.on };
    });
    function run() {
      var fs = baseFs * s._vb[2] / s._base[2];
      list.forEach(function (l) {
        l.t.style.display = l.len * fs * .6 <= l.w * (l.on ? 1.8 : 1.0) ? '' : 'none';
        l.t.setAttribute('font-size', fs); l.t.setAttribute('stroke-width', fs * .15);
      });
    }
    s._onzoom = run; run();
  }
  function fit(s, feats, pad) {
    var b = [1e9, 1e9, -1e9, -1e9];
    feats.forEach(function (f) { polys(f.geometry).forEach(function (p) { p[0].forEach(function (c) {
      b[0] = Math.min(b[0], c[0]); b[1] = Math.min(b[1], -c[1]); b[2] = Math.max(b[2], c[0]); b[3] = Math.max(b[3], -c[1]);
    }); }); });
    s._base = [b[0] - pad, b[1] - pad, b[2] - b[0] + 2 * pad, b[3] - b[1] + 2 * pad]; setVB(s, s._base.slice());
    return b[2] - b[0];
  }
  function setVB(s, v) {
    s._vb = v; s.setAttribute('viewBox', v.join(' '));
    s.style.touchAction = v[2] < s._base[2] * .98 ? 'none' : 'pan-y';
    if (s._onzoom) s._onzoom();
  }
  function toUser(s, x, y) {
    var r = s.getBoundingClientRect(), v = s._vb;
    return [v[0] + (x - r.left) / r.width * v[2], v[1] + (y - r.top) / r.height * v[3]];
  }
  function zoomAt(s, k, c) {
    var v = s._vb, b = s._base, w = v[2] / k;
    if (w > b[2]) { setVB(s, b.slice()); return; }
    if (w < b[2] / 14) return;
    var f = w / v[2];
    setVB(s, [c[0] - (c[0] - v[0]) * f, c[1] - (c[1] - v[1]) * f, w, v[3] * f]);
  }
  function zoomable(s) {
    var ptr = {}, moved = false, last = 0;
    var bar = document.createElement('div'); bar.className = 'zc';
    [['+', 1.6], ['−', 1 / 1.6], ['⟲', 0]].forEach(function (a) {
      var b = document.createElement('button'); b.textContent = a[0]; b.setAttribute('aria-label', 'Zoom');
      b.onclick = function () {
        if (!a[1]) return setVB(s, s._base.slice());
        var v = s._vb; zoomAt(s, a[1], [v[0] + v[2] / 2, v[1] + v[3] / 2]);
      };
      bar.appendChild(b);
    });
    s.parentNode.appendChild(bar);
    s.addEventListener('wheel', function (e) {
      e.preventDefault(); zoomAt(s, e.deltaY < 0 ? 1.25 : .8, toUser(s, e.clientX, e.clientY));
    }, { passive: false });
    s.addEventListener('pointerdown', function (e) { ptr[e.pointerId] = [e.clientX, e.clientY]; moved = false; last = 0; });
    s.addEventListener('pointermove', function (e) {
      var o = ptr[e.pointerId]; if (!o) return;
      var ids = Object.keys(ptr), r = s.getBoundingClientRect(), v = s._vb;
      if (ids.length === 2) {
        var q = ptr[ids.filter(function (i) { return i != e.pointerId; })[0]];
        var d = Math.hypot(e.clientX - q[0], e.clientY - q[1]);
        if (last) zoomAt(s, d / last, toUser(s, (e.clientX + q[0]) / 2, (e.clientY + q[1]) / 2));
        last = d; moved = true;
      } else if (v[2] < s._base[2] * .98) {
        var dx = e.clientX - o[0], dy = e.clientY - o[1];
        if (Math.abs(dx) + Math.abs(dy) > 1) moved = moved || Math.hypot(dx, dy) > 4;
        setVB(s, [v[0] - dx * v[2] / r.width, v[1] - dy * v[3] / r.height, v[2], v[3]]);
      }
      ptr[e.pointerId] = [e.clientX, e.clientY];
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (t) {
      s.addEventListener(t, function (e) { delete ptr[e.pointerId]; last = 0; });
    });
    s.addEventListener('click', function (e) { if (moved) { e.stopPropagation(); e.preventDefault(); moved = false; } }, true);
  }
  function show(box, titulo) { box.querySelector('h3').textContent = titulo; box.classList.add('open'); }
  [modal, photos].forEach(function (m) { m.addEventListener('click', function (e) {
    if (e.target === m || e.target.className === 'x' || (m === photos && /^(vw|vm)$/.test(e.target.className))) m.classList.remove('open');
  }); });

  /* ---------- Visor de fotos: todas las del álbum, grandes, con flechas ---------- */
  var vImg = photos.querySelector('.vm img'), vLoad = photos.querySelector('.vl'), vT = photos.querySelector('.vt'),
      vC = photos.querySelector('.vc'), vA = photos.querySelector('.al');
  var V = { o: null, list: [], i: 0 };
  function vShow(i) {
    var L = V.list; if (!L.length) return;
    V.i = (i + L.length) % L.length;
    vLoad.textContent = 'Cargando foto…'; vLoad.style.display = 'block'; vImg.style.opacity = .25;
    vImg.onload = function () { vLoad.style.display = 'none'; vImg.style.opacity = 1; };
    vImg.onerror = function () { vLoad.textContent = 'No se pudo cargar esta foto'; };
    vImg.src = L[V.i]; vImg.alt = V.o.m;
    vC.textContent = (V.i + 1) + ' / ' + L.length;
    [1, -1].forEach(function (d) { new Image().src = L[(V.i + d + L.length) % L.length]; });
  }
  function vMsg(t) { vLoad.textContent = t; vLoad.style.display = 'block'; vImg.removeAttribute('src'); }
  function openPhotos(o) {
    V.o = o; V.list = []; V.i = 0;
    vT.textContent = o.m; vC.textContent = '';
    vA.innerHTML = '';
    if (CFG.showAlbum !== false) o.links.forEach(function (l, k) {
      if (k) vA.appendChild(document.createTextNode(' · '));
      var a = document.createElement('a'); a.className = 'gl'; a.href = l.u;
      a.textContent = (o.links.length > 1 && l.t ? 'Álbum: ' + l.t : 'Ver álbum completo') + ' →';
      vA.appendChild(a);
    });
    photos.classList.add('open');
    if (o.list) { V.list = o.list; return vShow(0); }
    if (!o.gals.length) return vMsg('Fotos próximamente.');
    vMsg('Cargando fotos…');
    fetch(CFG.ajax + '?action=sa_mapa_fotos&gal=' + o.gals.join(',')).then(function (r) { return r.json(); }).then(function (L) {
      if (!L || !L.length) return vMsg('Fotos próximamente.');
      o.list = V.list = L; vShow(0);
    }).catch(function () { vMsg('No se pudieron cargar las fotos.'); });
  }
  photos.querySelector('.vp').addEventListener('click', function () { vShow(V.i - 1); });
  photos.querySelector('.vs').addEventListener('click', function () { vShow(V.i + 1); });
  document.addEventListener('keydown', function (e) {
    if (!photos.classList.contains('open')) return;
    if (e.key === 'Escape') photos.classList.remove('open');
    else if (e.key === 'ArrowLeft') vShow(V.i - 1);
    else if (e.key === 'ArrowRight') vShow(V.i + 1);
  });
  var vx0 = null;
  photos.addEventListener('pointerdown', function (e) { vx0 = e.clientX; });
  photos.addEventListener('pointerup', function (e) {
    if (vx0 === null) return;
    var dx = e.clientX - vx0; vx0 = null;
    if (dx > 60) vShow(V.i - 1); else if (dx < -60) vShow(V.i + 1);
  });

  fetch(DATA_URL).then(function (r) { return r.json(); }).then(function (topo) {
    var depts = topojson.feature(topo, topo.objects.depts).features;
    var mpios = topojson.feature(topo, topo.objects.mpios).features;
    var W = fit(svg, depts.filter(function (f) { return n(f.properties.dpt).indexOf('ARCHIPIELAGO') < 0; }), .3);

    function openDept(name) {
      var feats = mpios.filter(function (f) { return n(f.properties.dpt) === name; }), lab = [];
      dsvg.innerHTML = ''; dsvg._onzoom = null;
      var w = fit(dsvg, feats, .05);
      feats.forEach(function (f) {
        var o = byMuni[name + '|' + n(f.properties.name)], t = o ? o.m : tc(f.properties.name);
        var p = el('path', { d: path(f.geometry), fill: o ? GOLD : GRAY, 'class': o ? 'go' : 'off' }, dsvg);
        p.appendChild(el('title', {})).textContent = t;
        if (o) p.addEventListener('click', function () { openPhotos(o); });
        var c = center(f.geometry);
        lab.push({ x: c[0], y: c[1], t: t, w: bw(f.geometry), on: !!o });
      });
      addLabels(dsvg, lab, w * .021);
      show(modal, DEP[name] || tc(name));
    }

    var lab = [];
    depts.forEach(function (f) {
      var key = n(f.properties.dpt), on = deptos[key], t = DEP[key] || tc(key);
      var p = el('path', { d: path(f.geometry), fill: on ? GOLD : GRAY, 'class': on ? 'go' : 'off' }, svg);
      p.appendChild(el('title', {})).textContent = t;
      if (on) p.addEventListener('click', function () { openDept(key); });
      if (key.indexOf('ARCHIPIELAGO') < 0) { var c = center(f.geometry); lab.push({ x: c[0], y: c[1], t: t, w: bw(f.geometry), on: !!on }); }
    });
    addLabels(svg, lab, W * .019);
    $('#mp-msg').style.display = 'none';
  }).catch(function () { $('#mp-msg').textContent = 'No se pudo cargar el mapa.'; });
})();
