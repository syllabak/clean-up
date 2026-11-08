/* Clean Up — « le parcours client en 40 s ». Tout est une fonction pure du temps t : window.__render(t).
   Les repères viennent de la voix off (vo_timing.js, généré par audio/align.py) : chaque scène démarre sur sa phrase. */
(function () {
  'use strict';
  const FPS = 30, D = 40, OFF = 0.3;                       // OFF : la voix démarre 0,3 s après le début de la vidéo
  const VO = window.VO;
  const P = (i) => ({ a: VO.phrases[i].debut + OFF, b: VO.phrases[i].fin + OFF });
  const c = { s1: P(0), s2: P(1), s3: P(2), brand: P(3), p1: P(4), price: P(5), p2: P(6), slots: P(7), p3: P(8), ok: P(9), sms: P(10), track: P(11), team: P(12), result: P(13), cta1: P(14), cta2: P(15) };
  const tr1 = c.p2.a - 0.1, tr2 = c.p3.a - 0.1, trOk = c.ok.a + 1.5;   // changements d'écran dans le téléphone

  // ------------------------------------------------------------------ outils
  const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
  const lerp = (a, b, p) => a + (b - a) * p;
  const eo = (x) => 1 - Math.pow(1 - x, 3), ei = (x) => x * x * x, eio = (x) => (x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2);
  const eb = (x, s = 1.4) => 1 + (s + 1) * Math.pow(x - 1, 3) + s * Math.pow(x - 1, 2);
  const seg = (t, a, d, e) => { const p = clamp((t - a) / d); return e ? e(p) : p; };
  const money = (n) => Math.round(n).toLocaleString('fr-FR').replace(/[  ]/g, ' ') + ' FCFA';
  const typed = (s, t, a, d) => s.slice(0, Math.round(s.length * clamp((t - a) / d)));
  const caret = (t, on) => (on && Math.floor(t * 3) % 2 === 0 ? '<span style="opacity:.55">|</span>' : '');
  function mk(tag, cls, html, parent, css) { const n = document.createElement(tag); if (cls) n.className = cls; if (html) n.innerHTML = html; if (css) n.style.cssText = css; (parent || stage).appendChild(n); return n; }
  const set = (el, o = {}) => { el.style.opacity = o.o === undefined ? 1 : clamp(o.o); el.style.transform = `translate(${o.x || 0}px,${o.y || 0}px) scale(${o.s === undefined ? 1 : o.s}) rotate(${o.r || 0}deg)`; };
  const pop = (t, a, d = 0.45) => ({ o: seg(t, a, 0.15), s: lerp(0.6, 1, eb(seg(t, a, d))) });
  const win = (el, t, a, b) => { el.classList.toggle('hidden', !(t >= a && t <= b)); return t >= a && t <= b; };
  const upd = []; const on = (fn) => upd.push(fn);

  // ------------------------------------------------------------------ dessins
  const DROP = '<svg viewBox="0 0 24 24" fill="#0B2545"><path d="M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z"/></svg>';
  function armchair(dirty) {
    const [a, b, d] = dirty ? ['#8b7b69', '#7d6e5d', '#85755f'] : ['#2f7fd0', '#4592e0', '#3a89d8'];
    let s = `<rect x="70" y="26" width="160" height="124" rx="32" fill="${a}"/><rect x="86" y="42" width="128" height="96" rx="22" fill="${b}"/>
      <rect x="30" y="102" width="62" height="116" rx="28" fill="${a}"/><rect x="208" y="102" width="62" height="116" rx="28" fill="${a}"/><rect x="72" y="140" width="156" height="62" rx="20" fill="${d}"/>
      <rect x="38" y="196" width="224" height="30" rx="13" fill="${a}"/><rect x="54" y="224" width="14" height="30" rx="5" fill="#5a3d2b"/><rect x="232" y="224" width="14" height="30" rx="5" fill="#5a3d2b"/>
      <path d="M92 170h116" stroke="rgba(255,255,255,.28)" stroke-width="3" stroke-dasharray="7 7" stroke-linecap="round" fill="none"/>`;
    if (dirty) s += '<ellipse cx="118" cy="168" rx="26" ry="14" fill="#4a3a2c" opacity=".6"/><ellipse cx="190" cy="82" rx="20" ry="26" fill="#3e3025" opacity=".55" transform="rotate(20 190 82)"/><ellipse cx="58" cy="150" rx="14" ry="20" fill="#3e3025" opacity=".55"/><ellipse cx="236" cy="170" rx="11" ry="16" fill="#4a3a2c" opacity=".5"/><ellipse cx="130" cy="70" rx="12" ry="9" fill="#5b4a36" opacity=".5"/>';
    else s += '<path d="M96 58c18-8 44-10 70-6" stroke="rgba(255,255,255,.55)" stroke-width="9" stroke-linecap="round" fill="none"/><path d="M40 126c0-8 4-14 10-18" stroke="rgba(255,255,255,.45)" stroke-width="7" stroke-linecap="round" fill="none"/>';
    return `<svg viewBox="0 0 300 260" width="100%" height="100%">${s}</svg>`;
  }
  const star = (x, y, r) => `<path d="M${x} ${y - r}Q${x} ${y} ${x + r} ${y}Q${x} ${y} ${x} ${y + r}Q${x} ${y} ${x - r} ${y}Q${x} ${y} ${x} ${y - r}Z" fill="#FFD23F"/>`;
  const SPARKS = `<svg viewBox="0 0 300 260" width="100%" height="100%">${star(236, 44, 20)}${star(62, 70, 13)}${star(246, 196, 12)}${star(150, 8, 10)}${star(36, 190, 11)}</svg>`;
  const CAR = (dirty) => `<svg viewBox="0 0 400 190" width="100%" height="100%"><path d="M20 132Q20 112 46 107L92 101L126 62Q137 52 158 52H254Q278 52 292 70L320 103L360 109Q382 113 382 136V152H20Z" fill="${dirty ? '#7d6c59' : '#2f7fd0'}"/>
    <path d="M139 70L160 62H201V100H112Z" fill="${dirty ? '#8fa3b3' : '#cfe8fb'}"/><path d="M212 62H252Q268 62 279 76L298 100H212Z" fill="${dirty ? '#8fa3b3' : '#cfe8fb'}"/>
    <circle cx="106" cy="152" r="35" fill="#171717"/><circle cx="106" cy="152" r="16" fill="#8d8d8d"/><circle cx="300" cy="152" r="35" fill="#171717"/><circle cx="300" cy="152" r="16" fill="#8d8d8d"/>
    ${dirty ? '<ellipse cx="170" cy="125" rx="46" ry="12" fill="#4a3a2c" opacity=".5"/><ellipse cx="300" cy="118" rx="30" ry="9" fill="#4a3a2c" opacity=".45"/><circle cx="60" cy="125" r="7" fill="#4a3a2c" opacity=".5"/><circle cx="350" cy="130" r="9" fill="#4a3a2c" opacity=".45"/>' : ''}</svg>`;
  const VAN = `<svg viewBox="0 0 460 220" width="100%" height="100%"><path d="M20 160V70Q20 52 40 52H300Q318 52 332 72L372 118L426 128Q442 131 442 148V172H20Z" fill="#EAF4FD"/>
    <path d="M318 74L350 114H300V74Z" fill="#cfe8fb"/><rect x="20" y="124" width="286" height="12" fill="#2EC4F1"/><circle cx="130" cy="96" r="25" fill="#FFD23F"/>
    <g transform="translate(116 82) scale(1.2)" fill="#0B2545"><path d="M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z" transform="translate(0 0) scale(1)"/></g>
    <text x="172" y="106" fill="#0B2545" font-family="Inter,Arial" font-weight="900" font-size="34">Clean Up</text>
    <circle cx="112" cy="176" r="38" fill="#171717"/><circle cx="112" cy="176" r="17" fill="#9a9a9a"/><circle cx="352" cy="176" r="38" fill="#171717"/><circle cx="352" cy="176" r="17" fill="#9a9a9a"/></svg>`;
  const CLOCK = '<svg viewBox="0 0 200 200" width="100%" height="100%"><circle cx="100" cy="100" r="92" fill="#fff"/><circle cx="100" cy="100" r="92" fill="none" stroke="#FFD23F" stroke-width="10"/><g stroke="#0B2545" stroke-width="6" stroke-linecap="round"><path d="M100 22v14M100 164v14M22 100h14M164 100h14"/></g></svg>';

  // ------------------------------------------------------------------ scène
  const stage = document.getElementById('stage');
  const L = () => mk('div', 'layer');

  const bubbles = L(); bubbles.style.pointerEvents = 'none';
  const bub = []; let sd = 7; const rnd = () => ((sd = (sd * 16807) % 2147483647) / 2147483647);
  for (let i = 0; i < 16; i++) { const s = 30 + rnd() * 110; bub.push({ el: mk('div', 'abs', '', bubbles, `width:${s}px;height:${s}px;border-radius:50%;border:4px solid rgba(255,255,255,.12);left:${rnd() * 1000}px;top:0`), x: rnd() * 1000, sp: 40 + rnd() * 80, ph: rnd() * 2000 }); }

  // ----- fond : boue → marine, révélé par un cercle
  const bgMud = L(); bgMud.style.background = 'radial-gradient(900px 900px at 50% 40%, #3a322b, #1f1a16)';
  const bgNavy = L(); bgNavy.style.background = 'radial-gradient(1100px 1000px at 80% 8%, #1f4f93 0%, rgba(31,79,147,0) 60%), linear-gradient(180deg, #0B2545, #13315C)';
  stage.insertBefore(bgMud, bubbles); stage.insertBefore(bgNavy, bubbles);

  // ----- 1. accroche
  const hook = L();
  const kin = (html, top, cls) => mk('div', 'kin ' + (cls || 'big'), html, hook, `top:${top}px`);
  const k1 = kin('Un canapé <em>taché.</em>', 250), k2 = kin('Une voiture <em>poussiéreuse.</em>', 250), k3 = kin('Et pas une minute à <em>perdre&nbsp;?</em>', 250);
  const sofa = mk('div', 'abs', armchair(true), hook, 'left:190px;top:720px;width:700px;height:607px');
  const car = mk('div', 'abs', CAR(true), hook, 'left:140px;top:790px;width:800px;height:380px');
  const clock = mk('div', 'abs', CLOCK, hook, 'left:340px;top:760px;width:400px;height:400px');
  const hand = mk('div', 'abs', '<div style="position:absolute;left:196px;top:96px;width:8px;height:112px;border-radius:5px;background:#0B2545;transform-origin:50% 100%"></div><div style="position:absolute;left:195px;top:140px;width:10px;height:68px;border-radius:5px;background:#e8453c;transform-origin:50% 100%" id="hand2"></div>', hook, 'left:0;top:0;width:400px;height:400px');
  clock.appendChild(hand); hand.style.cssText = 'position:absolute;left:0;top:0;width:400px;height:400px';
  const h1 = hand.children[0], h2 = hand.children[1];
  h1.style.left = '196px'; h1.style.top = '200px'; h1.style.height = '100px'; h1.style.transformOrigin = '50% 0%';
  h2.style.left = '195px'; h2.style.top = '200px'; h2.style.height = '134px'; h2.style.transformOrigin = '50% 0%';
  on((t) => {
    win(hook, t, 0, c.brand.a + 0.7); win(bgMud, t, 0, c.brand.a + 0.6);
    const q = (el, a, b) => { const v = win(el, t, a, b); if (v) set(el, { o: seg(t, a, 0.14) * (1 - seg(t, b - 0.2, 0.2)), y: lerp(40, 0, eo(seg(t, a, 0.4))), s: lerp(0.92, 1, eo(seg(t, a, 0.4))) }); };
    q(k1, 0.02, c.s2.a - 0.05); q(k2, c.s2.a - 0.05, c.s3.a - 0.05); q(k3, c.s3.a - 0.05, c.brand.a + 0.3);
    const sx = seg(t, c.s2.a - 0.1, 0.55, eio);
    if (win(sofa, t, 0, c.s2.a + 0.6)) set(sofa, { o: Math.min(seg(t, 0, 0.3), 1 - sx), x: -sx * 900, s: lerp(0.9, 1, eo(seg(t, 0, 0.7))), r: Math.sin(t * 7) * 0.4 * (1 - sx) });
    if (win(car, t, c.s2.a - 0.1, c.s3.a + 0.5)) { const cx = seg(t, c.s3.a - 0.1, 0.5, eio); set(car, { o: Math.min(seg(t, c.s2.a - 0.1, 0.2), 1 - cx), x: lerp(900, 0, eo(seg(t, c.s2.a - 0.1, 0.6))) - cx * 900 }); }
    if (win(clock, t, c.s3.a - 0.1, c.brand.a + 0.7)) {
      const u = t - c.s3.a; set(clock, { o: seg(t, c.s3.a - 0.1, 0.25) * (1 - seg(t, c.brand.a - 0.05, 0.25)), s: lerp(0.5, 1, eb(seg(t, c.s3.a - 0.1, 0.5))) });
      h1.style.transform = `rotate(${u * 160}deg)`; h2.style.transform = `rotate(${u * 960}deg)`;
    }
  });

  // ----- 2. marque et trois écrans
  const brand = L();
  const logo = mk('div', 'logo', `<div class="drop">${DROP}</div><div class="word">Clean Up</div>`, brand, 'left:150px;top:300px;width:780px;justify-content:center');
  const chips = [['1', 'Votre besoin'], ['2', 'Où et quand'], ['3', 'Contact et photos']].map(([n, tx], i) => mk('div', 'chip', `<b>${n}</b>${tx}`, brand, `top:${690 + i * 170}px`));
  const tag = mk('div', 'cap', 'Tout se réserve en <em>3&nbsp;écrans</em>', brand, 'top:520px;font-size:70px');
  on((t) => {
    const a = c.brand.a; win(brand, t, a - 0.2, c.p1.a + 0.2);
    const wr = seg(t, a - 0.1, 0.7, eio); bgNavy.style.clipPath = `circle(${wr * 1500}px at 540px 900px)`; bgNavy.classList.toggle('hidden', t < a - 0.12);
    set(logo, { o: seg(t, a + 0.1, 0.2) * (1 - seg(t, c.p1.a - 0.2, 0.3)), s: lerp(0.5, 1, eb(seg(t, a + 0.1, 0.5))), y: -seg(t, c.p1.a - 0.2, 0.4, ei) * 80 });
    set(tag, { o: seg(t, a + 1.0, 0.25) * (1 - seg(t, c.p1.a - 0.2, 0.3)), y: lerp(30, 0, eo(seg(t, a + 1.0, 0.4))) });
    chips.forEach((ch, i) => { const p = pop(t, a + 1.35 + i * 0.4); set(ch, { o: p.o * (1 - seg(t, c.p1.a - 0.25, 0.3)), s: p.s, x: -seg(t, c.p1.a - 0.25, 0.4, ei) * 400 }); });
  });

  // ----- 3. téléphone : les trois écrans, le SMS, le suivi
  const phoneL = L();
  const caption = [
    mk('div', 'cap', '<em>1</em> · Votre besoin', phoneL, 'top:120px;font-size:78px'),
    mk('div', 'cap', '<em>2</em> · Où et quand', phoneL, 'top:120px;font-size:78px'),
    mk('div', 'cap', '<em>3</em> · Contact et photos', phoneL, 'top:120px;font-size:78px'),
    mk('div', 'cap', 'Un <em>lien de suivi</em>, un clic', phoneL, 'top:120px;font-size:70px'),
  ];
  const phone = mk('div', '', '', phoneL); phone.id = 'phone';
  const screen = mk('div', '', '', phone); screen.id = 'screen';
  mk('div', 'notch', '', screen); mk('div', 'sbar', '<span>9:41</span><span>●●● 5G ▮</span>', screen);
  mk('div', 'apphead', '<i></i>Clean Up<span style="margin-left:auto;font-size:17px;color:#6b7b8c;font-weight:700">Réserver</span>', screen);
  const stepsEl = mk('div', 'steps', '<div>1 Besoin</div><div>2 Où et quand</div><div>3 Photos</div>', screen);
  const content = mk('div', 'content', '', screen);
  const scr1 = mk('div', 'scr', `<h3>Quel service ?</h3>
    <div class="opt sel0" id="o1">Lavage automobile<small>dès 3 000 FCFA</small></div><div class="opt">Canapés et fauteuils<small>dès 5 000 FCFA</small></div><div class="opt">Matelas<small>dès 6 000 FCFA</small></div>
    <div id="forms" style="margin-top:6px"><div style="font-size:14px;font-weight:800;color:#6b7b8c;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Formule</div>
    <div class="row3"><div class="opt" id="f1">Simple</div><div class="opt" id="f2">Complet</div><div class="opt" id="f3">Premium</div></div></div>`, content);
  const MAP = `<svg viewBox="0 0 440 190" width="100%" height="100%"><rect width="440" height="190" fill="#e9efe2"/><rect x="250" y="12" width="150" height="70" rx="12" fill="#cfe6c4"/><rect x="24" y="112" width="110" height="60" rx="10" fill="#cfe6c4"/>
    <path d="M-10 128L450 60" stroke="#f7d36b" stroke-width="16"/><path d="M-10 128L450 60" stroke="#fff" stroke-width="3" stroke-dasharray="14 12" opacity=".7"/><path d="M180 -10V200M330 -10V200" stroke="#fff" stroke-width="10"/><path d="M-10 40H450M-10 168H450" stroke="#fff" stroke-width="8"/>
    <path d="M60 -10L140 200" stroke="#fff" stroke-width="7"/><path d="M-10 90H190" stroke="#fff" stroke-width="6"/></svg>`;
  const scr2 = mk('div', 'scr', `<h3>Où et quand ?</h3>
    <div class="opt" id="geoBtn" style="display:flex;align-items:center;gap:12px;padding:12px 18px;margin-bottom:12px"><span style="font-size:24px">📍</span><span id="geoTx">Me localiser</span></div>
    <div id="mapBox" style="position:relative;height:190px;border-radius:20px;overflow:hidden;border:3px solid #d9e4ee;margin-bottom:12px">${MAP}
      <div id="pin" style="position:absolute;left:196px;top:62px;width:48px;height:62px;opacity:0"><svg viewBox="0 0 24 32" width="48" height="62"><path d="M12 31C5 21 3 16 3 11a9 9 0 0 1 18 0c0 5-2 10-9 20z" fill="#e8453c"/><circle cx="12" cy="11" r="4" fill="#fff"/></svg></div>
      <div id="halo" style="position:absolute;left:182px;top:96px;width:76px;height:76px;border-radius:50%;background:rgba(29,127,214,.22);border:3px solid rgba(29,127,214,.5);opacity:0"></div></div>
    <div class="field" id="fhood" style="margin-bottom:12px;padding:8px 18px;min-height:0"><label>Votre quartier</label><span id="hoodTx" style="color:#8a99a8">…</span></div>
    <div id="dayz"><div class="days"><div class="day sel">lun.<strong>9</strong>nov.</div><div class="day">mar.<strong>10</strong>nov.</div><div class="day">mer.<strong>11</strong>nov.</div><div class="day">jeu.<strong>12</strong>nov.</div></div>
    <div class="slots"><div class="slot">08:00</div><div class="slot">08:30</div><div class="slot" id="s2">09:00</div><div class="slot">09:30</div><div class="slot">10:00</div><div class="slot">10:30</div><div class="slot">11:00</div><div class="slot">11:30</div></div></div>`, content);
  const scr3 = mk('div', 'scr', `<h3>Contact et photos</h3>
    <div class="field" id="fname" style="margin-bottom:10px"><label>Votre nom</label><span id="nameTx"></span></div><div class="field" id="fphone" style="margin-bottom:10px"><label>Téléphone</label><span id="phoneTx"></span></div>
    <div style="font-size:14px;font-weight:800;color:#6b7b8c;text-transform:uppercase;letter-spacing:.4px;margin:6px 0 8px">Photos avant (facultatif)</div>
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px">
      <div id="ph1" style="width:92px;height:92px;border-radius:16px;overflow:hidden;background:linear-gradient(180deg,#c9cfd6,#aab2bb);padding:6px">${armchair(true)}</div>
      <div id="ph2" style="width:92px;height:92px;border-radius:16px;overflow:hidden;background:linear-gradient(180deg,#cfdbe6,#b7c3cf);padding:8px 4px">${CAR(true)}</div>
      <div class="opt" id="camBtn" style="margin:0;flex:1;text-align:center;padding:14px 6px;font-size:18px">📷 Prendre une photo</div></div>
    <div id="recap" class="opt" style="margin-top:2px;border-color:#cfe0f0">Lavage automobile · Complet<small>lun. 9 nov. à 09:00 · Plateau · 2 photos jointes ✓</small><small style="margin-top:6px;color:#0B2545;font-weight:900;font-size:19px">Total estimé 6 500 FCFA</small></div>`, content);
  const scrOk = mk('div', 'scr', `<div style="text-align:center;padding-top:70px"><div id="okc" style="width:150px;height:150px;border-radius:50%;background:#8EE3C8;margin:0 auto 26px;display:grid;place-items:center"><svg viewBox="0 0 24 24" width="84" height="84"><path id="okp" d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="#0B2545" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="20" stroke-dashoffset="20"/></svg></div>
    <div style="font-size:30px;font-weight:900;letter-spacing:-.5px">Réservation enregistrée</div><div style="display:inline-block;margin-top:16px;background:#0B2545;color:#fff;font-weight:900;font-size:21px;border-radius:14px;padding:9px 20px;letter-spacing:1px">CU-261108-K7QD</div>
    <div style="margin-top:18px;font-size:17px;font-weight:600;color:#5b6b7b;line-height:1.4">Votre lien de suivi<br>vous est envoyé par message.</div></div>`, content);
  const bar = mk('div', 'bar', '<div class="tot">Total estimé<strong id="tot">—</strong></div><div class="btn" id="cont">Continuer</div><div class="btn go" id="btnConf" style="display:none">Confirmer</div>', screen);
  const badge = mk('div', '', 'Sans devis ✓', screen, 'position:absolute;left:30px;top:812px;background:#FFD23F;color:#0B2545;font-weight:900;font-size:17px;border-radius:14px;padding:7px 14px;z-index:6');
  const sms = mk('div', 'sms', `<div style="text-align:center;padding-top:96px"><div style="font-size:92px;font-weight:300;letter-spacing:-2px">9:41</div><div style="font-size:21px;opacity:.75;font-weight:600">lundi 9 novembre</div></div>
    <div class="bubble" id="bub" style="top:330px"><b>CLEAN UP · maintenant</b>Réservation <strong>CU-261108-K7QD</strong> confirmée le lun. 9 nov. à 09:00.<br>Suivre en un clic : <u id="smsLink">cleanup.sn/suivi/k7q…</u></div>`, screen);
  const track = mk('div', 'track', `<h3>Votre réservation</h3><div class="ref">CU-261108-K7QD · Lavage automobile</div>
    <div class="tl"><div class="line"><i id="lineFill"></i></div>
    <div class="st" id="t1"><i>✓</i>Reçue<small>lun. 9 nov., 9:41</small></div><div class="st" id="t2"><i>✓</i>Confirmée<small>nous avons validé votre créneau</small></div>
    <div class="st" id="t3"><i>✓</i>Équipe affectée<small>Équipe Auto</small></div><div class="st" id="t4"><i>●</i>En route<small>chez vous à 09:00</small></div></div>
    <div class="eta" id="eta"><span style="font-size:34px">🚐</span><div>Notre équipe arrive<small>à 09:00 · Plateau</small></div></div>`, screen);
  const ptr = mk('div', 'ptr', '', screen, 'opacity:0'), rip = mk('div', 'rip', '', screen, 'opacity:0');

  // positions des cibles du doigt, mesurées une fois à l'état neutre
  const ctr = (id) => { const k = stage.getBoundingClientRect().width / 1080, sr = screen.getBoundingClientRect(), r = document.getElementById(id).getBoundingClientRect();
    return { x: (r.left - sr.left + r.width / 2) / k, y: (r.top - sr.top + r.height / 2) / k }; };
  const taps = [[c.p1.a + 0.95, 'o1'], [c.p1.a + 1.95, 'f2'], [tr1 - 0.2, 'cont'], [c.p2.a + 0.45, 'geoBtn'], [c.p2.a + 1.5, 'mapBox'], [c.slots.a + 1.35, 's2'], [tr2 - 0.2, 'cont'], [c.ok.a + 0.8, 'btnConf'], [c.track.a + 0.3, 'smsLink'], [c.p3.a + 2.0, 'camBtn']];
  let tapPos = null;
  const setBtn = (id, show) => { document.getElementById(id).style.display = show ? '' : 'none'; };
  const doc = (id) => document.getElementById(id);
  const hoods = doc('hoodTx'), nameTx = doc('nameTx'), phoneTx = doc('phoneTx'), tot = doc('tot');

  on((t) => {
    const a = c.p1.a - 0.5, end = c.team.a - 0.15;
    if (!win(phoneL, t, a, end + 0.5)) return;
    if (!tapPos) {   // mesure à l'état neutre : « Continuer » seul, puis « Confirmer » seul
      setBtn('btnConf', false); setBtn('cont', true);
      const pos = {}; taps.forEach(([, id]) => { if (id !== 'btnConf') pos[id] = ctr(id); });
      setBtn('cont', false); setBtn('btnConf', true); pos.btnConf = ctr('btnConf'); setBtn('btnConf', false); setBtn('cont', true);
      tapPos = taps.map(([tt, id]) => ({ t: tt, ...pos[id] }));
    }
    const pin = seg(t, a, 0.6, eo), pout = seg(t, end, 0.45, ei);
    set(phone, { o: 1 - pout, y: (1 - pin) * 1100 + pout * 1100, r: (1 - pin) * 4 });
    // légendes au-dessus du téléphone
    const capT = [[c.p1.a - 0.2, tr1], [tr1, tr2], [tr2, c.sms.a - 0.1], [c.sms.a - 0.1, end]];
    caption.forEach((el, i) => { const [x, y] = capT[i]; set(el, { o: Math.min(seg(t, x, 0.25), 1 - seg(t, y, 0.2)) * (1 - pout), y: lerp(24, 0, eo(seg(t, x, 0.35))) }); });
    // en-tête d'étapes
    [...stepsEl.children].forEach((d, i) => { const ph = t < tr1 ? 0 : t < tr2 ? 1 : 2; d.className = i < ph ? 'done' : i === ph ? 'on' : ''; });
    // glissement des écrans
    const sl = (el, tin, tout) => set(el, { x: (tin < 0 ? 0 : (1 - seg(t, tin, 0.45, eio)) * 492) - seg(t, tout, 0.45, eio) * 492 });
    sl(scr1, -9, tr1); sl(scr2, tr1, tr2); sl(scr3, tr2, trOk); sl(scrOk, trOk, 999);
    // écran 1
    const sel1 = t >= tapPos[0].t, sel2 = t >= tapPos[1].t;
    doc('o1').classList.toggle('sel', sel1); doc('f2').classList.toggle('sel', sel2);
    set(doc('forms'), { o: seg(t, tapPos[0].t + 0.1, 0.3), y: lerp(24, 0, eo(seg(t, tapPos[0].t + 0.1, 0.4))) });
    const price = seg(t, c.price.a, 1.0, eo) * 6000; const fee = t >= tapPos[4].t + 0.15 ? 500 : 0;
    tot.textContent = t < c.price.a ? (sel2 ? '…' : '—') : money(price + (t > tr1 ? fee : 0));
    const pb = pop(t, c.price.b - 0.9); set(badge, { o: pb.o * (1 - seg(t, tr1 - 0.1, 0.2)), s: pb.s });
    // écran 2 : position détectée, repère déplacé du doigt, quartier trouvé
    const g0 = tapPos[3].t + 0.15, g1 = tapPos[4].t + 0.1;
    doc('geoBtn').classList.toggle('sel', t >= g0); doc('geoTx').textContent = t >= g0 ? 'Position détectée ✓' : 'Me localiser';
    const drop = seg(t, g0, 0.45, (x) => eb(x, 1.2)), mv = seg(t, g1, 0.4, eio);
    set(doc('pin'), { o: seg(t, g0, 0.1), y: lerp(-70, 0, drop) + lerp(0, 8, mv), x: lerp(0, 52, mv) });
    set(doc('halo'), { o: seg(t, g0 + 0.2, 0.3) * (0.6 + 0.4 * Math.sin(t * 6)) * (1 - 0.4 * mv), s: lerp(0.5, 1.15, seg(t, g0 + 0.2, 0.6)), x: lerp(0, 52, mv), y: lerp(0, 8, mv) });
    const hoodOn = t >= g0 + 0.5; hoods.textContent = hoodOn ? 'Plateau' : '…'; hoods.style.color = hoodOn ? '#0B2545' : '#8a99a8'; doc('fhood').classList.toggle('sel', hoodOn && t < g1 + 0.8);
    set(doc('dayz'), { o: seg(t, c.slots.a - 0.1, 0.3), y: lerp(30, 0, eo(seg(t, c.slots.a - 0.1, 0.45))) });
    [...document.querySelectorAll('.slot')].forEach((s, i) => { const p = pop(t, c.slots.a + 0.25 + i * 0.07, 0.3); set(s, { o: p.o, s: p.s }); });
    doc('s2').classList.toggle('sel', t >= tapPos[5].t);
    // écran 3
    nameTx.innerHTML = typed('Awa Diop', t, c.p3.a + 0.3, 0.7) + caret(t, t > c.p3.a + 0.2 && t < c.p3.a + 1.1); doc('fname').classList.toggle('sel', t > c.p3.a + 0.2 && t < c.p3.a + 1.1);
    phoneTx.innerHTML = typed('77 612 34 56', t, c.p3.a + 1.15, 0.85) + caret(t, t > c.p3.a + 1.1 && t < c.p3.a + 2.1); doc('fphone').classList.toggle('sel', t > c.p3.a + 1.1 && t < c.p3.a + 2.0);
    const cb = tapPos[9].t; doc('camBtn').classList.toggle('sel', t >= cb && t < cb + 0.5);
    [['ph1', 0.15], ['ph2', 0.5]].forEach(([id, d]) => { const p = pop(t, cb + d, 0.4); set(doc(id), { o: p.o, s: p.s }); });
    const pr = pop(t, Math.max(c.ok.a - 0.15, cb + 0.95)); set(doc('recap'), { o: pr.o, s: pr.s });
    // bouton : Continuer → Confirmer
    setBtn('cont', t < tr2 + 0.05 || t > trOk); setBtn('btnConf', t >= tr2 + 0.05 && t <= trOk);
    const done = t >= tapPos[7].t; const bc = doc('btnConf'); bc.textContent = done ? 'Confirmée ✓' : 'Confirmer'; bc.style.transform = done ? `scale(${1 + 0.08 * Math.sin(seg(t, tapPos[7].t, 0.4) * Math.PI)})` : '';
    // confirmation
    doc('okp').style.strokeDashoffset = 20 * (1 - seg(t, trOk + 0.2, 0.5, eo)); set(doc('okc'), { s: lerp(0.4, 1, eb(seg(t, trOk + 0.05, 0.5))) });
    bar.style.transform = `translateY(${seg(t, trOk, 0.4, eio) * 140}px)`;
    // SMS puis suivi
    const smsIn = seg(t, c.sms.a - 0.1, 0.45, eo);
    sms.style.opacity = smsIn * (t >= c.track.a + 0.45 ? 0 : 1); sms.style.transform = `translateY(${(1 - smsIn) * 120}px)`; sms.classList.toggle('hidden', smsIn <= 0 || t > c.track.a + 0.9);
    const bp = pop(t, c.sms.a + 0.2, 0.5); set(doc('bub'), { o: bp.o, s: bp.s, y: lerp(-40, 0, eo(seg(t, c.sms.a + 0.2, 0.5))) });
    const lk = doc('smsLink'); lk.style.background = (t > c.sms.a + 1.0 && t < c.track.a + 0.3) ? `rgba(255,210,63,${0.35 + 0.35 * Math.sin(t * 9)})` : 'transparent';
    const trk = seg(t, c.track.a + 0.3, 0.3, eo); track.style.opacity = trk; track.style.transform = `translateY(${(1 - trk) * 80}px)`; track.classList.toggle('hidden', trk <= 0);
    const T0 = c.track.a + 0.65;
    [0, 1, 2].forEach((i) => { const el = doc('t' + (i + 1)); const ok = t >= T0 + i * 0.35; el.firstChild.style.background = ok ? '#1D7FD6' : '#d9e4ee'; set(el, { o: seg(t, T0 + i * 0.45 - 0.2, 0.25) * 0 + 1, x: 0 }); });
    const el4 = doc('t4'); el4.firstChild.style.background = t >= T0 + 1.1 ? '#FFD23F' : '#d9e4ee'; el4.firstChild.style.color = '#0B2545';
    doc('lineFill').style.height = (seg(t, T0, 1.25, eio) * 282) + 'px';
    const ep = pop(t, T0 + 1.2, 0.5); set(doc('eta'), { o: ep.o, s: ep.s });
    // doigt et ondes de tapotement
    let shown = false;
    tapPos.forEach((w) => {
      const dt = t - w.t; if (dt < -0.55 || dt > 0.55) return; shown = true;
      const k = eo(seg(t, w.t - 0.55, 0.5)); const x = lerp(w.x + 70, w.x, k), y = lerp(w.y + 110, w.y, k);
      ptr.style.opacity = Math.min(seg(t, w.t - 0.55, 0.2), 1 - seg(t, w.t + 0.25, 0.25)); ptr.style.transform = `translate(${x}px,${y}px) scale(${1 - 0.18 * Math.max(0, 1 - Math.abs(dt) * 8)})`;
      if (dt >= 0) { rip.style.opacity = 1 - seg(t, w.t, 0.5); rip.style.transform = `translate(${w.x}px,${w.y}px) scale(${lerp(0.7, 2.6, eo(seg(t, w.t, 0.5)))})`; } else rip.style.opacity = 0;
    });
    if (!shown) { ptr.style.opacity = 0; rip.style.opacity = 0; }
  });

  // ----- 4. équipe puis avant / après
  const ba = L();
  const van = mk('div', 'abs', VAN, ba, 'left:150px;top:640px;width:780px;height:372px');
  const capTeam = mk('div', 'cap', 'Une équipe <em>équipée</em>, chez vous', ba, 'top:230px;font-size:76px');
  const tools = ['Aspirateur', 'Injection-extraction', 'Produits pros'].map((tx, i) => mk('div', 'chip', `<b>✓</b>${tx}`, ba, `top:${1090 + i * 130}px;height:100px;font-size:44px;left:140px;width:800px`));
  const panel = mk('div', 'ba', '', ba, 'top:430px');
  const dirtyHalf = mk('div', 'half', `<div style="position:absolute;inset:0;background:linear-gradient(180deg,#c9cfd6,#aab2bb)"></div><div style="position:absolute;left:120px;top:70px;width:660px;height:572px">${armchair(true)}</div>`, panel);
  const cleanHalf = mk('div', 'half', `<div style="position:absolute;inset:0;background:linear-gradient(180deg,#ffffff,#d9ecfb)"></div><div style="position:absolute;left:120px;top:70px;width:660px;height:572px">${armchair(false)}</div><div style="position:absolute;left:120px;top:70px;width:660px;height:572px" id="sparks">${SPARKS}</div>`, panel);
  const divider = mk('div', 'div', '', panel);
  const labB = mk('div', 'lab', 'Avant', panel, 'left:24px;background:#5b4636;color:#fff'); const labA = mk('div', 'lab', 'Après', panel, 'right:24px;background:#FFD23F;color:#0B2545');
  const capRes = mk('div', 'cap', 'Le résultat, <em>le voilà.</em>', ba, 'top:1210px;font-size:84px');
  on((t) => {
    const a = c.team.a - 0.2, b = c.result.b + 0.8; if (!win(ba, t, a, b)) return;
    const vIn = seg(t, c.team.a - 0.2, 0.7, eo), vOut = seg(t, c.result.a - 0.25, 0.5, ei);
    set(van, { o: Math.min(1, vIn * 2) * (1 - vOut), x: lerp(1100, 0, vIn) - vOut * 1100, y: Math.sin(t * 22) * 2 * (1 - vIn) });
    set(capTeam, { o: seg(t, c.team.a, 0.3) * (1 - vOut), y: lerp(30, 0, eo(seg(t, c.team.a, 0.4))) });
    tools.forEach((el, i) => { const p = pop(t, c.team.a + 0.9 + i * 0.4); set(el, { o: p.o * (1 - vOut), s: p.s, x: -vOut * 1100 }); });
    const pin = seg(t, c.result.a - 0.3, 0.6, eo); set(panel, { o: pin * (1 - seg(t, b - 0.4, 0.4)), s: lerp(0.85, 1, pin), y: lerp(120, 0, pin) });
    const f = lerp(100, 50, seg(t, c.result.a + 0.05, 1.1, eio)); cleanHalf.style.clipPath = `inset(0 0 0 ${f}%)`; divider.style.left = f + '%'; divider.style.opacity = seg(t, c.result.a, 0.1);
    set(labB, { o: seg(t, c.result.a - 0.1, 0.3) }); set(labA, { o: seg(t, c.result.a + 0.9, 0.3), s: lerp(0.7, 1, eb(seg(t, c.result.a + 0.9, 0.4))) });
    const sp = doc('sparks'); sp.style.opacity = seg(t, c.result.a + 1.0, 0.3) * (0.6 + 0.4 * Math.sin(t * 8));
    const cp = pop(t, c.result.a + 0.15, 0.5); set(capRes, { o: cp.o * (1 - seg(t, b - 0.4, 0.4)), s: cp.s });
  });

  // ----- 5. appel à l'action
  const cta = L();
  const logo2 = mk('div', 'logo', `<div class="drop">${DROP}</div><div class="word">Clean Up</div>`, cta, 'left:150px;top:300px;width:780px;justify-content:center');
  const head = mk('div', 'kin big', 'Réservez <em>dès maintenant</em>', cta, 'top:560px');
  const url = mk('div', 'url', '<span>cleanup.sn</span>', cta, 'top:900px');
  const pts = ['Prix affiché tout de suite', 'Paiement après l’intervention', 'Position et photos en un geste'].map((tx, i) => mk('div', 'chip', `<b>✓</b>${tx}`, cta, `top:${1090 + i * 130}px;height:100px;font-size:42px;left:120px;width:840px`));
  on((t) => {
    if (!win(cta, t, c.cta1.a - 0.2, D + 1)) return;
    const l = pop(t, c.cta1.a - 0.1, 0.55); set(logo2, { o: l.o, s: l.s });
    const h = pop(t, c.cta2.a - 0.1, 0.5); set(head, { o: h.o, s: h.s });
    const u = pop(t, c.cta2.a + 0.5, 0.5); const pulse = t > c.cta2.a + 1.4 ? 1 + 0.04 * Math.sin((t - c.cta2.a - 1.4) * 5) : 1; set(url, { o: u.o, s: u.s * pulse });
    pts.forEach((el, i) => { const p = pop(t, c.cta2.a + 1.1 + i * 0.35); set(el, { o: p.o, s: p.s }); });
  });

  // ----- sous-titres et bulles de fond
  const sub = mk('div', '', '', stage); sub.id = 'sub';
  const subs = VO.phrases.map((p, i) => ({ a: p.debut + OFF - 0.05, b: p.fin + OFF + 0.35, tx: p.phrase })).slice(3, 13);   // pas de sous-titre sur « le résultat » : le grand texte le porte déjà
  on((t) => {
    const s = subs.find((x) => t >= x.a && t <= x.b);
    sub.innerHTML = s ? `<span>${s.tx}</span>` : ''; sub.style.opacity = s ? Math.min(seg(t, s.a, 0.15), 1 - seg(t, s.b - 0.15, 0.15)) : 0;
    bub.forEach((b, i) => { const y = 2100 - ((t * b.sp + b.ph) % 2300); set(b.el, { o: t > c.brand.a ? 1 : 0, y: y, x: Math.sin(t * 0.8 + i) * 24 }); });
  });

  // ------------------------------------------------------------------ rendu
  window.__fps = FPS; window.__duration = D;
  window.__render = (t) => { upd.forEach((f) => f(t)); };
  const fit = () => { const k = Math.min(innerWidth / 1080, innerHeight / 1920); stage.style.transform = new URLSearchParams(location.search).has('render') ? 'none' : `scale(${k})`; };
  fit(); addEventListener('resize', fit);
  const q = new URLSearchParams(location.search);
  if (q.has('t')) window.__render(parseFloat(q.get('t')));
  else if (!q.has('render')) { const t0 = performance.now(); (function loop() { window.__render(((performance.now() - t0) / 1000) % D); requestAnimationFrame(loop); })(); }
  else window.__render(0);
})();
