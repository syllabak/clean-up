/* Parcours de réservation en 3 écrans (besoin, où et quand, coordonnées). Position GPS + carte pour corriger ; photos avant intervention. Le prix et les créneaux viennent toujours du serveur ; il est
   recalculé et revalidé à la soumission : rien de ce qui s'affiche ici n'est pris pour argent comptant. */
(function () {
  'use strict';
  var root = document.getElementById('wizard');
  if (!root) return;
  var BASE = (document.querySelector('meta[name="base-path"]') || {}).content || '';
  var CSRF = root.dataset.csrf, CUR = root.dataset.currency || 'FCFA';
  var SERVICES = JSON.parse(document.getElementById('services-data').textContent);
  var S = { step: 0, svc: null, def: null, formula: null, fields: {}, options: [], hood: null, date: null, slot: null, quote: null, days: null };
  var PREFORMULA = parseInt(root.dataset.preformula, 10) || 0;
  // Trois écrans ; chacun regroupe des sections (data-step) : 0 service + 1 détails | 2 lieu + 4 créneau | 3 coordonnées + 5 récapitulatif
  var PAGES = [[0, 1], [2, 4], [3, 5]], TITLES = ['Votre besoin', 'Où et quand', 'Vos coordonnées'];
  var $ = function (id) { return document.getElementById(id); };
  var money = function (n) { return new Intl.NumberFormat('fr-FR').format(n).replace(/\u202f/g, ' ') + ' ' + CUR; };
  var dur = function (m) { var h = Math.floor(m / 60), r = m % 60; return h ? h + ' h' + (r ? ' ' + String(r).padStart(2, '0') : '') : r + ' min'; };

  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'class') n.className = attrs[k]; else if (k === 'text') n.textContent = attrs[k]; else if (k.slice(0, 2) === 'on') n.addEventListener(k.slice(2), attrs[k]); else n.setAttribute(k, attrs[k]);
    });
    (kids || []).forEach(function (c) { n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }
  function api(url, body) {
    var opt = body === undefined ? { headers: { Accept: 'application/json' } }
      : { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(body) };
    return fetch(BASE + url, opt).then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, status: r.status, data: d }; }); });
  }
  function showError(msg) { var m = $('msg'); m.textContent = msg; m.classList.toggle('d-none', !msg); if (msg) m.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
  function errText(d) { if (d && d.errors) return Object.keys(d.errors).map(function (k) { return d.errors[k]; }).join(' '); return (d && d.error) || 'Une erreur est survenue. Réessayez.'; }

  function payload(extra) {
    var p = { service_id: S.svc && S.svc.id, formula_id: S.formula, fields: S.fields, options: S.options, neighborhood_id: S.hood && S.hood.id };
    return Object.assign(p, extra || {});
  }

  // ---------------------------------------------------------------- Navigation
  function go(p) {
    S.step = p; showError('');
    document.querySelectorAll('.wiz-step').forEach(function (sec) { sec.classList.toggle('active', PAGES[p].indexOf(+sec.dataset.step) !== -1); });
    var pr = $('progress'); pr.innerHTML = '';
    TITLES.forEach(function (t, i) { pr.appendChild(el('li', { class: i < p ? 'done' : i === p ? 'cur' : '', text: (i + 1) + '. ' + t })); });
    $('prev').style.visibility = p === 0 ? 'hidden' : 'visible';
    $('next').textContent = p === 2 ? 'Confirmer la réservation' : 'Continuer';
    $('next').disabled = false;
    if (p === 1) { initMap(); setTimeout(function () { if (G.map) G.map.invalidateSize(); }, 60); if (!G.asked) { G.asked = true; locate(); } }
    if (p === 1 && S.hood) loadDays();
    if (p === 2) renderRecap();
    window.scrollTo({ top: root.offsetTop - 80, behavior: 'smooth' });
  }
  $('prev').addEventListener('click', function () { if (S.step > 0) go(S.step - 1); });
  $('next').addEventListener('click', function () { validate(S.step).then(function (ok) { if (!ok) return; if (S.step === 2) submit(); else go(S.step + 1); }); });

  function splitName(full) {
    var t = full.trim().split(/\s+/);
    return t.length < 2 ? { first: t[0] || '', last: '-' } : { first: t.slice(0, -1).join(' '), last: t[t.length - 1] };
  }
  function validate(p) {
    showError('');
    if (p === 0) { if (!S.svc) { showError('Choisissez un service.'); return Promise.resolve(false); } return refreshQuote(true); }
    if (p === 1) {
      if (!S.hood) { showError('Choisissez votre quartier.'); return Promise.resolve(false); }
      if ($('address').value.trim().length < 5) { showError('Indiquez votre adresse (au moins 5 caractères).'); return Promise.resolve(false); }
      if (!S.slot) { showError('Choisissez une date puis une heure.'); return Promise.resolve(false); }
      return refreshQuote(true);
    }
    var phone = $('phone').value.replace(/\D/g, ''), em = $('email').value.trim();
    if (!$('full_name').value.trim()) { showError('Indiquez votre nom.'); return Promise.resolve(false); }
    if (phone.length < 9 || phone.length > 15) { showError('Indiquez un numéro de téléphone valide.'); return Promise.resolve(false); }
    if (em && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)) { showError("L'email n'est pas valide."); return Promise.resolve(false); }
    return Promise.resolve(true);
  }

  // ---------------------------------------------------------------- Étape 0 : services
  function renderServices() {
    var box = $('service-list'); box.innerHTML = '';
    SERVICES.forEach(function (s) {
      var inp = el('input', { type: 'radio', name: 'service', value: s.id });
      var lab = el('label', { class: 'opt-card' }, [inp, el('strong', { text: s.name }), el('div', { class: 'small text-muted', text: s.short_description || '' }), el('div', { class: 'small fw-semibold text-primary', text: 'dès ' + money(s.from_price) + (s.billing_unit === 'per_sqm' ? ' /m²' : '') })]);
      inp.addEventListener('change', function () { selectService(s); setTimeout(function () { $('sec-needs').scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 400); });
      if (S.svc && S.svc.id === s.id) inp.checked = true;
      box.appendChild(el('div', { class: 'col-md-6' }, [lab]));
    });
  }
  function selectService(s) {
    if (S.svc && S.svc.id === s.id) return;
    S.svc = s; S.formula = null; S.fields = {}; S.options = []; S.quote = null; S.date = S.slot = null;
    api('/api/services/' + s.id + '/form').then(function (r) { if (!r.ok) return showError('Service indisponible.'); S.def = r.data;
      if (PREFORMULA && r.data.formulas.some(function (f) { return f.id === PREFORMULA; })) { S.formula = PREFORMULA; }
      PREFORMULA = 0; renderDetails(); $('sec-needs').classList.remove('d-none'); refreshQuote(false); });
    updateSide();
  }

  // ---------------------------------------------------------------- Étape 1 : détails dynamiques
  function renderDetails() {
    var d = S.def;
    $('svc-cond').textContent = d.service.conditions || '';
    var f = $('formulas'); f.innerHTML = '';
    if (d.formulas.length) {
      f.appendChild(el('h3', { class: 'h6', text: 'Formule' }));
      var row = el('div', { class: 'row g-2' });
      d.formulas.forEach(function (fm) {
        var inp = el('input', { type: 'radio', name: 'formula', value: fm.id });
        if (S.formula === fm.id) inp.checked = true;
        inp.addEventListener('change', function () { S.formula = fm.id; refreshQuote(false); });
        var price = fm.amount ? money(fm.amount) + (d.service.billing_unit === 'per_sqm' ? ' /m²' : '') : '';
        var kids = [inp, el('strong', { text: fm.name })];
        if (fm.popular) kids.push(' ', el('span', { class: 'badge text-bg-warning', text: 'La plus choisie' }));
        kids.push(el('div', { class: 'small text-muted', text: fm.description || '' }), el('div', { class: 'small fw-semibold text-primary', text: price }));
        if (fm.includes && fm.includes.length) kids.push(el('ul', {}, fm.includes.map(function (t) { return el('li', { text: t }); })));
        row.appendChild(el('div', { class: 'col-md-4' }, [el('label', { class: 'opt-card' }, kids)]));
      });
      f.appendChild(row);
    }
    var fl = $('fields'); fl.innerHTML = '';
    d.fields.forEach(function (fd) {
      var id = 'f_' + fd.key, ctl;
      if (fd.type === 'select') {
        ctl = el('select', { class: 'form-select', id: id }, [el('option', { value: '', text: 'Choisir…' })].concat(fd.choices.map(function (c) { return el('option', { value: c.id, text: c.label }); })));
      } else {
        ctl = el('input', { class: 'form-control', id: id, type: fd.type === 'number' ? 'number' : 'text', inputmode: fd.type === 'number' ? 'numeric' : 'text' });
        if (fd.min !== null && fd.min !== undefined) ctl.min = fd.min; if (fd.max !== null && fd.max !== undefined) ctl.max = fd.max;
        if (fd.role === 'quantity') ctl.value = 1;
      }
      var upd = function () { var v = ctl.value.trim(); if (v === '') delete S.fields[fd.key]; else S.fields[fd.key] = v; refreshQuote(false); };
      ctl.addEventListener(fd.type === 'select' ? 'change' : 'input', upd);
      if (fd.role === 'quantity') S.fields[fd.key] = '1';
      fl.appendChild(el('div', { class: 'col-md-6' }, [el('label', { class: 'form-label', for: id, text: fd.label + (fd.required ? '' : ' (facultatif)') }), ctl, fd.help ? el('div', { class: 'form-text', text: fd.help }) : '']));
    });
    var ow = $('options-wrap'), ob = $('options'); ob.innerHTML = '';
    ow.classList.toggle('d-none', !d.options.length);
    d.options.forEach(function (o) {
      var inp = el('input', { type: 'checkbox', value: o.id });
      inp.addEventListener('change', function () { S.options = Array.prototype.slice.call(ob.querySelectorAll('input:checked')).map(function (i) { return +i.value; }); refreshQuote(false); });
      ob.appendChild(el('div', { class: 'col-md-6' }, [el('label', { class: 'opt-card' }, [inp, el('strong', { text: o.name }), ' ', el('span', { class: 'text-primary fw-semibold', text: '+ ' + money(o.price) })])]));
    });
  }

  // ---------------------------------------------------------------- Prix en direct (toujours calculé par le serveur)
  var timer = null;
  function refreshQuote(strict) {
    if (!S.svc) return Promise.resolve(false);
    return new Promise(function (resolve) {
      clearTimeout(timer);
      timer = setTimeout(function () {
        api('/api/quote', payload()).then(function (r) {
          if (r.ok) { S.quote = r.data; showError(''); updateSide(); resolve(true); }
          else { S.quote = null; updateSide(); if (strict) showError(errText(r.data)); resolve(false); }
        });
      }, strict ? 0 : 250);
    });
  }
  function updateSide() {
    var dl = $('side-summary'); dl.innerHTML = '';
    var add = function (t, v) { if (!v) return; dl.appendChild(el('dt', { text: t })); dl.appendChild(el('dd', { text: v })); };
    add('Service', S.svc ? S.svc.name : '—');
    if (S.quote) {
      (S.quote.details || []).forEach(function (d) { add(d[0], d[1]); });
      add('Durée estimée', dur(S.quote.duration));
      if (S.hood) add('Déplacement', S.quote.travel_fee ? money(S.quote.travel_fee) : 'Offert');
    }
    if (S.hood) add('Quartier', S.hood.name);
    if (S.date && S.slot) add('Rendez-vous', fmtDay(S.date) + ' à ' + S.slot);
    $('side-total').textContent = S.quote ? money(S.quote.total) : '—';
    if ($('mobile-total')) $('mobile-total').textContent = S.quote ? money(S.quote.total) : '—';
  }

  // ---------------------------------------------------------------- Lieu : un seul choix de quartier, groupé par commune
  var hoodData = [];
  // ---------------------------------------------------------------- Position : géolocalisation automatique, carte pour corriger
  var G = { lat: null, lng: null, src: null, map: null, marker: null, asked: false, manual: false, prog: false };
  var DAKAR = [14.6937, -17.4441], MAX_KM = 15;
  function setGeo(t, bad) { var s = $('geo-status'); if (s) { s.textContent = t || ''; s.classList.toggle('text-danger', !!bad); } }
  function km(a, b, c, d) { var r = Math.PI / 180, x = (c - a) * r, y = (d - b) * r, h = Math.sin(x / 2) * Math.sin(x / 2) + Math.cos(a * r) * Math.cos(c * r) * Math.sin(y / 2) * Math.sin(y / 2); return 12742 * Math.asin(Math.sqrt(h)); }
  function initMap() {
    if (G.map || !window.L || !$('map')) return;
    L.Icon.Default.prototype.options.imagePath = BASE + '/assets/vendor/leaflet/images/';
    G.map = L.map('map', { scrollWheelZoom: false }).setView(DAKAR, 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { subdomains: 'abc', maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(G.map);
    G.map.on('click', function (e) { place(e.latlng.lat, e.latlng.lng, 'map', false); });
  }
  function place(lat, lng, src, zoom) {
    initMap(); if (!G.map) return;
    G.lat = lat; G.lng = lng; G.src = src;
    if (!G.marker) { G.marker = L.marker([lat, lng], { draggable: true }).addTo(G.map); G.marker.on('dragend', function () { var p = G.marker.getLatLng(); place(p.lat, p.lng, 'map', false); }); }
    else G.marker.setLatLng([lat, lng]);
    if (zoom) G.map.setView([lat, lng], 16); else G.map.panTo([lat, lng]);
    detectHood();
  }
  /** Quartier le plus proche du repère : sélectionné tout seul tant que le client ne l'a pas choisi lui-même. */
  function detectHood() {
    if (G.lat === null || !hoodData.length) return;
    var best = null;
    hoodData.forEach(function (h) { if (h.lat === null || h.lat === undefined) return; var d = km(G.lat, G.lng, parseFloat(h.lat), parseFloat(h.lng)); if (!best || d < best.d) best = { h: h, d: d }; });
    if (!best || best.d > MAX_KM) { setGeo('Cette position est hors des zones desservies. Choisissez votre quartier dans la liste.', true); return; }
    if (!G.manual) { G.prog = true; $('hood').value = best.h.id; $('hood').dispatchEvent(new Event('change')); G.prog = false; }
    setGeo((G.src === 'gps' ? 'Position détectée' : 'Repère placé') + ' · quartier : ' + best.h.name + (G.manual ? '' : '. Vous pouvez le modifier.'));
  }
  function locate() {
    if (!navigator.geolocation) { setGeo('Votre appareil ne permet pas la géolocalisation. Touchez la carte pour placer votre adresse.', true); return; }
    setGeo('Recherche de votre position…');
    navigator.geolocation.getCurrentPosition(function (p) { place(p.coords.latitude, p.coords.longitude, 'gps', true); },
      function (err) { setGeo(err.code === 1 ? 'Position refusée. Touchez la carte pour placer votre adresse.' : 'Position introuvable. Touchez la carte pour placer votre adresse.', true); },
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
  }
  if ($('geo-btn')) $('geo-btn').addEventListener('click', function () { G.manual = false; locate(); });

  function loadHoods(selectedId) {
    api('/api/geo/all/0').then(function (r) {
      hoodData = Array.isArray(r.data) ? r.data : [];
      hoodData.forEach(function (h) { h.lat = h.lat === null ? null : parseFloat(h.lat); h.lng = h.lng === null ? null : parseFloat(h.lng); });
      var sel = $('hood'); sel.innerHTML = ''; sel.appendChild(el('option', { value: '', text: hoodData.length ? 'Choisir votre quartier' : 'Aucun quartier desservi pour le moment' }));
      var groups = {}, order = [];
      hoodData.forEach(function (h) { var g = h.commune + (h.city && h.city !== h.commune ? ' · ' + h.city : ''); if (!groups[g]) { groups[g] = []; order.push(g); } groups[g].push(h); });
      order.forEach(function (g) {
        var parent = order.length > 1 ? sel.appendChild(el('optgroup', { label: g })) : sel;
        groups[g].forEach(function (h) { parent.appendChild(el('option', { value: h.id, text: h.name })); });
      });
      if (selectedId && hoodData.some(function (h) { return h.id === selectedId; })) { G.prog = true; sel.value = selectedId; sel.dispatchEvent(new Event('change')); G.prog = false; }
      if (G.lat !== null) detectHood();
    });
  }
  $('hood').addEventListener('change', function () {
    if (!G.prog) G.manual = true;   // le client a choisi lui-même : on ne le change plus
    var h = hoodData.filter(function (x) { return String(x.id) === $('hood').value; })[0];
    S.date = S.slot = null;
    if (!h) { S.hood = null; $('hood-info').textContent = ''; updateSide(); return; }
    S.hood = { id: h.id, name: h.name, fee: h.travel_fee };
    var info = 'Frais de déplacement : ' + (h.travel_fee ? money(h.travel_fee) : 'offerts') + '.';
    if (h.days) info += ' Nous intervenons ici : ' + h.days + '.';
    if (h.conditions) info += ' ' + h.conditions;
    $('hood-info').textContent = info;
    refreshQuote(true).then(function (ok) { if (ok) loadDays(); });   // les créneaux apparaissent dès que le quartier est choisi
  });

  // ---------------------------------------------------------------- Étape 4 : calendrier et créneaux
  var MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'], DAYS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
  function parseDay(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function fmtDay(s) { var d = parseDay(s); return DAYS[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()]; }
  function loadDays() {
    $('days').innerHTML = ''; $('slots-wrap').classList.add('d-none'); $('days-loading').textContent = 'Chargement des disponibilités…'; $('days-loading').classList.remove('d-none'); $('dur-label').textContent = S.quote ? dur(S.quote.duration) : '';
    api('/api/availability/days', payload()).then(function (r) {
      $('days-loading').classList.add('d-none');
      if (!r.ok) return showError(errText(r.data));
      S.days = r.data.days; $('dur-label').textContent = dur(r.data.duration);
      var any = false;
      Object.keys(S.days).forEach(function (d) {
        var info = S.days[d], dt = parseDay(d);
        if (info.count > 0) any = true;
        var b = el('button', { type: 'button', class: 'day-btn' + (S.date === d ? ' sel' : ''), 'data-date': d, 'aria-pressed': S.date === d ? 'true' : 'false' },
          [el('small', { text: DAYS[dt.getDay()] }), el('span', { class: 'dn', text: dt.getDate() }), el('small', { text: MONTHS[dt.getMonth()] }), el('small', { text: info.count ? info.count + ' créneau' + (info.count > 1 ? 'x' : '') : 'indisponible' })]);
        if (!info.count) b.disabled = true;
        b.addEventListener('click', function () { pickDay(d); });
        $('days').appendChild(b);
      });
      if (!any) showError("Aucun créneau n'est disponible prochainement dans ce quartier pour cette durée. Appelez-nous ou essayez un autre quartier.");
      else if (S.date) pickDay(S.date, true);
    });
  }
  function pickDay(d, keep) {
    if (!keep) S.slot = null;
    S.date = d;
    document.querySelectorAll('.day-btn').forEach(function (b) { var on = b.dataset.date === d; b.classList.toggle('sel', on); b.setAttribute('aria-pressed', on); });
    api('/api/availability/slots', payload({ date: d })).then(function (r) {
      var box = $('slots'); box.innerHTML = ''; $('slots-wrap').classList.remove('d-none'); $('slots-title').textContent = 'Heures disponibles le ' + fmtDay(d);
      if (!r.ok) return showError(errText(r.data));
      if (!r.data.slots.length) box.appendChild(el('p', { class: 'text-muted', text: 'Plus aucun créneau ce jour-là.' }));
      r.data.slots.forEach(function (s) {
        var b = el('button', { type: 'button', class: 'slot-btn' + (s.recommended ? ' reco' : '') + (S.slot === s.start ? ' sel' : ''), title: s.recommended ? 'Notre équipe est déjà dans votre quartier ce jour-là' : '', text: s.start });
        b.addEventListener('click', function () {
          S.slot = s.start; box.querySelectorAll('.slot-btn').forEach(function (x) { x.classList.remove('sel'); }); b.classList.add('sel'); updateSide(); showError('');
        });
        box.appendChild(b);
      });
      updateSide();
    });
  }

  // ---------------------------------------------------------------- Étape 5 : récapitulatif et envoi
  function renderRecap() {
    var q = S.quote, box = $('recap'); box.innerHTML = '';
    var row = function (t, v) { box.appendChild(el('div', { class: 'd-flex justify-content-between border-bottom py-2 gap-3' }, [el('span', { class: 'text-muted', text: t }), el('strong', { class: 'text-end', text: v })])); };
    row('Service', q.service + (q.formula ? ' — ' + q.formula : ''));
    (q.details || []).forEach(function (d) { if (d[0] !== 'Formule') row(d[0], d[1]); });
    row('Quand', fmtDay(S.date) + ' à ' + S.slot + ' (≈ ' + dur(q.duration) + ')');
    row('Où', $('address').value.trim() + ' — ' + S.hood.name);
    if (G.lat !== null) row('Position', G.src === 'gps' ? 'Détectée ✓' : 'Placée sur la carte ✓');
    if (PH && PH.count()) row('Photos', PH.count() + (PH.count() > 1 ? ' photos jointes' : ' photo jointe'));
    row('Sous-total', money(q.subtotal)); row('Déplacement', q.travel_fee ? money(q.travel_fee) : 'Offert'); row('Total estimé', money(q.total));
  }
  function submit() {
    $('next').disabled = true; $('next').textContent = 'Envoi en cours…';
    var nm = splitName($('full_name').value);
    var body = payload({ date: S.date, start: S.slot, first_name: nm.first, last_name: nm.last, phone: $('phone').value.trim(), email: $('email').value.trim(),
      address_text: $('address').value.trim(), instructions: $('instructions').value.trim(), website: $('website').value, lat: G.lat, lng: G.lng, location_source: G.src, marketing_consent: $('consent') ? $('consent').checked : false });
    api('/reservation', body).then(function (r) {
      if (r.ok) {
        saveProfile();
        if (!PH || !PH.count()) { location.href = r.data.redirect; return; }
        $('next').textContent = 'Envoi des photos…';   // la commande est créée ; une panne d'envoi ne l'annule pas (réessai possible sur la confirmation)
        PH.uploadAll(r.data.reference, CSRF, function (d, t) { $('next').textContent = 'Envoi des photos ' + d + '/' + t + '…'; }).then(function () { location.href = r.data.redirect; });
        return;
      }
      if (r.status === 409) { S.slot = null; showError(r.data.error); go(1); return; }
      if (r.status === 429) { showError('Trop de tentatives. Patientez quelques minutes.'); } else showError(errText(r.data));
      $('next').disabled = false; $('next').textContent = 'Confirmer la réservation';
    }).catch(function () { showError('Connexion impossible. Vérifiez votre réseau puis réessayez : votre réservation n\'a pas été enregistrée.'); $('next').disabled = false; $('next').textContent = 'Confirmer la réservation'; });
  }

  // ---------------------------------------------------------------- Coordonnées retenues sur cet appareil (jamais envoyées ailleurs)
  var PH = (window.CuPhotos && $('photos')) ? window.CuPhotos.mount($('photos'), { deferred: true, onChange: function () { if (S.step === 2) renderRecap(); } }) : null;
  var PROFILE_KEY = 'cu_profile', PROFILE_FIELDS = ['full_name', 'phone', 'email', 'address', 'instructions'];
  function loadProfile() { try { return JSON.parse(localStorage.getItem(PROFILE_KEY) || 'null'); } catch (e) { return null; } }
  function saveProfile() {
    try { var p = {}; PROFILE_FIELDS.forEach(function (f) { p[f] = $(f).value.trim(); }); p.hood = S.hood ? S.hood.id : null; localStorage.setItem(PROFILE_KEY, JSON.stringify(p)); } catch (e) { /* navigation privée : on ne retient rien */ }
  }
  var prof = loadProfile();
  if (prof && prof.full_name) {
    PROFILE_FIELDS.forEach(function (f) { if ($(f) && !$(f).value && prof[f]) $(f).value = prof[f]; });
    if ($('prefill-note')) $('prefill-note').hidden = false;
  }
  if ($('prefill-clear')) $('prefill-clear').addEventListener('click', function (e) {
    e.preventDefault();
    try { localStorage.removeItem(PROFILE_KEY); } catch (x) { /* rien */ }
    PROFILE_FIELDS.forEach(function (f) { if ($(f)) $(f).value = ''; });
    $('prefill-note').hidden = true;
  });

  // ---------------------------------------------------------------- Démarrage
  renderServices();
  loadHoods(prof && prof.hood ? prof.hood : null);
  var pre = SERVICES.filter(function (s) { return s.slug === root.dataset.preselect; })[0];
  if (pre) { selectService(pre); renderServices(); }
  go(0);
})();
