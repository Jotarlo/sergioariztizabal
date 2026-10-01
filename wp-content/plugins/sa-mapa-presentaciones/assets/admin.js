(function () {
  var dep = document.getElementById('sa_dep'), mun = document.getElementById('sa_mun'), title = document.getElementById('title');
  if (!dep || !mun) return;
  var idx = {}, auto = '';
  var tc = function (s) { return s.toLowerCase().replace(/(^|\s)\S/g, function (m) { return m.toUpperCase(); }); };
  function fillMun() {
    mun.innerHTML = '<option value="">— Elige el municipio —</option>';
    (idx[dep.value] || []).sort().forEach(function (m) { mun.add(new Option(tc(m), m)); });
  }
  fetch(SA_ADMIN.dataUrl).then(function (r) { return r.json(); }).then(function (t) {
    t.objects.mpios.geometries.forEach(function (g) { (idx[g.properties.dpt] = idx[g.properties.dpt] || []).push(g.properties.name); });
    dep.innerHTML = '<option value="">— Elige el departamento —</option>';
    Object.keys(idx).sort().forEach(function (d) { dep.add(new Option(tc(d), d)); });
    dep.value = dep.dataset.v || ''; fillMun(); mun.value = mun.dataset.v || '';
  });
  dep.addEventListener('change', fillMun);
  mun.addEventListener('change', function () {
    if (title && mun.value && (!title.value || title.value === auto)) { auto = tc(mun.value); title.value = auto; }
  });
})();
