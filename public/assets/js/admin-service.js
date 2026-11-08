/* Simulateur de prix de la fiche service : appelle le même moteur de calcul que le site public. */
(function () {
  'use strict';
  var box = document.getElementById('sim'); if (!box) return;
  var BASE = (document.querySelector('meta[name="base-path"]') || {}).content || '';
  var form = document.getElementById('sim-form'), out = document.getElementById('sim-out'), timer = null;
  var money = function (n) { return new Intl.NumberFormat('fr-FR').format(n).replace(/ /g, ' ') + ' ' + (box.dataset.currency || 'FCFA'); };
  var dur = function (m) { var h = Math.floor(m / 60), r = m % 60; return h ? h + ' h' + (r ? ' ' + String(r).padStart(2, '0') : '') : r + ' min'; };
  function row(label, value, strong) {
    var d = document.createElement('div'); d.className = 'd-flex justify-content-between py-1 border-bottom' + (strong ? ' fw-bold fs-5' : '');
    var a = document.createElement('span'); a.textContent = label; var b = document.createElement('span'); b.textContent = value; d.appendChild(a); d.appendChild(b); return d;
  }
  function run() {
    var fields = {}; form.querySelectorAll('[data-field]').forEach(function (c) { if (c.value.trim() !== '') fields[c.dataset.field] = c.value.trim(); });
    var opts = []; form.querySelectorAll('[data-option]:checked').forEach(function (c) { opts.push(+c.dataset.option); });
    var f = document.getElementById('sim-formula'), h = document.getElementById('sim-hood');
    var body = { formula_id: f ? +f.value : null, fields: fields, options: opts, neighborhood_id: h && h.value ? +h.value : null };
    fetch(BASE + box.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': form.querySelector('[name=_csrf]').value }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (r) {
        out.innerHTML = '';
        if (!r.ok) { var al = document.createElement('div'); al.className = 'alert alert-warning small mb-0'; al.textContent = (r.d && r.d.error) || 'Calcul impossible.'; out.appendChild(al); return; }
        var d = r.d; (d.details || []).forEach(function (x) { out.appendChild(row(x[0], x[1])); });
        if (d.quantity > 1) out.appendChild(row('Prix unitaire × ' + d.quantity, money(d.unit_price) + ' × ' + d.quantity));
        out.appendChild(row('Sous-total', money(d.subtotal))); out.appendChild(row('Déplacement', d.travel_fee ? money(d.travel_fee) : 'Offert / non choisi'));
        out.appendChild(row('Durée estimée', dur(d.duration))); out.appendChild(row('Total client', money(d.total), true));
      }).catch(function () { out.textContent = 'Connexion impossible.'; });
  }
  form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 250); });
  form.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(run, 100); });
  run();
})();
