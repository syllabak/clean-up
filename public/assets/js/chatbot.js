/* Assistant de réservation : prend un rendez-vous par conversation, sans compte.
   Il n'invente rien : prix, disponibilités et enregistrement passent par les mêmes routes que le formulaire de réservation
   (/api/services/{id}/form, /api/quote, /api/availability/*, /reservation). Aucune IA externe, aucun service tiers. */
(function () {
  'use strict';
  var root = document.getElementById('cb-root'); if (!root) return;
  var BASE = (document.querySelector('meta[name="base-path"]') || {}).content || '';
  var CFG = null, RESTART = { restart: true };
  var MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'], DAYS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];

  // ---------------------------------------------------------------- petits outils
  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) { if (k === 'text') n.textContent = attrs[k]; else n.setAttribute(k, attrs[k]); });
    (kids || []).forEach(function (c) { n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }
  var norm = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9 ]+/g, ' ').replace(/\s+/g, ' ').trim(); };
  var money = function (n) { return new Intl.NumberFormat('fr-FR').format(n).replace(/ /g, ' ') + ' ' + (CFG ? CFG.currency : 'FCFA'); };
  var dur = function (m) { var h = Math.floor(m / 60), r = m % 60; return h ? h + ' h' + (r ? ' ' + String(r).padStart(2, '0') : '') : r + ' min'; };
  function parseDay(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function fmtDay(s) { var d = parseDay(s); return DAYS[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()]; }
  function lev(a, b) {   // distance d'édition : tolère des fautes de frappe sur un nom de quartier
    var m = a.length, n = b.length, d = [], i, j; if (Math.abs(m - n) > 3) return 9;
    for (i = 0; i <= m; i++) { d[i] = [i]; } for (j = 1; j <= n; j++) d[0][j] = j;
    for (i = 1; i <= m; i++) for (j = 1; j <= n; j++) d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
    return d[m][n];
  }
  function api(url, body) {
    var opt = body === undefined ? { headers: { Accept: 'application/json' } }
      : { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': CFG ? CFG.csrf : '' }, body: JSON.stringify(body) };
    return fetch(BASE + url, opt).then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, status: r.status, data: d }; }); });
  }
  function errText(d) { if (d && d.errors) return Object.keys(d.errors).map(function (k) { return d.errors[k]; }).join(' '); return (d && d.error) || 'Une erreur est survenue.'; }
  var wait = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };

  // ---------------------------------------------------------------- interface
  var launch = el('button', { type: 'button', class: 'cb-launch', 'aria-expanded': 'false', 'aria-controls': 'cb-panel' }, [el('span', { class: 'cb-dot', 'aria-hidden': 'true' }), 'Prendre rendez-vous']);
  var log = el('div', { class: 'cb-log', role: 'log', 'aria-live': 'polite' });
  var choicesBox = el('div', { class: 'cb-choices' });
  var input = el('input', { class: 'cb-input', type: 'text', maxlength: '300', autocomplete: 'off', 'aria-label': 'Votre message', placeholder: 'Écrivez ici…' });
  var send = el('button', { type: 'submit', class: 'cb-send' }, ['Envoyer']);
  var form = el('form', { class: 'cb-form' }, [input, send]);
  var restartBtn = el('button', { type: 'button', class: 'cb-hbtn', 'aria-label': 'Recommencer la conversation', title: 'Recommencer' }, ['↺']);
  var closeBtn = el('button', { type: 'button', class: 'cb-hbtn', 'aria-label': 'Fermer l\'assistant' }, ['×']);
  var panel = el('section', { class: 'cb-panel', id: 'cb-panel', role: 'dialog', 'aria-label': 'Assistant de réservation', hidden: 'hidden' }, [
    el('header', { class: 'cb-head' }, [el('strong', { text: 'Assistant de réservation' }), el('span', { class: 'cb-hs' }, [restartBtn, closeBtn])]), log, choicesBox, form]);
  root.appendChild(launch); root.appendChild(panel);

  function scroll() { log.scrollTop = log.scrollHeight; }
  function bubble(text, who) { var b = el('div', { class: 'cb-b ' + who }); b.textContent = text; log.appendChild(b); scroll(); return b; }
  function say(text) { return bubble(text, 'bot'); }
  function sayLines(lines) {   // un saut de ligne entre deux éléments, sauf si on a déjà fourni un <br> explicite
    var b = el('div', { class: 'cb-b bot' }), prevBr = true;
    lines.forEach(function (l, i) {
      var isBr = l instanceof Node && l.nodeName === 'BR';
      if (i && !prevBr && !isBr && !(typeof l === 'string' && l === ' · ')) b.appendChild(el('br'));
      b.appendChild(l instanceof Node ? l : document.createTextNode(l)); prevBr = isBr;
    });
    log.appendChild(b); scroll(); return b;
  }
  function link(href, text) { return el('a', { href: href, class: 'cb-a', target: href.indexOf('http') === 0 || href.indexOf('tel:') === 0 ? '_blank' : '_self', rel: 'noopener' }, [text]); }
  async function typing() { var t = el('div', { class: 'cb-b bot cb-typing', 'aria-hidden': 'true' }, ['…']); log.appendChild(t); scroll(); await wait(280); t.remove(); }
  async function sayT(text) { await typing(); return say(text); }

  var pending = null;
  function showChoices(opts) {
    choicesBox.innerHTML = '';
    (opts || []).forEach(function (o) {
      var b = el('button', { type: 'button', class: 'cb-ch' + (o.primary ? ' pri' : '') }, [o.label]);
      b.addEventListener('click', function () { if (!pending) return; bubble(o.label, 'me'); var p = pending; pending = null; choicesBox.innerHTML = ''; p.resolve({ choice: o.value }); });
      choicesBox.appendChild(b);
    });
  }
  /** Attend la réponse du client : un bouton ({choice}) ou du texte libre ({text}). Les commandes générales (menu, recommencer, conseiller) sont traitées ici. */
  function ask(opts, placeholder) {
    showChoices(opts); input.placeholder = placeholder || 'Écrivez ici…';
    return new Promise(function (resolve, reject) {
      pending = { resolve: resolve, reject: reject };
      setTimeout(function () { if (!panel.hidden) input.focus({ preventScroll: true }); }, 50);
    }).then(function (r) {
      if (r.text !== undefined) {
        var n = norm(r.text);
        if (/^(menu|recommencer|recommence|annuler|annule|stop|accueil)$/.test(n)) throw RESTART;
        if (/(conseiller|humain|une personne|appeler|telephone|whatsapp)/.test(n) && n.split(' ').length <= 5) { contactInfo(); return ask(opts, placeholder); }
      }
      return r;
    });
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault(); var v = input.value.trim(); if (!v || !pending) return;
    bubble(v, 'me'); input.value = ''; var p = pending; pending = null; choicesBox.innerHTML = ''; p.resolve({ text: v });
  });
  function contactInfo() {
    var c = CFG.company, lines = ['Vous préférez parler à quelqu\'un ? Notre équipe vous répond' + (c.hours ? ' (' + c.hours + ')' : '') + '.'];
    if (c.phone) lines.push(link('tel:' + c.phone.replace(/[^\d+]/g, ''), 'Appeler ' + c.phone));
    if (c.whatsapp) { lines.push(' · '); lines.push(link('https://wa.me/' + c.whatsapp, 'Écrire sur WhatsApp')); }
    sayLines(lines);
  }

  // ---------------------------------------------------------------- compréhension du texte libre
  var SYN = { 'lavage': 'voiture auto lavage vehicule 4x4 suv berline camionnette', 'canape': 'canape fauteuil sofa salon divan', 'matelas': 'matelas lit', 'tapis': 'tapis moquette', 'menage': 'menage maison appartement logement villa studio',
    'vitre': 'vitre vitres fenetre fenetres baie vitrine', 'bureau': 'bureau bureaux commerce boutique entreprise local', 'travaux': 'travaux chantier renovation', 'repassage': 'repassage repasser linge' };
  function matchService(text) {
    var n = norm(text), best = null, bestScore = 0;
    CFG.services.forEach(function (s) {
      var score = 0, hay = norm(s.name + ' ' + (s.short_description || '')), slug = s.slug || '';
      Object.keys(SYN).forEach(function (k) { if (slug.indexOf(k) >= 0) SYN[k].split(' ').forEach(function (w) { if (n.split(' ').indexOf(w) >= 0) score += 3; }); });
      n.split(' ').forEach(function (w) { if (w.length >= 4 && hay.indexOf(w) >= 0) score += 1; });
      if (score > bestScore) { bestScore = score; best = s; }
    });
    return bestScore >= 2 ? best : null;
  }
  function matchHood(text) {
    var n = norm(text); if (!n) return [];
    var exact = CFG.hoods.filter(function (h) { return norm(h.name) === n; }); if (exact.length) return exact.slice(0, 1);
    var part = CFG.hoods.filter(function (h) { var hn = norm(h.name); return hn.indexOf(n) >= 0 || n.indexOf(hn) >= 0; }); if (part.length) return part.slice(0, 5);
    var near = CFG.hoods.map(function (h) { var hn = norm(h.name); return { h: h, d: lev(hn, n), t: Math.max(2, Math.ceil(Math.max(hn.length, n.length) * 0.45)) }; })
      .filter(function (x) { return x.d <= x.t; }).sort(function (a, b) { return a.d - b.d; }).slice(0, 4).map(function (x) { return x.h; });
    near.fuzzy = true; return near;   // correspondance approximative : on demande confirmation au client
  }
  var STOP = 'quel quelle quels quelles est sont pour avec dans vous votre vos nous notre les des une que qui comment combien faut peut pouvez puis plus aux'.split(' ');
  function matchFaq(text) {
    var words = norm(text).split(' ').filter(function (w) { return w.length >= 4 && STOP.indexOf(w) < 0; }); if (!words.length) return null;
    var best = null, bs = 0;
    CFG.faq.forEach(function (f) { var hay = norm(f.q + ' ' + f.a), s = 0; words.forEach(function (w) { if (hay.indexOf(w) >= 0) s += norm(f.q).indexOf(w) >= 0 ? 2 : 1; }); if (s > bs) { bs = s; best = f; } });
    return bs >= 2 ? best : null;
  }

  // ---------------------------------------------------------------- étapes de la réservation
  async function pickService() {
    await sayT('Quel service souhaitez-vous ? Touchez un choix ou écrivez-le (par exemple « canapé » ou « voiture »).');
    var opts = CFG.services.map(function (s) { return { label: s.name + (s.from_price ? ' · dès ' + money(s.from_price) : ''), value: s.id }; });
    for (;;) {
      var r = await ask(opts);
      if (r.choice !== undefined) return CFG.services.filter(function (s) { return s.id === r.choice; })[0];
      var m = matchService(r.text); if (m) return m;
      say('Je n\'ai pas trouvé ce service. Choisissez dans la liste, ou tapez « conseiller » pour parler à l\'équipe.');
    }
  }

  async function details(svc, B) {
    var r = await api('/api/services/' + svc.id + '/form'); if (!r.ok) throw new Error('form');
    var d = r.data; B.formula = null; B.fields = {}; B.options = []; B.detailsText = [];
    if (d.formulas.length) {
      await sayT('Quelle formule ?');
      var fo = d.formulas.map(function (f) { return { label: f.name + (f.amount ? ' · ' + money(f.amount) + (d.service.billing_unit === 'per_sqm' ? ' /m²' : '') : '') + (f.popular ? ' (la plus choisie)' : ''), value: f.id, primary: f.popular }; });
      var f = null;
      while (!f) {
        var a = await ask(fo);
        if (a.choice !== undefined) f = d.formulas.filter(function (x) { return x.id === a.choice; })[0];
        else { var n = norm(a.text); f = d.formulas.filter(function (x) { return norm(x.name) === n || (n.length > 2 && norm(x.name).indexOf(n) >= 0); })[0]; if (!f) say('Choisissez l\'une des formules proposées.'); }
      }
      B.formula = f.id; B.detailsText.push(f.name);
      if (f.includes && f.includes.length) say('Comprend : ' + f.includes.join(', ') + '.');
    }
    for (var i = 0; i < d.fields.length; i++) {
      var fd = d.fields[i]; if (!fd.required && fd.type !== 'select') continue;
      if (fd.type === 'select') {
        await sayT(fd.label + ' ?');
        var co = fd.choices.map(function (c) { return { label: c.label, value: c.id }; });
        if (!fd.required) co.push({ label: 'Passer', value: -1 });
        var v = null;
        while (v === null) {
          var s = await ask(co);
          if (s.choice !== undefined) v = s.choice;
          else { var t = norm(s.text), mm = fd.choices.filter(function (c) { return norm(c.label).indexOf(t) >= 0 && t.length > 1; }); if (mm.length === 1) v = mm[0].id; else say('Touchez l\'un des choix, s\'il vous plaît.'); }
        }
        if (v !== -1) { B.fields[fd.key] = String(v); B.detailsText.push(fd.choices.filter(function (c) { return c.id === v; })[0].label); }
      } else {
        await sayT(fd.label + ' ?' + (fd.help ? ' ' + fd.help : '') + (fd.min !== null && fd.max !== null ? ' (entre ' + fd.min + ' et ' + fd.max + ')' : ''));
        var okv = null;
        while (okv === null) {
          var q = await ask(fd.role === 'quantity' ? [{ label: '1', value: 1 }, { label: '2', value: 2 }, { label: '3', value: 3 }] : [], 'Un nombre');
          var num = q.choice !== undefined ? q.choice : parseInt(String(q.text).replace(/[^\d]/g, ''), 10);
          var lo = fd.min !== null && fd.min !== undefined ? +fd.min : 0, hi = fd.max !== null && fd.max !== undefined ? +fd.max : 100000;
          if (isNaN(num) || num < lo || num > hi) say('Merci d\'indiquer un nombre entre ' + lo + ' et ' + hi + '.'); else okv = num;
        }
        B.fields[fd.key] = String(okv); B.detailsText.push(fd.label + ' : ' + okv);
      }
    }
    if (d.options.length) {
      await sayT('Souhaitez-vous une option en plus ?');
      for (;;) {
        var oo = d.options.filter(function (o) { return B.options.indexOf(o.id) < 0; }).map(function (o) { return { label: o.name + ' (+' + money(o.price) + ')', value: o.id }; });
        oo.push({ label: B.options.length ? 'C\'est tout' : 'Non merci', value: 0, primary: true });
        var ch = await ask(oo);
        var cv = ch.choice !== undefined ? ch.choice : 0;
        if (!cv) break;
        B.options.push(cv); B.detailsText.push(d.options.filter(function (o) { return o.id === cv; })[0].name);
        if (d.options.length === B.options.length) break;
        await sayT('Ajoutée. Une autre ?');
      }
    }
  }

  function payload(B, extra) { return Object.assign({ service_id: B.svc.id, formula_id: B.formula, fields: B.fields, options: B.options, neighborhood_id: B.hood ? B.hood.id : null }, extra || {}); }

  async function pickHood(B) {
    await sayT('Dans quel quartier se trouve le lieu de l\'intervention ?');
    var popular = CFG.hoods.slice(0, 6).map(function (h) { return { label: h.name, value: h.id }; });
    for (;;) {
      var r = await ask(popular, 'Votre quartier');
      if (r.choice !== undefined) { B.hood = CFG.hoods.filter(function (h) { return h.id === r.choice; })[0]; return; }
      var m = matchHood(r.text);
      if (m.length === 1 && !m.fuzzy) { B.hood = m[0]; return; }
      if (m.length >= 1) {
        await sayT(m.length === 1 ? 'Vous voulez dire ' + m[0].name + ' ?' : 'Lequel de ces quartiers ?');
        var pick = await ask(m.map(function (h) { return { label: h.name + (h.commune ? ' (' + h.commune + ')' : ''), value: h.id, primary: m.length === 1 }; }).concat([{ label: m.length === 1 ? 'Non, un autre' : 'Aucun d\'eux', value: 0 }]));
        if (pick.choice) { B.hood = CFG.hoods.filter(function (h) { return h.id === pick.choice; })[0]; return; }
      } else {
        say('Je ne trouve pas « ' + r.text + ' » parmi nos quartiers. Essayez un autre nom, ou tapez « conseiller » : nous étudions les zones proches.');
      }
    }
  }

  async function pickAddress(B) {
    await sayT('Quelle est l\'adresse, ou un repère facile à trouver ? (rue, villa, près de la pharmacie…)');
    for (;;) {
      var r = await ask([], 'Adresse ou repère');
      if (r.text && r.text.trim().length >= 5) { B.address = r.text.trim(); return; }
      say('Un peu plus de détails, s\'il vous plaît (au moins 5 caractères).');
    }
  }

  async function pickSlot(B) {
    var r = await api('/api/availability/days', payload(B));
    if (!r.ok) { say(errText(r.data)); throw RESTART; }
    var days = Object.keys(r.data.days).filter(function (k) { return r.data.days[k].count > 0; });
    if (!days.length) { say('Aucun créneau n\'est libre prochainement dans ce quartier pour cette durée.'); contactInfo(); throw RESTART; }
    var offset = 0;
    for (;;) {
      await sayT('Quel jour vous convient ?');
      var page = days.slice(offset, offset + 5);
      var opts = [{ label: 'Le plus tôt possible', value: 'first', primary: true }].concat(page.map(function (d) { return { label: fmtDay(d), value: d }; }));
      if (offset + 5 < days.length) opts.push({ label: 'Plus de jours', value: 'more' });
      var a = await ask(opts);
      if (a.choice === 'more') { offset += 5; continue; }
      if (a.text !== undefined) { say('Touchez un jour dans la liste, s\'il vous plaît.'); continue; }
      var day = a.choice === 'first' ? days[0] : a.choice;
      var sl = await api('/api/availability/slots', payload(B, { date: day }));
      if (!sl.ok || !sl.data.slots.length) { say('Plus de créneau ce jour-là. Choisissons un autre jour.'); continue; }
      var slots = sl.data.slots, chosen = null;
      if (a.choice === 'first') chosen = slots[0].start;
      else {
        var so = 0;
        while (!chosen) {
          await sayT('Heures disponibles le ' + fmtDay(day) + ' :');
          var ps = slots.slice(so, so + 8).map(function (s) { return { label: s.start, value: s.start }; });
          if (so + 8 < slots.length) ps.push({ label: 'Autres heures', value: 'more' });
          ps.push({ label: 'Autre jour', value: 'back' });
          var h = await ask(ps);
          if (h.choice === 'more') so += 8; else if (h.choice === 'back') break; else if (h.choice) chosen = h.choice; else say('Touchez une heure dans la liste.');
        }
        if (!chosen) continue;
      }
      B.date = day; B.start = chosen; B.duration = sl.data.duration; return;
    }
  }

  async function contact(B) {
    if (!B.name) {
      await sayT('Votre prénom et votre nom ?');
      for (;;) { var r = await ask([], 'Prénom et nom'); var t = r.text.trim().split(/\s+/); if (t.length >= 2 && r.text.trim().length >= 3) { B.name = r.text.trim(); break; } say('J\'ai besoin du prénom et du nom, par exemple « Awa Diop ».'); }
    }
    if (!B.phone) {
      await sayT('Votre numéro de téléphone (WhatsApp de préférence) ?');
      for (;;) { var p = await ask([], '77 123 45 67'); var dg = p.text.replace(/\D/g, ''); if (dg.length >= 9 && dg.length <= 15) { B.phone = p.text.trim(); break; } say('Ce numéro ne semble pas valide. Exemple : 77 123 45 67.'); }
    }
    if (B.email === undefined) {
      await sayT('Votre email ? Avec un email, nous vous écrivons par email ; sans email, par SMS.');
      for (;;) {
        var e = await ask([{ label: 'Pas d\'email', value: '' }], 'vous@exemple.com');
        if (e.choice !== undefined) { B.email = ''; break; }
        var tx = e.text.trim();
        if (/^(non|pas d email|passer|aucun|-)$/i.test(norm(tx))) { B.email = ''; break; }
        if (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(tx)) { B.email = tx; break; }
        say('Cet email ne semble pas valide. Vous pouvez aussi toucher « Pas d\'email ».');
      }
    }
  }

  async function recap(B) {
    var q = await api('/api/quote', payload(B)); if (!q.ok) { say(errText(q.data)); throw RESTART; }
    B.quote = q.data;
    var lines = ['Voici votre rendez-vous :', el('br'), '• ' + B.svc.name + (B.detailsText.length ? ' (' + B.detailsText.join(', ') + ')' : ''),
      el('br'), '• Le ' + fmtDay(B.date) + ' à ' + B.start + ' (durée estimée : ' + dur(q.data.duration) + ')', el('br'), '• ' + B.address + ', ' + B.hood.name,
      el('br'), '• ' + B.name + ' · ' + B.phone + (B.email ? ' · ' + B.email : ''), el('br'), '• Total estimé : ' + money(q.data.total) + (q.data.travel_fee ? ' (déplacement ' + money(q.data.travel_fee) + ' compris)' : '') + ', à régler à l\'équipe après l\'intervention.'];
    await typing(); sayLines(lines);
    var a = await ask([{ label: 'Confirmer le rendez-vous', value: 'ok', primary: true }, { label: 'Changer l\'heure', value: 'slot' }, { label: 'Changer l\'adresse', value: 'addr' }, { label: 'Tout recommencer', value: 'restart' }]);
    if (a.choice === 'ok' || (a.text && /^(oui|ok|confirme|confirmer|d accord)/.test(norm(a.text)))) return 'ok';
    if (a.choice === 'restart') throw RESTART;
    return a.choice || 'slot';
  }

  async function submit(B) {
    var parts = B.name.trim().split(/\s+/), first = parts.slice(0, -1).join(' '), last = parts[parts.length - 1];
    await typing();
    var r = await api('/reservation', payload(B, { date: B.date, start: B.start, first_name: first, last_name: last, phone: B.phone, email: B.email || '', address_text: B.address, instructions: '', website: '', marketing_consent: false, source: 'chatbot' }));
    if (r.ok) {
      B.done = r.data;
      var ch = B.email ? 'par email à ' + B.email : 'par SMS au ' + B.phone;
      sayLines(['Rendez-vous enregistré. Votre référence : ' + r.data.reference + '.', el('br'), 'Nous vous envoyons la confirmation ' + ch + ', puis un rappel avant l\'intervention.']);
      var acts = [link(r.data.redirect, 'Voir ma confirmation')];
      if (CFG.pay && CFG.pay.length) { acts.push(el('br')); acts.push('Vous pourrez aussi payer en ligne depuis cette page (facultatif).'); }
      sayLines(acts);
      return true;
    }
    if (r.status === 409) { say('Ce créneau vient d\'être pris par quelqu\'un d\'autre. Choisissons-en un nouveau.'); return false; }
    if (r.status === 429) { say('Trop de tentatives pour le moment. Réessayez dans quelques minutes ou appelez-nous.'); contactInfo(); throw RESTART; }
    say('Je n\'ai pas pu enregistrer le rendez-vous : ' + errText(r.data)); contactInfo(); throw RESTART;
  }

  async function booking(preSvc) {
    var B = { svc: preSvc || await pickService() };
    await details(B.svc, B);
    var est = await api('/api/quote', payload(B));
    if (est.ok) await sayT('Estimation pour ' + B.svc.name + ' : ' + money(est.data.subtotal) + ' (hors déplacement, calculé selon votre quartier).');
    await pickHood(B); await pickAddress(B);
    for (;;) {
      await pickSlot(B); await contact(B);
      var step;
      for (;;) {
        step = await recap(B);
        if (step === 'ok') break;
        if (step === 'addr') { await pickAddress(B); continue; }
        break;
      }
      if (step === 'ok') { if (await submit(B)) return; }
    }
  }

  // ---------------------------------------------------------------- menu, services, FAQ, suivi
  async function showServices() {
    var lines = ['Nos services (prix de départ) :'];
    CFG.services.forEach(function (s) { lines.push(el('br')); lines.push('• ' + s.name + (s.from_price ? ' : dès ' + money(s.from_price) : '')); });
    sayLines(lines);
    var r = await ask([{ label: 'Réserver maintenant', value: 'book', primary: true }, { label: 'Retour au menu', value: 'menu' }]);
    if (r.choice === 'book' || (r.text && /(reserv|rendez|rdv)/.test(norm(r.text)))) return booking();
  }

  async function question(first) {
    if (first) await sayT('Posez votre question en quelques mots (prix, paiement, délais, annulation…).');
    for (;;) {
      var r = await ask([{ label: 'Retour au menu', value: 'menu' }], 'Votre question');
      if (r.choice === 'menu') return;
      var f = matchFaq(r.text);
      if (f) { await typing(); sayLines([f.q, el('br'), f.a]); var n = await ask([{ label: 'Prendre rendez-vous', value: 'book', primary: true }, { label: 'Une autre question', value: 'q' }, { label: 'Retour au menu', value: 'menu' }]); if (n.choice === 'book') return booking(); if (n.choice === 'menu') return; continue; }
      say('Je n\'ai pas la réponse à cette question.'); contactInfo();
    }
  }

  async function track() {
    sayLines(['Pour suivre votre réservation, entrez votre référence et votre numéro de téléphone sur la page de suivi : ', link('/suivi', 'Suivre ma réservation'), el('br'), 'Si vous avez reçu un lien de suivi par email ou SMS, il suffit de le toucher.']);
    await ask([{ label: 'Retour au menu', value: 'menu' }]);
  }

  async function menu(greet) {
    if (greet) await sayT('Bonjour ! Je suis l\'assistant de ' + CFG.company.name + '. Je peux prendre votre rendez-vous en deux minutes, directement ici.');
    else await sayT('Que puis-je faire pour vous ?');
    for (;;) {
      var r = await ask([{ label: 'Prendre rendez-vous', value: 'book', primary: true }, { label: 'Services et prix', value: 'svc' }, { label: 'Suivre ma réservation', value: 'track' }, { label: 'Poser une question', value: 'q' }]);
      if (r.choice === 'book') return booking();
      if (r.choice === 'svc') return showServices();
      if (r.choice === 'track') return track();
      if (r.choice === 'q') return question(true);
      var t = norm(r.text);
      var s = matchService(r.text); if (s && !/(prix|tarif|combien)/.test(t)) return booking(s);
      if (/(reserv|rendez|rdv|prendre)/.test(t)) return booking();
      if (/(suivi|suivre|ma reservation)/.test(t)) return track();
      if (/(prix|tarif|combien|services?)/.test(t) && !matchFaq(r.text)) return showServices();
      var f = matchFaq(r.text);
      if (f) { sayLines([f.q, el('br'), f.a]); continue; }
      say('Je n\'ai pas bien compris. Choisissez une option ci-dessous, ou tapez « conseiller » pour parler à l\'équipe.');
    }
  }

  var started = false, running = false;
  async function run() {
    if (running) return; running = true; var greet = true;
    for (;;) {
      try { await menu(greet); greet = false; }
      catch (e) {
        if (e === RESTART) { log.innerHTML = ''; greet = true; continue; }
        say('Désolé, un problème technique est survenu. Vous pouvez réserver avec le formulaire.'); sayLines([link('/reserver', 'Ouvrir le formulaire de réservation')]); contactInfo(); await wait(500); greet = false; log.appendChild(el('hr')); continue;
      }
    }
  }
  function restart() { if (pending) { var p = pending; pending = null; choicesBox.innerHTML = ''; p.reject(RESTART); } }

  // ---------------------------------------------------------------- ouverture et fermeture
  async function open() {
    panel.hidden = false; launch.setAttribute('aria-expanded', 'true'); launch.classList.add('open');
    if (!started) {
      started = true;
      try { var r = await api('/api/chatbot'); if (!r.ok) throw new Error('cfg'); CFG = r.data; }
      catch (e) { say('L\'assistant est momentanément indisponible. Utilisez le formulaire de réservation.'); sayLines([link('/reserver', 'Réserver en ligne')]); return; }
      run();
    }
    setTimeout(function () { input.focus({ preventScroll: true }); }, 60);
  }
  function close() { panel.hidden = true; launch.setAttribute('aria-expanded', 'false'); launch.classList.remove('open'); launch.focus(); }
  launch.addEventListener('click', function () { panel.hidden ? open() : close(); });
  closeBtn.addEventListener('click', close);
  restartBtn.addEventListener('click', function () { if (started && CFG) { if (pending) restart(); else { log.innerHTML = ''; } } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) close(); });
  if (/[?&]assistant=1/.test(location.search)) open();   // lien direct : /?assistant=1
  window.CuChatbot = { open: open };
})();
