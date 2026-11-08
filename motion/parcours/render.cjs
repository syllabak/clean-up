// Rendu déterministe : Playwright (une capture par image) -> ffmpeg (H.264 muet) ; le son est ajouté ensuite par mix.sh.
// Usage :
//   node render.cjs --stills 2,6,9.5     images de contrôle dans out/stills/ (rapide)
//   node render.cjs                      vidéo complète -> out/parcours-muet.mp4
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

const OUT = path.join(__dirname, 'out');
const args = process.argv.slice(2);
const stillsIdx = args.indexOf('--stills');

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1080, height: 1920 }, deviceScaleFactor: 1 });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto('file://' + path.join(__dirname, 'index.html') + '?render=1');
  await page.evaluate(() => document.fonts.ready);
  const { fps, dur } = await page.evaluate(() => ({ fps: window.__fps, dur: window.__duration }));

  if (stillsIdx >= 0) {
    const dir = path.join(OUT, 'stills'); fs.mkdirSync(dir, { recursive: true });
    for (const t of args[stillsIdx + 1].split(',').map(Number)) {
      await page.evaluate((x) => window.__render(x), t);
      await page.screenshot({ path: path.join(dir, `t${t.toFixed(2).padStart(5, '0')}.png`) });
    }
    console.log(errors.length ? 'ERREURS JS : ' + errors.join(' | ') : 'OK, aucune erreur JS');
    await browser.close();
    return;
  }

  const frames = Math.round(fps * dur);
  const file = path.join(OUT, 'parcours-muet.mp4');
  const ff = spawn('ffmpeg', ['-y', '-loglevel', 'error', '-f', 'image2pipe', '-framerate', String(fps), '-i', '-',
    '-c:v', 'libx264', '-preset', 'medium', '-crf', '17', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', file], { stdio: ['pipe', 'inherit', 'inherit'] });
  const done = new Promise((res, rej) => { ff.on('close', (c) => (c === 0 ? res() : rej(new Error('ffmpeg exit ' + c)))); });
  for (let f = 0; f < frames; f++) {
    await page.evaluate((t) => window.__render(t), f / fps);
    const buf = await page.screenshot({ type: 'jpeg', quality: 94 });
    if (!ff.stdin.write(buf)) await new Promise((r) => ff.stdin.once('drain', r));
    if (f % 150 === 0) console.log(`image ${f}/${frames}`);
  }
  ff.stdin.end();
  await done;
  console.log(errors.length ? 'ERREURS JS : ' + errors.join(' | ') : `OK : ${frames} images, ${fps} fps -> ${file}`);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
