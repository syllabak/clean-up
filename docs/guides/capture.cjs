// Capture les écrans RÉELS de l'application pour les guides (parcours client sur téléphone, administration sur ordinateur).
// Prérequis : serveur lancé sur la base d'exemple (voir README), photos d'exemple (make-photos.cjs).
// Usage : BASE=http://127.0.0.1:8140 node capture.cjs        -> img/*.jpg + refs.json
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs'), path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8140';
const OUT = path.join(__dirname, 'img'); fs.mkdirSync(OUT, { recursive: true });
const PHOTOS = process.env.PHOTOS || '/tmp/guide-photos';
const tiles = new Map();

// Fonds de carte : récupérés par curl (le navigateur du bac à sable n'a pas d'accès direct), puis servis à la page.
async function routeTiles(ctx) {
  await ctx.route(/tile\.openstreetmap\.org/, async (route) => {
    const url = route.request().url();
    if (!tiles.has(url)) { try { tiles.set(url, execFileSync('curl', ['-sS', '-m', '20', '-A', 'CleanUpGuide/1.0 (contact@exemple.sn)', '-e', BASE + '/', url], { maxBuffer: 1 << 24 })); } catch (e) { tiles.set(url, null); } }
    const body = tiles.get(url);
    body ? route.fulfill({ status: 200, contentType: 'image/png', body }) : route.abort();
  });
}

// Pastilles numérotées + cadre rouge sur les éléments à expliquer.
async function mark(page, items) {
  await page.evaluate((items) => {
    document.querySelectorAll('.__mk').forEach((n) => n.remove());
    items.forEach(([sel, n, pos], i) => {
      const el = sel.startsWith('//') ? document.evaluate(sel, document, null, 9, null).singleNodeValue : document.querySelector(sel); if (!el) return;
      const r = el.getBoundingClientRect(), sx = window.scrollX, sy = window.scrollY;
      const box = document.createElement('div'); box.className = '__mk';
      box.style.cssText = `position:absolute;left:${r.left + sx - 4}px;top:${r.top + sy - 4}px;width:${r.width + 8}px;height:${r.height + 8}px;border:3px solid #e8453c;border-radius:10px;z-index:99998;pointer-events:none`;
      document.body.appendChild(box);
      const c = document.createElement('div'); c.className = '__mk'; c.textContent = n;
      const p = pos || 'tl', dx = p.includes('r') ? r.width - 8 : -16, dy = p.includes('b') ? r.height - 8 : -16;
      c.style.cssText = `position:absolute;left:${r.left + sx + dx}px;top:${r.top + sy + dy}px;width:32px;height:32px;border-radius:50%;background:#e8453c;color:#fff;font:800 18px/32px Inter,Arial;text-align:center;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35);z-index:99999;pointer-events:none`;
      document.body.appendChild(c);
    });
  }, items);
}
const unmark = (page) => page.evaluate(() => document.querySelectorAll('.__mk').forEach((n) => n.remove()));
const shot = async (page, name, opts = {}) => { await page.addStyleTag({ content: '.wa-float{display:none!important}' }).catch(() => {}); await page.screenshot({ path: path.join(OUT, name + '.jpg'), type: 'jpeg', quality: 88, ...opts }); console.log('  ✓', name); };
const settle = (page, ms = 700) => page.waitForTimeout(ms);

