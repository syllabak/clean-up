// Scènes : chaque update(t) est une fonction pure du temps.
const $ = (id) => document.getElementById(id);
const show = (el, v) => { el.style.display = v ? 'block' : 'none'; };

const CARDS = [
  { t: 'Automobile', d: 'Lavage à domicile ou au bureau.',
    svg: '<path class="dr" pathLength="1" d="M14 70 L24 44 Q28 38 36 38 H84 Q92 38 96 44 L106 70 V90 H14 Z"/><path class="dr" pathLength="1" d="M30 52 H90"/><circle class="dr" pathLength="1" cx="36" cy="90" r="9"/><circle class="dr" pathLength="1" cx="84" cy="90" r="9"/>' },
  { t: 'Canapés, matelas, tapis', d: 'Injection-extraction, séchage rapide.',
    svg: '<path class="dr" pathLength="1" d="M26 56 V42 Q26 32 36 32 H84 Q94 32 94 42 V56"/><path class="dr" pathLength="1" d="M12 62 Q12 54 20 54 Q28 54 28 62 V78 H92 V62 Q92 54 100 54 Q108 54 108 62 V90 H12 Z"/><path class="dr" pathLength="1" d="M24 98 V106 M96 98 V106"/>' },
  { t: 'Maison & bureaux', d: 'Entretien courant ou grand ménage.',
    svg: '<path class="dr" pathLength="1" d="M12 56 L60 16 L108 56"/><path class="dr" pathLength="1" d="M22 50 V102 H98 V50"/><path class="dr" pathLength="1" d="M50 102 V74 H70 V102"/>' },
];

const STEPS = [
  { title: 'Choisissez votre service', kind: 'row', items: ['Lavage automobile', 'Nettoyage de canapés', 'Nettoyage de bureaux'], pick: 1 },
  { title: 'Votre quartier', kind: 'chip', items: ['Plateau', 'Almadies', 'Mermoz', 'Yoff', 'Ngor', 'Mamelles'], pick: 1 },
  { title: 'Votre créneau', kind: 'chip', items: ['08:00', '09:30', '11:00', '14:30', '16:00', '17:00'], pick: 1 },
];

const S = { bubbles: [], letters: [], cards: [], steps: [] };

function buildScenes() {
  // Bulles (déterministes)
  const rnd = mulberry32(7);
  for (let i = 0; i < 16; i++) {
    const r = 14 + rnd() * 34;
    const el = document.createElement('div');
    el.className = 'b'; el.style.width = el.style.height = r * 2 + 'px';
    $('bubbles').appendChild(el);
    S.bubbles.push({ el, r, x0: rnd() * W, speed: 55 + rnd() * 90, off: rnd() * 2400, ph: rnd() * 6.28, f: 0.5 + rnd() * 0.8 });
  }
  // Lettres du logotype
  [...'Net\'Express'].forEach((ch) => {
    const s = document.createElement('span');
    s.textContent = ch;
    if (ch === "'") s.style.color = COLOR.cyan;
    $('word').appendChild(s); S.letters.push(s);
  });
  // Cartes services
  CARDS.forEach((c, i) => {
    const el = document.createElement('div');
    el.className = 'card'; el.style.top = 520 + i * 340 + 'px';
    el.innerHTML = `<div class="ic"><svg viewBox="0 0 120 120" fill="none" stroke="${COLOR.cyan}" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">${c.svg}</svg></div><div><h3>${c.t}</h3><p>${c.d}</p></div>`;
    $('cards').appendChild(el);
    const paths = [...el.querySelectorAll('.dr')];
    paths.forEach((p) => { p.style.strokeDasharray = '1'; });
    S.cards.push({ el, paths });
  });
  // Étapes de réservation
  STEPS.forEach((st) => {
    const el = document.createElement('div');
    el.className = 'step';
    const wrap = st.kind === 'chip' ? '<div class="chips"></div>' : '<div class="rows"></div>';
    el.innerHTML = `<h4>${st.title}</h4>${wrap}`;
    const host = el.lastChild;
    const items = st.items.map((txt) => {
      const it = document.createElement('div');
      it.className = st.kind; it.textContent = txt; host.appendChild(it); return it;
    });
    $('steps').appendChild(el); S.steps.push({ el, items, st });
  });
  $('s5mark').innerHTML = $('mark').innerHTML.replace('id="g"', 'id="g2"').replace('url(#g)', 'url(#g2)');
}

