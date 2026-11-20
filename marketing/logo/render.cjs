// Exporte chaque SVG en PNG (transparent sauf les versions « fond »). Usage : node render.cjs
const { chromium } = require('playwright'); const fs = require('fs'), path = require('path');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ deviceScaleFactor: 1 });
  for (const f of fs.readdirSync(__dirname).filter((n) => n.endsWith('.svg'))) {
    const svg = fs.readFileSync(path.join(__dirname, f), 'utf8');
    const [w, h] = svg.match(/viewBox="0 0 (\d+) (\d+)"/).slice(1).map(Number);
    const target = f.startsWith('icone') ? 1024 : 2000, k = target / (f.startsWith('icone') ? w : w);
    await p.setViewportSize({ width: Math.round(w * k), height: Math.round(h * k) });
    await p.setContent(`<html><body style="margin:0;background:transparent">${svg.replace(/width="\d+" height="\d+"/, `width="${Math.round(w * k)}" height="${Math.round(h * k)}"`)}</body></html>`);
    await p.screenshot({ path: path.join(__dirname, f.replace('.svg', '.png')), omitBackground: true });
    console.log('ok', f);
  }
  await b.close();
})();
