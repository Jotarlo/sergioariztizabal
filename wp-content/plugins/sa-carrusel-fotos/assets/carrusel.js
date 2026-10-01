(function () {
  var modalOpen = false;

  function lightbox(items, start, accent) {
    var cur = start, ov = document.createElement('div');
    ov.className = 'sac-modal';
    ov.style.setProperty('--ac', accent);
    ov.innerHTML = '<button type="button" class="sac-x" aria-label="Cerrar">×</button><button type="button" class="sac-m sac-mp" aria-label="Anterior">‹</button><img alt=""><button type="button" class="sac-m sac-mn" aria-label="Siguiente">›</button>';
    var img = ov.querySelector('img');
    function show(i) { cur = (i + items.length) % items.length; img.src = items[cur].full; img.alt = items[cur].t; }
    function close() { ov.remove(); document.removeEventListener('keydown', key); document.documentElement.style.overflow = ''; modalOpen = false; }
    function key(e) { if (e.key === 'Escape') close(); else if (e.key === 'ArrowLeft') show(cur - 1); else if (e.key === 'ArrowRight') show(cur + 1); }
    ov.querySelector('.sac-x').onclick = close;
    ov.querySelector('.sac-mp').onclick = function () { show(cur - 1); };
    ov.querySelector('.sac-mn').onclick = function () { show(cur + 1); };
    ov.addEventListener('click', function (e) { if (e.target === ov) close(); });
    var x0 = null;
    ov.addEventListener('pointerdown', function (e) { x0 = e.clientX; });
    ov.addEventListener('pointerup', function (e) {
      if (x0 === null) return;
      var dx = e.clientX - x0; x0 = null;
      if (dx > 60) show(cur - 1); else if (dx < -60) show(cur + 1);
    });
    document.addEventListener('keydown', key);
    document.documentElement.style.overflow = 'hidden';
    modalOpen = true; show(cur);
    document.body.appendChild(ov);
    ov.querySelector('.sac-x').focus();
  }

  function init(el) {
    var track = el.querySelector('.sac-track'), n = track.children.length;
    var secs = parseFloat(el.dataset.int) || 4, dur = parseInt(el.dataset.dur, 10) || 700;
    var mode = el.dataset.mode, bounce = mode === 'bounce', cont = mode === 'continuous';
    var maxVis = parseInt(el.dataset.vis, 10) || 3, auto = el.dataset.auto === '1';
    var accent = getComputedStyle(el).getPropertyValue('--ac').trim() || '#d8aa00';
    var arrows = el.querySelectorAll('.sac-prev, .sac-next');
    var vis = 1, idx = 0, dir = 1, busy = false, timer = null, hover = false, pos = 0, last = 0, raf = 0, moved = false;

    function max() { return Math.max(0, n - vis); }
    function go(i, animate) {
      idx = i;
      track.style.transition = animate ? 'transform ' + dur + 'ms ease' : 'none';
      track.style.transform = 'translateX(' + (-idx * 100 / vis) + '%)';
    }
    function next(manual) {
      if (n <= vis || busy || cont) return;
      if (bounce) {
        if (manual) { if (idx < max()) go(idx + 1, true); return; }
        if (idx >= max()) dir = -1; else if (idx <= 0) dir = 1;
        go(idx + dir, true); return;
      }
      busy = true;
      track.style.transition = 'transform ' + dur + 'ms ease';
      track.style.transform = 'translateX(' + (-100 / vis) + '%)';
      setTimeout(function () {
        track.style.transition = 'none';
        track.appendChild(track.firstElementChild);
        track.style.transform = 'translateX(0)';
        busy = false;
      }, dur + 20);
    }
    function prev() {
      if (n <= vis || busy || cont) return;
      if (bounce) { if (idx > 0) go(idx - 1, true); return; }
      busy = true;
      track.style.transition = 'none';
      track.insertBefore(track.lastElementChild, track.firstElementChild);
      track.style.transform = 'translateX(' + (-100 / vis) + '%)';
      void track.offsetWidth;
      track.style.transition = 'transform ' + dur + 'ms ease';
      track.style.transform = 'translateX(0)';
      setTimeout(function () { busy = false; }, dur + 20);
    }
    function frame(t) {
      var dt = last ? Math.min(t - last, 50) : 0; last = t;
      if (!hover && !modalOpen && !document.hidden) {
        var sw = track.firstElementChild.getBoundingClientRect().width;
        if (sw > 1) {
          pos += dt * sw / (secs * 1000);
          while (pos >= sw) { track.appendChild(track.firstElementChild); pos -= sw; }
          track.style.transform = 'translateX(' + (-pos) + 'px)';
        }
      }
      raf = requestAnimationFrame(frame);
    }
    function start() {
      clearInterval(timer); cancelAnimationFrame(raf); last = 0;
      if (!auto || n <= vis) return;
      if (cont) { raf = requestAnimationFrame(frame); return; }
      timer = setInterval(function () { if (!hover && !modalOpen && !document.hidden) next(false); }, secs * 1000);
    }
    function setup() {
      var w = el.clientWidth, v = w >= 900 ? maxVis : (w >= 600 ? Math.min(2, maxVis) : 1);
      vis = Math.max(1, Math.min(v, n));
      el.style.setProperty('--vis', vis);
      idx = Math.min(idx, max());
      go(bounce ? idx : 0, false); pos = 0;
      for (var i = 0; i < arrows.length; i++) arrows[i].style.display = (n > vis && !cont) ? '' : 'none';
      start();
    }

    if (arrows[0]) arrows[0].addEventListener('click', function () { prev(); });
    if (arrows[1]) arrows[1].addEventListener('click', function () { next(true); });
    el.addEventListener('mouseenter', function () { hover = true; });
    el.addEventListener('mouseleave', function () { hover = false; });
    el.addEventListener('touchstart', function () { hover = true; }, { passive: true });
    el.addEventListener('touchend', function () { setTimeout(function () { hover = false; }, 2500); });
    var x0 = null;
    el.addEventListener('pointerdown', function (e) { x0 = e.clientX; moved = false; });
    el.addEventListener('pointerup', function (e) {
      if (x0 === null) return;
      var dx = e.clientX - x0; x0 = null;
      if (Math.abs(dx) > 6) moved = true;
      if (cont) return;
      if (dx > 50) prev(); else if (dx < -50) next(true);
    });
    el.addEventListener('click', function (e) {
      if (moved) { moved = false; return; }
      var s = e.target.closest('.sac-slide');
      if (!s || !s.dataset.full) return;
      var list = Array.prototype.slice.call(track.children);
      lightbox(list.map(function (c) { return { full: c.dataset.full, t: c.dataset.t || '' }; }), list.indexOf(s), accent);
    });
    var rt;
    window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(setup, 150); });
    setup();
  }
  Array.prototype.forEach.call(document.querySelectorAll('.sac'), init);
})();
