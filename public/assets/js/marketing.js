// Balises publicitaires (Meta, Google) : chargées uniquement après l'accord du visiteur.
// La configuration vient des <meta name="mk-config"> et <meta name="mk-event"> de la page ; aucun script en ligne.
(function () {
  'use strict';
  function meta(name) {
    var m = document.querySelector('meta[name="' + name + '"]');
    if (!m) return null;
    try { return JSON.parse(m.content); } catch (e) { return null; }
  }
  var cfg = meta('mk-config');
  if (!cfg) return;
  var ev = meta('mk-event');

  function consent() { var m = document.cookie.match(/(?:^|; )mk_consent=([01])/); return m ? m[1] : null; }
  function setConsent(v) {
    document.cookie = 'mk_consent=' + v + '; max-age=31536000; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }

  function loadMeta() {
    if (!cfg.meta || window.fbq) return;
    var n = window.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
    if (!window._fbq) window._fbq = n;
    n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
    var s = document.createElement('script'); s.async = true; s.src = 'https://connect.facebook.net/en_US/fbevents.js';
    document.head.appendChild(s);
    window.fbq('init', cfg.meta);
    window.fbq('track', 'PageView');
  }

  function loadGoogle() {
    var id = cfg.gads || cfg.ga4;
    if (!id || window.gtag) return;
    var s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    if (cfg.gads) window.gtag('config', cfg.gads);
    if (cfg.ga4) window.gtag('config', cfg.ga4);
  }

  // Une conversion n'est comptée qu'une fois par réservation, même si la page est rechargée.
  function conversion() {
    if (!ev || !ev.id) return;
    var key = 'mk_done_' + ev.id;
    try { if (sessionStorage.getItem(key)) return; sessionStorage.setItem(key, '1'); } catch (e) { /* stockage indisponible : on envoie quand même */ }
    var value = Number(ev.value) || 0;
    if (window.fbq) window.fbq('track', 'Lead', { value: value, currency: cfg.currency }, { eventID: ev.id });   // même identifiant que l'envoi serveur : Meta ne compte qu'une fois
    if (window.gtag) {
      if (cfg.gads && cfg.labelBooking) window.gtag('event', 'conversion', { send_to: cfg.gads + '/' + cfg.labelBooking, value: value, currency: cfg.currency, transaction_id: ev.id });
      if (cfg.ga4) window.gtag('event', 'generate_lead', { value: value, currency: cfg.currency, transaction_id: ev.id });
    }
  }

  function start() { loadMeta(); loadGoogle(); conversion(); }

  function banner() {
    var old = document.querySelector('.mk-banner'); if (old) old.remove();
    var b = document.createElement('div'); b.className = 'mk-banner'; b.setAttribute('role', 'dialog'); b.setAttribute('aria-label', 'Cookies publicitaires');
    var p = document.createElement('p');
    p.textContent = 'Nous utilisons des cookies de mesure publicitaire (Facebook, Google) pour savoir quelles annonces vous amènent chez nous. Vous pouvez les refuser, le site fonctionne de la même façon. ';
    var a = document.createElement('a'); a.href = '/confidentialite'; a.textContent = 'En savoir plus'; p.appendChild(a);
    var ok = document.createElement('button'); ok.type = 'button'; ok.className = 'btn btn-lemon btn-sm'; ok.textContent = 'Accepter';
    var no = document.createElement('button'); no.type = 'button'; no.className = 'btn btn-outline-light btn-sm'; no.textContent = 'Refuser';
    ok.addEventListener('click', function () { setConsent('1'); b.remove(); start(); });
    no.addEventListener('click', function () { setConsent('0'); b.remove(); });
    var row = document.createElement('div'); row.className = 'mk-actions'; row.appendChild(no); row.appendChild(ok);
    b.appendChild(p); b.appendChild(row); document.body.appendChild(b);
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-mk-reset]') : null;
    if (t) { e.preventDefault(); banner(); }
  });

  var c = consent();
  if (c === '1') start();
  else if (c === null) banner();
})();
