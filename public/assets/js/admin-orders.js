/* Administration des commandes : visionneuse de photos (sans ouvrir d'autre page) et carte de localisation. */
(function () {
  'use strict';
  var BASE = (document.querySelector('meta[name="base-path"]') || {}).content || '';

  // ---- Visionneuse : un clic sur une vignette ouvre la photo en grand ; flèches et Échap au clavier
  var box = null, ids = [], idx = 0;
  function show() {
    box.querySelector('img').src = BASE + '/photo/' + ids[idx];
    box.querySelector('.lb-c').textContent = (idx + 1) + ' / ' + ids.length;
    box.querySelector('.lb-p').style.display = box.querySelector('.lb-n').style.display = ids.length > 1 ? '' : 'none';
  }
  function close() { if (box) { box.remove(); box = null; document.removeEventListener('keydown', key); } }
  function step(d) { idx = (idx + d + ids.length) % ids.length; show(); }
  function key(e) { if (e.key === 'Escape') close(); else if (e.key === 'ArrowRight') step(1); else if (e.key === 'ArrowLeft') step(-1); }
  function open(list, i) {
    ids = list; idx = i; close();
    box = document.createElement('div'); box.className = 'lb'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', 'Photos de la commande');
    box.innerHTML = '<img alt="Photo de la commande"><button type="button" class="lb-x" aria-label="Fermer">×</button><button type="button" class="lb-p" aria-label="Photo précédente">‹</button><button type="button" class="lb-n" aria-label="Photo suivante">›</button><div class="lb-c"></div>';
    box.addEventListener('click', function (e) { if (e.target === box || e.target.classList.contains('lb-x')) close(); else if (e.target.classList.contains('lb-p')) step(-1); else if (e.target.classList.contains('lb-n')) step(1); });
    document.body.appendChild(box); document.addEventListener('keydown', key); show();
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-ids]') : null;
    if (!t) return;
    e.preventDefault();
    open(t.dataset.ids.split(',').map(Number), parseInt(t.dataset.i, 10) || 0);
  });

  // ---- Carte de la fiche commande : repère à la position du client (fond de carte OpenStreetMap)
  var m = document.getElementById('order-map');
  if (m && window.L) {
    L.Icon.Default.prototype.options.imagePath = BASE + '/assets/vendor/leaflet/images/';
    var pos = [parseFloat(m.dataset.lat), parseFloat(m.dataset.lng)];
    var map = L.map(m, { scrollWheelZoom: false }).setView(pos, 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { subdomains: 'abc', maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
    L.marker(pos).addTo(map);
  }
})();
