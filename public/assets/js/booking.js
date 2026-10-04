/* Parcours de réservation en 6 étapes. Le prix et les créneaux viennent toujours du serveur ; il est
   recalculé et revalidé à la soumission : rien de ce qui s'affiche ici n'est pris pour argent comptant. */
(function () {
  'use strict';
  var root = document.getElementById('wizard');
  if (!root) return;
  var CSRF = root.dataset.csrf, CUR = root.dataset.currency || 'FCFA';
  var SERVICES = JSON.parse(document.getElementById('services-data').textContent);
  var S = { step: 0, svc: null, def: null, formula: null, fields: {}, options: [], hood: null, date: null, slot: null, quote: null, days: null };
  var STEPS = ['Service', 'Détails', 'Lieu', 'Coordonnées', 'Créneau', 'Récapitulatif'];
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
    return fetch(url, opt).then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, status: r.status, data: d }; }); });
  }
  function showError(msg) { var m = $('msg'); m.textContent = msg; m.classList.toggle('d-none', !msg); if (msg) m.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
  function errText(d) { if (d && d.errors) return Object.keys(d.errors).map(function (k) { return d.errors[k]; }).join(' '); return (d && d.error) || 'Une erreur est survenue. Réessayez.'; }

  function payload(extra) {
    var p = { service_id: S.svc && S.svc.id, formula_id: S.formula, fields: S.fields, options: S.options, neighborhood_id: S.hood && S.hood.id };
    return Object.assign(p, extra || {});
  }

  // ---------------------------------------------------------------- Navigation
  function go(n) {
    S.step = n; showError('');
    document.querySelectorAll('.wiz-step').forEach(function (s) { s.classList.toggle('active', +s.dataset.step === n); });
    var pr = $('progress'); pr.innerHTML = '';
    STEPS.forEach(function (t, i) { pr.appendChild(el('li', { class: i < n ? 'done' : i === n ? 'cur' : '', text: (i + 1) + '. ' + t })); });
    $('prev').style.visibility = n === 0 ? 'hidden' : 'visible';
    $('next').textContent = n === 5 ? 'Confirmer la réservation' : 'Continuer';
    $('next').disabled = false;
    if (n === 4) loadDays();
    if (n === 5) renderRecap();
    window.scrollTo({ top: root.offsetTop - 80, behavior: 'smooth' });
  }
  $('prev').addEventListener('click', function () { if (S.step > 0) go(S.step - 1); });
  $('next').addEventListener('click', function () { validate(S.step).then(function (ok) { if (!ok) return; if (S.step === 5) submit(); else go(S.step + 1); }); });

  function validate(n) {
    showError('');
    if (n === 0) { if (!S.svc) { showError('Choisissez un service.'); return Promise.resolve(false); } return Promise.resolve(true); }
    if (n === 1) return refreshQuote(true);
    if (n === 2) {
      if (!S.hood) { showError('Choisissez votre quartier.'); return Promise.resolve(false); }
      if ($('address').value.trim().length < 5) { showError('Indiquez une adresse précise (au moins 5 caractères).'); return Promise.resolve(false); }
      return refreshQuote(true);
    }
    if (n === 3) {
      var phone = $('phone').value.replace(/\D/g, ''), em = $('email').value.trim();
      if (!$('first_name').value.trim() || !$('last_name').value.trim()) { showError('Indiquez votre prénom et votre nom.'); return Promise.resolve(false); }
      if (phone.length < 9 || phone.length > 15) { showError('Indiquez un numéro de téléphone valide.'); return Promise.resolve(false); }
      if (em && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)) { showError("L'email n'est pas valide."); return Promise.resolve(false); }
      return Promise.resolve(true);
    }
    if (n === 4) { if (!S.slot) { showError('Choisissez une date puis une heure.'); return Promise.resolve(false); } return Promise.resolve(true); }
    return Promise.resolve(true);
  }

  // ---------------------------------------------------------------- Étape 0 : services
  function renderServices() {
    var box = $('service-list'); box.innerHTML = '';
    SERVICES.forEach(function (s) {
      var inp = el('input', { type: 'radio', name: 'service', value: s.id });
      var lab = el('label', { class: 'opt-card' }, [inp, el('strong', { text: s.name }), el('div', { class: 'small text-muted', text: s.short_description || '' }), el('div', { class: 'small fw-semibold text-primary', text: 'dès ' + money(s.from_price) + (s.billing_unit === 'per_sqm' ? ' /m²' : '') })]);
      inp.addEventListener('change', function () { selectService(s); });
      if (S.svc && S.svc.id === s.id) inp.checked = true;
      box.appendChild(el('div', { class: 'col-md-6' }, [lab]));
    });
  }
  function selectService(s) {
    if (S.svc && S.svc.id === s.id) return;
    S.svc = s; S.formula = null; S.fields = {}; S.options = []; S.quote = null; S.date = S.slot = null;
    api('/api/services/' + s.id + '/form').then(function (r) { if (!r.ok) return showError('Service indisponible.'); S.def = r.data; renderDetails(); refreshQuote(false); });
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
        inp.addEventListener('change', function () { S.formula = fm.id; refreshQuote(false); });
        var price = fm.amount ? money(fm.amount) + (d.service.billing_unit === 'per_sqm' ? ' /m²' : '') : '';
        row.appendChild(el('div', { class: 'col-md-4' }, [el('label', { class: 'opt-card' }, [inp, el('strong', { text: fm.name }), el('div', { class: 'small text-muted', text: fm.description || '' }), el('div', { class: 'small fw-semibold text-primary', text: price })])]));
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
  }

  // ---------------------------------------------------------------- Étape 2 : localisation en cascade
  function fill(sel, rows, ph) {
    sel.innerHTML = ''; sel.appendChild(el('option', { value: '', text: ph }));
    rows.forEach(function (r) { sel.appendChild(el('option', { value: r.id, text: r.name })); });
    sel.disabled = rows.length === 0;
  }
  function cascade(parent, child, type, ph, after) {
    $(parent).addEventListener('change', function () {
      S.hood = null; S.slot = S.date = null; $('hood-info').textContent = '';
      var next = ['city', 'commune', 'hood'], i = next.indexOf(child);
      next.slice(i + 1).forEach(function (n) { fill($(n), [], n === 'commune' ? 'Choisir la commune' : 'Choisir le quartier'); });
      if (!$(parent).value) { fill($(child), [], ph); return; }
      api('/api/geo/' + type + '/' + $(parent).value).then(function (r) { fill($(child), r.data, ph); if (after) after(r.data); });
    });
  }
  var hoodData = [];
  cascade('region', 'city', 'cities', 'Choisir la ville');
  cascade('city', 'commune', 'communes', 'Choisir la commune');
  cascade('commune', 'hood', 'neighborhoods', 'Choisir le quartier', function (d) { hoodData = d; });
  $('hood').addEventListener('change', function () {
    var h = hoodData.filter(function (x) { return String(x.id) === $('hood').value; })[0];
    S.date = S.slot = null;
    if (!h) { S.hood = null; $('hood-info').textContent = ''; updateSide(); return; }
    S.hood = { id: h.id, name: h.name, fee: h.travel_fee };
    var info = 'Frais de déplacement : ' + (h.travel_fee ? money(h.travel_fee) : 'offerts') + '.';
    if (h.days) info += ' Nous intervenons ici : ' + h.days + '.';
    if (h.conditions) info += ' ' + h.conditions;
    $('hood-info').textContent = info;
    refreshQuote(false);
  });

  // ---------------------------------------------------------------- Étape 4 : calendrier et créneaux
  var MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'], DAYS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
  function parseDay(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function fmtDay(s) { var d = parseDay(s); return DAYS[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()]; }
  function loadDays() {
    $('days').innerHTML = ''; $('slots-wrap').classList.add('d-none'); $('days-loading').classList.remove('d-none'); $('dur-label').textContent = S.quote ? dur(S.quote.duration) : '';
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
    row('Contact', $('first_name').value.trim() + ' ' + $('last_name').value.trim() + ' · ' + $('phone').value.trim());
    row('Sous-total', money(q.subtotal)); row('Déplacement', q.travel_fee ? money(q.travel_fee) : 'Offert'); row('Total estimé', money(q.total));
  }
  function submit() {
    $('next').disabled = true; $('next').textContent = 'Envoi en cours…';
    var body = payload({ date: S.date, start: S.slot, first_name: $('first_name').value.trim(), last_name: $('last_name').value.trim(), phone: $('phone').value.trim(), email: $('email').value.trim(),
      address_text: $('address').value.trim(), instructions: $('instructions').value.trim(), website: $('website').value });
    api('/reservation', body).then(function (r) {
      if (r.ok) { location.href = r.data.redirect; return; }
      if (r.status === 409) { S.slot = null; showError(r.data.error); go(4); return; }
      if (r.status === 429) { showError('Trop de tentatives. Patientez quelques minutes.'); } else showError(errText(r.data));
      $('next').disabled = false; $('next').textContent = 'Confirmer la réservation';
    }).catch(function () { showError('Connexion impossible. Vérifiez votre réseau puis réessayez : votre réservation n\'a pas été enregistrée.'); $('next').disabled = false; $('next').textContent = 'Confirmer la réservation'; });
  }

  // ---------------------------------------------------------------- Démarrage
  renderServices();
  api('/api/geo/regions/0').then(function (r) { fill($('region'), r.data, 'Choisir la région'); if (r.data.length === 1) { $('region').value = r.data[0].id; $('region').dispatchEvent(new Event('change')); } });
  var pre = SERVICES.filter(function (s) { return s.slug === root.dataset.preselect; })[0];
  if (pre) { selectService(pre); renderServices(); go(1); } else go(0);
})();
