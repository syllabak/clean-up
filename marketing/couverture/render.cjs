const { chromium } = require('playwright'); const path = require('path');
(async () => {
  const b = await chromium.launch();
  for (const [n, w, h, k] of [['couverture-facebook', 820, 312, 2], ['image-partage', 1200, 630, 1]]) {
    const p = await b.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: k });
    await p.goto('file://' + path.join(__dirname, n + '.html')); await p.evaluate(() => document.fonts.ready);
    await p.screenshot({ path: path.join(__dirname, n + '.png') }); console.log('ok', n, w * k + 'x' + h * k); await p.close();
  }
  await b.close();
})();
