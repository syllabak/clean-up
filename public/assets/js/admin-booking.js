/* Fiche réservation (admin) : charge les créneaux réellement disponibles pour la date choisie. */
(function () {
  var f = document.getElementById('resched');
  if (!f) return;
  var date = document.getElementById('r_date'), sel = document.getElementById('r_start');
  date.addEventListener('change', function () {
    sel.innerHTML = '<option value="">Chargement…</option>';
    fetch(((document.querySelector('meta[name="base-path"]') || {}).content || '') + '/admin/api/creneaux', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': f.dataset.csrf, Accept: 'application/json' }, body: JSON.stringify({ booking_id: +f.dataset.booking, date: date.value }) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        sel.innerHTML = '';
        if (!d.slots || !d.slots.length) { sel.innerHTML = '<option value="">Aucun créneau ce jour-là</option>'; return; }
        d.slots.forEach(function (s) { var o = document.createElement('option'); o.value = s.start; o.textContent = s.start + ' → ' + s.end + (s.recommended ? '  (regroupé)' : ''); sel.appendChild(o); });
      }).catch(function () { sel.innerHTML = '<option value="">Erreur de chargement</option>'; });
  });
})();
