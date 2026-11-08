// Rend chaque affiche HTML en PDF (A3, impression) et en PNG (aperçu / réseaux sociaux).
// Usage : node render.cjs
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path');

(async () => {
  const dir = path.join(__dirname, 'html'), out = path.join(__dirname, 'out');
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch();
  for (const f of fs.readdirSync(dir).filter(n => n.endsWith('.html')).sort()) {
    const name = f.replace('.html', '');
    const page = await browser.newPage({ viewport: { width: 1123, height: 1587 }, deviceScaleFactor: 2 });   // A3 à 96 dpi ≈ 1123×1587
    await page.goto('file://' + path.join(dir, f));
    await page.evaluate(() => document.fonts.ready);
    await page.pdf({ path: path.join(out, name + '.pdf'), width: '297mm', height: '420mm', printBackground: true });
    await page.screenshot({ path: path.join(out, name + '.png') });
    await page.close();
    console.log('ok', name);
  }
  await browser.close();
})();
