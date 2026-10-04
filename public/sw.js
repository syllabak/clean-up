/* Service worker prudent.
 * - Met en cache uniquement les fichiers statiques publics (CSS, JS, icônes) pour accélérer l'affichage.
 * - Ne met JAMAIS en cache : /admin, /agent, /api, /reservation, /suivi, /connexion, /photo, ni aucune réponse POST.
 *   Les données personnelles et les disponibilités ne sont donc jamais conservées sur l'appareil.
 * - Hors connexion, une navigation échouée affiche la page /hors-ligne ; aucune réservation n'est mise en file d'attente. */
const VERSION = 'nx-v2';
const BASE = location.pathname.replace(/\/sw\.js$/, '');   // "" à la racine, "/cleanup" en sous-dossier
const STATIC = ['/assets/vendor/bootstrap.min.css', '/assets/vendor/bootstrap.bundle.min.js', '/assets/css/app.css', '/assets/js/app.js', '/assets/icons/icon-192.png', '/hors-ligne'].map((p) => BASE + p);
const NEVER = /^\/(admin|agent|api|reservation|suivi|connexion|deconnexion|photo|cron|contact)(\/|$)/;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const req = e.request, url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin || NEVER.test(url.pathname.slice(BASE.length))) return;
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(BASE + '/hors-ligne')));
    return;
  }
  if (url.pathname.startsWith(BASE + '/assets/')) {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
      return res;
    })));
  }
});
