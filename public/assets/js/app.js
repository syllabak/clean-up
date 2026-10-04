/* Comportements communs. Aucun script inline : compatible avec la CSP stricte. */
(function () {
  // Confirmation avant action sensible : <form data-confirm="Message ?">
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toggle-side]');
    if (t) { document.getElementById('side').classList.toggle('open'); }
    if (e.target.closest('[data-reload]')) location.reload();
  });
  var base = (document.querySelector('meta[name="base-path"]') || {}).content || '';
  // Service worker : mise en cache des fichiers statiques uniquement (voir sw.js)
  if ('serviceWorker' in navigator && location.protocol !== 'file:') {
    window.addEventListener('load', function () { navigator.serviceWorker.register(base + '/sw.js').catch(function () {}); });
  }
})();
