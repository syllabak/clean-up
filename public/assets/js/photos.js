/* Photos « avant intervention » : prises avec le téléphone ou importées.
   Chaque photo est réduite (1600 px, JPEG) avant l'envoi : une photo de téléphone pèse 3 à 8 Mo, ce serait long sur un réseau mobile.
   Deux usages : à la commande (envoi différé, après la création de la commande) et après coup (envoi immédiat, page de confirmation et de suivi). */
(function () {
  'use strict';
  var BASE = (document.querySelector('meta[name="base-path"]') || {}).content || '';
  var MAX_SIDE = 1600, QUALITY = 0.82;

  function load(file) {   // décode l'image en respectant l'orientation EXIF (photos prises à la verticale)
    if (window.createImageBitmap) return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return viaImg(file); });
    return viaImg(file);
  }
  function viaImg(file) {
    return new Promise(function (res, rej) {
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); res(img); };
      img.onerror = function () { URL.revokeObjectURL(url); rej(new Error('image illisible')); };
      img.src = url;
    });
  }
  /** Réduit une photo. En cas d'échec, renvoie le fichier d'origine : on ne perd jamais une photo pour une question de taille. */
  function compress(file) {
    if (!/^image\//.test(file.type)) return Promise.reject(new Error('Ce fichier n\'est pas une image.'));
    return load(file).then(function (img) {
      var w = img.width, h = img.height, k = Math.min(1, MAX_SIDE / Math.max(w, h));
      var c = document.createElement('canvas'); c.width = Math.round(w * k); c.height = Math.round(h * k);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      return new Promise(function (res) { c.toBlob(function (b) { res(b && b.size < file.size ? b : file); }, 'image/jpeg', QUALITY); });
    }).catch(function () { return file; });
  }
  function send(ref, blob, csrf) {
    var fd = new FormData(); fd.append('photo', blob, 'photo.jpg'); fd.append('_csrf', csrf);
    return fetch(BASE + '/reservation/' + encodeURIComponent(ref) + '/photos', { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-CSRF-Token': csrf } })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, data: d }; }); });
  }

  /** Branche un bloc .cu-photos. opts.deferred : on garde les photos en mémoire ; sinon envoi immédiat vers opts.ref. */
  function mount(root, opts) {
    var max = parseInt(root.dataset.max, 10) || 8, thumbs = root.querySelector('.cu-thumbs'), msg = root.querySelector('.cu-msg');
    var items = [];   // { blob, url } en attente d'envoi (mode différé)
    var count = thumbs.querySelectorAll('.cu-th').length;
    var changed = function () { if (opts.onChange) opts.onChange(); };
    var say = function (t, bad) { msg.textContent = t || ''; msg.classList.toggle('text-danger', !!bad); };
    function add(url, onRemove) {
      var d = document.createElement('div'); d.className = 'cu-th';
      var i = document.createElement('img'); i.src = url; i.alt = 'Photo de la commande'; d.appendChild(i);
      if (onRemove) { var b = document.createElement('button'); b.type = 'button'; b.className = 'cu-rm'; b.setAttribute('aria-label', 'Retirer cette photo'); b.textContent = '×'; b.addEventListener('click', function () { onRemove(d); }); d.appendChild(b); }
      thumbs.appendChild(d); return d;
    }
    function onFiles(files) {
      var list = Array.prototype.slice.call(files);
      var chain = Promise.resolve();
      list.forEach(function (f) {
        chain = chain.then(function () {
          if (count >= max) { say('Maximum ' + max + ' photos.', true); return; }
          say('Préparation de la photo…');
          return compress(f).then(function (blob) {
            var url = URL.createObjectURL(blob);
            if (opts.deferred) {
              var rec = { blob: blob, url: url };
              rec.el = add(url, function (el) { items.splice(items.indexOf(rec), 1); el.remove(); count--; say(''); changed(); }); items.push(rec); count++; say(''); changed();
              return;
            }
            var el = add(url); el.classList.add('cu-up'); count++;
            return send(opts.ref, blob, opts.csrf).then(function (r) {
              el.classList.remove('cu-up');
              if (!r.ok) { el.remove(); count--; say((r.data && r.data.error) || 'Envoi impossible, réessayez.', true); } else say('Photo envoyée.');
            }).catch(function () { el.remove(); count--; say('Connexion impossible : la photo n\'a pas été envoyée.', true); });
          }).catch(function (e) { say(e.message || 'Photo refusée.', true); });
        });
      });
      return chain;
    }
    root.querySelectorAll('input[type=file]').forEach(function (inp) {
      inp.addEventListener('change', function () { if (inp.files && inp.files.length) onFiles(inp.files).then(function () { inp.value = ''; }); });
    });
    return {
      count: function () { return count; },
      /** Envoie toutes les photos gardées en mémoire vers la commande créée. Une panne n'annule jamais la commande. */
      uploadAll: function (ref, csrf, progress) {
        var total = items.length, done = 0, failed = 0, chain = Promise.resolve();
        items.forEach(function (it) {
          chain = chain.then(function () { return send(ref, it.blob, csrf).then(function (r) { if (!r.ok) failed++; }).catch(function () { failed++; }); })
            .then(function () { done++; if (progress) progress(done, total); });
        });
        return chain.then(function () { return { total: total, failed: failed }; });
      }
    };
  }

  window.CuPhotos = { mount: mount, compress: compress };

  // Pages de confirmation et de suivi : blocs prêts à l'emploi (envoi immédiat)
  document.querySelectorAll('.cu-photos[data-ref]').forEach(function (el) { mount(el, { ref: el.dataset.ref, csrf: el.dataset.csrf }); });
})();
