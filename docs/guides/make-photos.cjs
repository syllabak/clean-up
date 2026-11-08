// Génère des photos d'exemple (illustrations) pour les captures des guides : canapé taché, matelas, tapis, voiture, vitres.
// Usage : node make-photos.cjs [dossier]    (défaut : /tmp/guide-photos)
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path');
const out = process.argv[2] || '/tmp/guide-photos';
fs.mkdirSync(out, { recursive: true });

const sofa = (c, stains, extra = '') => `<svg viewBox="0 0 520 300" width="1000" height="577"><rect x="20" y="120" width="480" height="140" rx="40" fill="${c[0]}"/><rect x="50" y="40" width="420" height="140" rx="46" fill="${c[1]}"/>
  <rect x="0" y="120" width="76" height="150" rx="34" fill="${c[0]}"/><rect x="444" y="120" width="76" height="150" rx="34" fill="${c[0]}"/><rect x="80" y="170" width="170" height="70" rx="22" fill="${c[2]}"/><rect x="270" y="170" width="170" height="70" rx="22" fill="${c[2]}"/>
  <rect x="70" y="262" width="22" height="30" rx="6" fill="#4a3220"/><rect x="428" y="262" width="22" height="30" rx="6" fill="#4a3220"/>${stains}${extra}</svg>`;
const stain = (x, y, rx, ry, o = .55) => `<ellipse cx="${x}" cy="${y}" rx="${rx}" ry="${ry}" fill="#3e3025" opacity="${o}"/>`;
const wall = (inner, bg = 'linear-gradient(#e8e3da 0 62%, #8a6a4a 62%)') => `<html><body style="margin:0;width:1200px;height:900px;background:${bg};display:grid;place-items:center">${inner}</body></html>`;

const shots = {
  'canape-1': wall(sofa(['#9a8c7a', '#a79a88', '#8f8170'], stain(150, 205, 38, 18) + stain(340, 90, 26, 30, .4) + stain(380, 215, 20, 12))),
  'canape-2': wall(`<div style="transform:scale(1.25) translateY(40px)">${sofa(['#9a8c7a', '#a79a88', '#8f8170'], stain(150, 205, 48, 22, .6) + stain(120, 200, 22, 12, .5))}</div>`),
  'canape-3': wall(`<div style="transform:rotate(-3deg) scale(1.1)">${sofa(['#9a8c7a', '#a79a88', '#8f8170'], stain(250, 90, 30, 34, .45) + stain(60, 200, 18, 22))}</div>`),
  'matelas': wall('<svg viewBox="0 0 520 300" width="1000" height="577"><rect x="40" y="70" width="440" height="170" rx="18" fill="#e9e2d2"/><rect x="40" y="70" width="440" height="40" rx="18" fill="#f3ede0"/><path d="M60 140h400M60 190h400" stroke="#d5cbb5" stroke-width="3" stroke-dasharray="10 8"/><ellipse cx="190" cy="170" rx="46" ry="26" fill="#b99a5a" opacity=".55"/><ellipse cx="330" cy="150" rx="26" ry="16" fill="#a98a4a" opacity=".5"/></svg>', 'linear-gradient(#cfd6dd 0 55%, #7d5f45 55%)'),
  'tapis': wall('<svg viewBox="0 0 520 300" width="1000" height="577"><polygon points="70,90 450,90 500,250 20,250" fill="#7a4b3a"/><polygon points="110,110 410,110 450,230 70,230" fill="none" stroke="#d9b779" stroke-width="5"/><path d="M130 130l60 30-60 30M300 130l-60 30 60 30M380 130l-50 40 50 40" stroke="#d9b779" stroke-width="4" fill="none"/><ellipse cx="250" cy="180" rx="60" ry="22" fill="#2b1a12" opacity=".55"/></svg>', 'linear-gradient(#e6e0d6 0 50%, #a98a68 50%)'),
  'voiture': wall('<svg viewBox="0 0 400 190" width="1050" height="500"><path d="M20 132Q20 112 46 107L92 101L126 62Q137 52 158 52H254Q278 52 292 70L320 103L360 109Q382 113 382 136V152H20Z" fill="#7d6c59"/><path d="M139 70L160 62H201V100H112Z" fill="#8fa3b3"/><path d="M212 62H252Q268 62 279 76L298 100H212Z" fill="#8fa3b3"/><circle cx="106" cy="152" r="35" fill="#171717"/><circle cx="106" cy="152" r="16" fill="#8d8d8d"/><circle cx="300" cy="152" r="35" fill="#171717"/><circle cx="300" cy="152" r="16" fill="#8d8d8d"/><ellipse cx="170" cy="125" rx="46" ry="12" fill="#4a3a2c" opacity=".5"/><ellipse cx="300" cy="118" rx="30" ry="9" fill="#4a3a2c" opacity=".45"/></svg>', 'linear-gradient(#cfdbe6 0 60%, #8f8f8f 60%)'),
  'vitres': wall('<svg viewBox="0 0 520 300" width="1000" height="577"><rect x="60" y="20" width="400" height="260" rx="8" fill="#b9d3e6"/><path d="M60 150h400M260 20v260" stroke="#e9eef3" stroke-width="12"/><path d="M90 60l60 40M300 200l70 50M120 220l40 20" stroke="#8aa3b5" stroke-width="5" opacity=".6" stroke-linecap="round"/><ellipse cx="380" cy="90" rx="40" ry="18" fill="#7d8f9c" opacity=".35"/></svg>', 'linear-gradient(#d9d4c8, #cbc5b6)'),
};

(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1200, height: 900 } });
  for (const [name, html] of Object.entries(shots)) {
    await p.setContent(html);
    await p.screenshot({ path: path.join(out, name + '.jpg'), type: 'jpeg', quality: 90 });
  }
  await b.close();
  console.log('OK', Object.keys(shots).length, 'photos dans', out);
})();
