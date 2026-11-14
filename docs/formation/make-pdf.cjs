// Génère guide-formation-cp3s.pdf à partir du HTML. Usage : node make-pdf.cjs
const { chromium } = require('playwright');
const path = require('path');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage();
  await p.goto('file://' + path.join(__dirname, 'guide-formation-cp3s.html')); await p.evaluate(() => document.fonts.ready);
  await p.pdf({ path: path.join(__dirname, 'guide-formation-cp3s.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true });
  await b.close(); console.log('OK');
})();
