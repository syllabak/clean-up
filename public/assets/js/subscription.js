/* Demande d'abonnement : estimation du prix par passage (remise comprise) et champ « entreprise » selon le type de client. */
(function () {
  'use strict';
  var root = document.getElementById('subform'); if (!root) return;
  var BASE = (document.querySelector('meta[name="base-path"]') || {}).content || '';
  var form = root.querySelector('form'), total = document.getElementById('sub-total'), detail = document.getElementById('sub-detail');
  var pct = parseInt(root.dataset.discount, 10) || 0, CUR = root.dataset.currency || 'FCFA', timer = null;
  var money = function (n) { return new Intl.NumberFormat('fr-FR').format(n).replace(/ /g, ' ') + ' ' + CUR; };

  function kind() { var r = form.querySelector('input[name=kind]:checked'); return r ? r.value : 'person'; }
  function syncKind() { document.getElementById('bizbox').style.display = kind() === 'business' ? '' : 'none'; }
  function payload() {
    var fields = {};
    form.querySelectorAll('[name^="fields["]').forEach(function (el) { var k = el.name.slice(7, -1); if (el.value !== '') fields[k] = el.value; });
    var fid = form.querySelector('[name=formula_id]');
    return { service_id: +root.dataset.service, formula_id: fid && fid.value ? +fid.value : null, fields: fields,
      options: Array.prototype.map.call(form.querySelectorAll('[name="options[]"]:checked'), function (i) { return +i.value; }),
      neighborhood_id: +form.querySelector('[name=neighborhood_id]').value || null };
  }
  function refresh() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      fetch(BASE + '/api/quote', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': root.dataset.csrf }, body: JSON.stringify(payload()) })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (r) {
          if (!r.ok) { total.textContent = '—'; detail.textContent = 'Complétez le formulaire pour voir le prix.'; return; }
          var discount = Math.round(r.d.subtotal * pct / 100), t = Math.max(0, r.d.total - discount);
          total.textContent = money(t);
          detail.textContent = (discount > 0 ? 'Remise de ' + money(discount) + ' incluse. ' : '') + 'Déplacement : ' + (r.d.travel_fee ? money(r.d.travel_fee) : 'offert') + '. Prix indicatif, confirmé par notre équipe.';
        }).catch(function () { total.textContent = '—'; });
    }, 250);
  }
  form.addEventListener('change', function (e) { if (e.target.name === 'kind') syncKind(); refresh(); });
  form.addEventListener('input', function (e) { if (e.target.type === 'number') refresh(); });
  syncKind(); refresh();
})();