function updateS1(t) {
  const on = t < T.s1.out; show($('s1'), on); if (!on) return;
  document.querySelectorAll('.dl').forEach((el, i) => {
    const p = seg(t, 0.15 + i * 0.28, 0.5, easeOutCubic);
    el.style.opacity = p; el.style.transform = `translateY(${(1 - p) * 50}px)`;
  });
  const w = seg(t, T.s1.wipe, T.s1.wipeDur, easeInOutCubic);
  const edge = -15 + w * 135; // % de largeur, bord diagonal ±9 %
  $('clean').style.clipPath = `polygon(0 0, ${edge + 9}% 0, ${edge - 9}% 100%, 0 100%)`;
  const line = $('sqLine');
  line.setAttribute('x1', ((edge + 9) / 100) * W); line.setAttribute('x2', ((edge - 9) / 100) * W);
  line.style.opacity = w > 0 && w < 1 ? 1 : 0;
  $('dirtyInner').style.transform = `translateX(${-w * 60}px)`;
  const sp = seg(t, 1.9, 0.8, easeOutCubic);
  $('cleanInner').style.transform = `scale(${1.05 - 0.05 * sp})`;
  $('cleanInner').style.transformOrigin = '30% 50%';
}

function updateBubbles(t) {
  const a = seg(t, 3.2, 0.6) * (1 - seg(t, T.s5.circle, 0.4));
  show($('bubbles'), a > 0); $('bubbles').style.opacity = a;
  S.bubbles.forEach((b) => {
    const span = H + b.r * 2 + 40;
    const y = H + b.r - ((t * b.speed + b.off) % span);
    const x = b.x0 + Math.sin(t * b.f + b.ph) * 22;
    b.el.style.transform = `translate3d(${x - b.r}px,${y - b.r}px,0)`;
  });
}

function updateS2(t) {
  const c = T.s2;
  show($('s2bg'), t < c.end + 0.2);
  $('s2bg').style.clipPath = `circle(${seg(t, c.circle, 0.75, easeInOutCubic) * 1600}px at 540px 960px)`;
  const on = t >= c.circle && t < c.end; show($('s2'), on); if (!on) return;
  const m = seg(t, c.mark, 0.7, easeOutBack);
  const mr = seg(t, c.mark, 0.7, easeOutCubic);
  $('mark').style.opacity = seg(t, c.mark, 0.2);
  $('mark').style.transform = `scale(${0.4 + 0.6 * m}) rotate(${-14 * (1 - mr)}deg)`;
  S.letters.forEach((s, i) => {
    const p = seg(t, c.word + i * 0.04, 0.45, easeOutCubic);
    s.style.opacity = p; s.style.transform = `translateY(${(1 - p) * 60}px)`;
  });
  const tg = seg(t, c.tag, 0.5, easeOutCubic);
  $('tag').style.opacity = tg; $('tag').style.transform = `translateY(${(1 - tg) * 24}px)`;
  const e = seg(t, c.exit, 0.4, easeInCubic);
  $('logo').style.opacity = 1 - e; $('logo').style.transform = `translateY(${-140 * e}px)`;
}

function updateS3(t) {
  const c = T.s3;
  const on = t >= c.in && t < c.end; show($('s3'), on); if (!on) return;
  const tp = seg(t, c.in + 0.15, 0.6, easeOutCubic), te = seg(t, c.exit, 0.35, easeInCubic);
  $('s3t').style.opacity = tp * (1 - te);
  $('s3t').style.transform = `translateY(${(1 - tp) * 40 - te * 80}px)`;
  S.cards.forEach((card, i) => {
    const st = c.cards + i * c.cardGap;
    const p = seg(t, st, 0.75, (x) => easeOutBack(x, 1.1));
    const e = seg(t, c.exit + i * 0.07, 0.4, easeInCubic);
    card.el.style.opacity = seg(t, st, 0.2) * (1 - e);
    card.el.style.transform = `translateX(${(1 - p) * 900 - e * 1000}px)`;
    const d = seg(t, st + 0.45, 0.9, easeInOutCubic);
    card.paths.forEach((p2) => { p2.style.strokeDashoffset = 1 - d; });
  });
}