(async () => {
  const browser = await chromium.launch();
  const refs = {};

  // ================================================================= PARCOURS CLIENT (téléphone)
  console.log('Parcours client');
  const mob = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-FR',
    geolocation: { latitude: 14.6955, longitude: -17.4648, accuracy: 25 }, permissions: ['geolocation'] });
  await routeTiles(mob);
  const m = await mob.newPage();
  const errs = []; m.on('pageerror', (e) => errs.push(e.message));

  await m.goto(BASE + '/'); await settle(m, 900);
  await m.evaluate(() => { const b = document.querySelector('.mk-banner'); if (b) b.remove(); });
  await mark(m, [['.btn-lemon', 1, 'tl']]); await shot(m, 'c01-accueil'); await unmark(m);

  await m.goto(BASE + '/reserver'); await settle(m, 900);
  await mark(m, [['#service-list label >> nth=1', 1]].map(([s, n]) => ['#service-list .col-md-6:nth-child(2) label', n, 'tl'])); await shot(m, 'c02-service'); await unmark(m);
  await m.click('#service-list .col-md-6:nth-child(2) label'); await settle(m, 1100);
  for (const sel of await m.$$('#fields select')) await sel.selectOption({ index: 3 }).catch(() => sel.selectOption({ index: 1 }));
  await settle(m, 800);
  await m.evaluate(() => document.querySelector('#sec-needs').scrollIntoView({ block: 'start' })); await settle(m, 400);
  await mark(m, [['#fields select', 1, 'tl'], ['#mobile-total', 2, 'tr']]); await shot(m, 'c03-besoin'); await unmark(m);
  await m.click('#next'); await settle(m, 3500);

  await m.evaluate(() => document.querySelector('#geo-btn').scrollIntoView({ block: 'start' })); await settle(m, 1500);
  await mark(m, [['#geo-btn', 1, 'tl'], ['#map', 2, 'tl'], ['#hood', 3, 'tl']]); await shot(m, 'c04-position'); await unmark(m);
  await m.fill('#address', 'Point E, rue de Fatick, près de la pharmacie'); await settle(m, 1500);
  await m.evaluate(() => document.querySelector('#days').scrollIntoView({ block: 'start' })); await settle(m, 500);
  const days = await m.$$('#days .day-btn:not([disabled])'); await days[1].click(); await settle(m, 800);
  await (await m.$$('#slots .slot-btn'))[3].click(); await settle(m, 400);
  await m.evaluate(() => window.scrollBy(0, -60));
  await mark(m, [['#days', 1, 'tl'], ['#slots', 2, 'tl']]); await shot(m, 'c05-creneau'); await unmark(m);
  await m.click('#next'); await settle(m, 800);

  await m.fill('#full_name', 'Awa Diop'); await m.fill('#phone', '77 612 34 56');
  await m.setInputFiles('#photos input[multiple]', [path.join(PHOTOS, 'canape-1.jpg'), path.join(PHOTOS, 'canape-2.jpg')]); await settle(m, 2500);
  await m.evaluate(() => document.querySelector('#full_name').scrollIntoView({ block: 'start' })); await settle(m, 400);
  await mark(m, [['#full_name', 1, 'tl'], ['#phone', 2, 'tl'], ['#photos .cu-actions', 3, 'tl'], ['#photos .cu-thumbs', 4, 'tl']]); await shot(m, 'c06-photos'); await unmark(m);
  await m.evaluate(() => document.querySelector('#recap').scrollIntoView({ block: 'start' })); await settle(m, 400);
  await mark(m, [['#recap', 1, 'tl'], ['#next', 2, 'tl']]); await shot(m, 'c07-recapitulatif'); await unmark(m);

  await Promise.all([m.waitForURL(/confirmation/, { timeout: 25000 }), m.click('#next')]); await settle(m, 1800);
  refs.reference = (await m.textContent('.ref-box')).trim();
  refs.trackLink = (await m.textContent('.panel.small code')).trim();
  await m.evaluate(() => window.scrollTo(0, 0)); await settle(m, 300);
  await mark(m, [['.ref-box', 1, 'tl']]); await shot(m, 'c08-confirmation'); await unmark(m);
  await m.evaluate(() => document.querySelector('.cu-photos').scrollIntoView({ block: 'center' })); await settle(m, 400);
  await mark(m, [['.cu-photos', 1, 'tl'], ['.panel.small code', 2, 'tl']]); await shot(m, 'c09-photos-recues'); await unmark(m);

  await m.goto(refs.trackLink.replace(/^https?:\/\/[^/]+/, BASE)); await settle(m, 1200);
  await mark(m, [['.wiz-progress', 1, 'tl'], ['.cu-photos', 2, 'tl']]); await shot(m, 'c10-suivi'); await unmark(m);
  console.log('  commande créée :', refs.reference, '| erreurs JS :', errs.length ? errs : 'aucune');

  // ================================================================= ADMINISTRATION (ordinateur)
  console.log('Administration');
  const dsk = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1, locale: 'fr-FR' });
  await routeTiles(dsk);
  const a = await dsk.newPage(); a.on('pageerror', (e) => errs.push('admin: ' + e.message));
  await a.goto(BASE + '/connexion'); await settle(a, 500);
  await mark(a, [['input[name=email]', 1, 'tl'], ['input[name=password]', 2, 'tl']]); await shot(a, 'a01-connexion'); await unmark(a);
  await a.fill('input[name=email]', 'admin@exemple.sn'); await a.fill('input[name=password]', 'Guide#2026-demo');
  await a.locator('form[action$="connexion"] button').first().click(); await a.waitForURL(/admin/, { timeout: 10000 }); await settle(a, 800);

  await mark(a, [['//div[contains(@class,"panel")][.//h2[contains(.,"À faire")]]', 1, 'tl'], ['.admin-search input', 2, 'tl'], ['.admin-side nav', 3, 'tr']]); await shot(a, 'a02-tableau-de-bord'); await unmark(a);

  await a.goto(BASE + '/admin/reservations'); await settle(a, 1200);
  await mark(a, [['table.orders tbody tr:first-child td:nth-child(2) .btn-outline-success', 1, 'tr'], ['table.orders tbody tr:first-child td:nth-child(3) .loc', 2, 'tl'], ['table.orders tbody tr:first-child td:nth-child(4) .ph-row', 3, 'tl'], ['table.orders tbody tr:first-child td:nth-child(5) .stmenu', 4, 'tl']]);
  await shot(a, 'a03-liste-commandes'); await unmark(a);

  await a.click('table.orders tbody tr:first-child .stmenu summary'); await settle(a, 400);
  await mark(a, [['table.orders tbody tr:first-child .stmenu-list', 1, 'tl']]); await shot(a, 'a04-statut-un-clic'); await unmark(a);
  await a.keyboard.press('Escape'); await a.click('body', { position: { x: 5, y: 5 } }).catch(() => {});

  await a.goto(BASE + '/admin/reservations/' + (await a.evaluate(() => 0)) || ''); // sera remplacé ci-dessous
  const row = await dsk.newPage(); await row.goto(BASE + '/admin/reservations?q=' + refs.reference); const href = await row.getAttribute('table.orders tbody tr:first-child a.fw-bold', 'href'); await row.close();
  refs.orderUrl = BASE + href;
  await a.goto(refs.orderUrl); await settle(a, 1200);
  await mark(a, [['.seg-status', 1, 'tl'], ['//div[contains(@class,"panel")][.//h2[normalize-space()="Client"]]', 2, 'tl']]); await shot(a, 'a05-fiche-commande'); await unmark(a);

  await a.evaluate(() => document.querySelector('#carte').scrollIntoView({ block: 'start' })); await settle(a, 3500);
  await mark(a, [['#order-map', 1, 'tl'], ['#carte .btn-primary', 2, 'tl']]); await shot(a, 'a06-localisation'); await unmark(a);

  await a.evaluate(() => document.querySelector('.ph-grid').scrollIntoView({ block: 'center' })); await settle(a, 800);
  await mark(a, [['.ph-grid', 1, 'tl']]); await shot(a, 'a07-photos'); await unmark(a);
  await a.click('.ph-thumb.ph-lg >> nth=0'); await settle(a, 900); await shot(a, 'a08-visionneuse'); await a.keyboard.press('Escape'); await settle(a, 300);

  await a.evaluate(() => window.scrollTo(0, 0)); await settle(a, 300);
  await a.click('.seg-status form:has(button.s-confirmed) button'); await settle(a, 1200);
  await mark(a, [['.seg-status', 1, 'tl']]); await shot(a, 'a09-commande-confirmee'); await unmark(a);
  await mark(a, [['#team_id', 1, 'tl']]); await a.evaluate(() => document.querySelector('#team_id').scrollIntoView({ block: 'center' })); await settle(a, 300);
  await unmark(a); await mark(a, [['#team_id', 1, 'tl']]); await shot(a, 'a10-affectation'); await unmark(a);

  await a.goto(BASE + '/admin/recherche?q=Diop'); await settle(a, 800);
  await mark(a, [['.admin-search input', 1, 'tl'], ['//div[contains(@class,"panel")][.//h2[contains(.,"Réservations")]]', 2, 'tl']]); await shot(a, 'a11-recherche'); await unmark(a);

  await a.goto(BASE + '/admin/configuration'); await settle(a, 700); await shot(a, 'a12-configuration');
  await a.goto(BASE + '/admin/crud/neighborhoods'); await settle(a, 700); await shot(a, 'a13-quartiers');
  const first = await a.getAttribute('a[href*="/admin/crud/neighborhoods/"][href$="/modifier"]', 'href'); await a.goto(BASE + first); await settle(a, 700);
  await mark(a, [['#f_lat', 1, 'tl'], ['#f_lng', 2, 'tl']]).catch(() => {}); await shot(a, 'a14-quartier-coordonnees'); await unmark(a);
  await a.goto(BASE + '/admin/crud/services'); await settle(a, 700); await shot(a, 'a15-services');

  fs.writeFileSync(path.join(__dirname, 'refs.json'), JSON.stringify(refs, null, 2));
  console.log(errs.length ? 'ERREURS JS : ' + JSON.stringify(errs) : 'Aucune erreur JS');
  await browser.close();
})().catch((e) => { console.error('ÉCHEC', e.message.split('\n').slice(0, 3).join(' | ')); process.exit(1); });
