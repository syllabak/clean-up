// Rend les affiches réseaux sociaux (html-social/*.html) en PNG 2160 x 2700 (1080 x 1350 à l'échelle 2).
// Usage : node render-social.cjs
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path');
(async () => {
  const dir = path.join(__dirname, 'html-social'), out = path.join(__dirname, 'out');
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch();
  for (const f of fs.readdirSync(dir).filter(n => n.endsWith('.html')).sort()) {
    const page = await browser.newPage({ viewport: { width: 1080, height: 1350 }, deviceScaleFactor: 2 });
    await page.goto('file://' + path.join(dir, f));
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: path.join(out, 'social-' + f.replace('.html', '.png')) });
    await page.close();
    console.log('ok', f);
  }
  await browser.close();
})();