const MIST = [234, 241, 247], NAVY = [11, 37, 69], PAPER = [246, 250, 253];

function updateS4(t) {
  const c = T.s4;
  const on = t >= c.in && t < c.end; show($('s4'), on); if (!on) return;
  const p = seg(t, c.in, 0.8, easeOutCubic), e = seg(t, c.exit, 0.55, easeInCubic);
  $('phone').style.opacity = 1 - e;
  $('phone').style.transform = `translateY(${(1 - p) * 1000 + e * 420}px) rotate(${(1 - p) * 6}deg)`;
  const tp = seg(t, c.in + 0.1, 0.5, easeOutCubic);
  $('s4t').style.opacity = tp * (1 - e); $('s4t').style.transform = `translateY(${(1 - tp) * 30}px)`;

  const bars = $('prog').children;
  S.steps.forEach((s, k) => {
    const [a, b] = c.steps[k], u = t - a;
    const vis = seg(t, a, 0.2) * (1 - seg(t, b - 0.18, 0.18));
    s.el.style.opacity = vis; show(s.el, vis > 0);
    s.items.forEach((it, i) => {
      const q = seg(t, a + 0.1 + i * 0.06, 0.35, easeOutCubic);
      let sc = 1, bg = MIST, fg = NAVY, extra = 0;
      if (i === s.st.pick) {
        const pp = seg(t, a + 0.45, 0.22, easeOutCubic);
        bg = mixRGB(MIST, NAVY, pp); fg = mixRGB(NAVY, PAPER, pp); sc = 1 + 0.045 * Math.sin(pp * Math.PI);
      }
      it.style.background = rgb(bg); it.style.color = rgb(fg);
      it.style.transform = `translateY(${(1 - q) * 30 + extra}px) scale(${sc})`;
      it.style.opacity = q;
    });
    bars[k].style.setProperty('--p', seg(t, a + 0.45, 0.25, easeOutCubic));
  });
  const sp = seg(t, c.success, 0.5, easeOutBack);
  $('success').style.opacity = seg(t, c.success, 0.2);
  $('okC').style.transformOrigin = '60px 60px'; $('okC').style.transform = `scale(${sp})`;
  const okP = $('okP'); okP.style.strokeDasharray = '1'; okP.style.strokeDashoffset = 1 - seg(t, c.success + 0.2, 0.5, easeInOutCubic);
  const tx = seg(t, c.success + 0.5, 0.4, easeOutCubic);
  $('okT').style.opacity = tx; $('okS').style.opacity = tx;
  $('okT').style.transform = $('okS').style.transform = `translateY(${(1 - tx) * 24}px)`;
}

function updateS5(t) {
  const c = T.s5;
  const on = t >= c.circle; show($('s5'), on); if (!on) return;
  $('s5bg').style.clipPath = `circle(${seg(t, c.circle, 0.8, easeInOutCubic) * 2300}px at 540px 1920px)`;
  [...document.querySelectorAll('#s5c > *')].forEach((el, i) => {
    const p = seg(t, c.content + i * 0.15, 0.6, easeOutCubic);
    el.style.opacity = p; el.style.transform = `translateY(${(1 - p) * 50}px)`;
  });
  const u = t - c.pulse;
  const b = $('btn');
  if (u > 0 && u < 0.6) b.style.transform = `scale(${1 + 0.06 * Math.sin((u / 0.6) * Math.PI)})`;
}

function renderFrame(t) {
  updateS1(t); updateBubbles(t); updateS2(t); updateS3(t); updateS4(t); updateS5(t);
}
