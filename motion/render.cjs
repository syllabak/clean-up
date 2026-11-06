// Rendu déterministe : Playwright (une capture par image) -> ffmpeg (H.264).
// Usage : node motion/render.cjs [--stills]
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

const OUT = path.join(__dirname, 'out');
const STILLS = process.argv.includes('--stills');
const STILL_TIMES = [0.9, 1.7, 3.6, 4.4, 6.4, 8.6, 10.5, 11.9, 12.7, 14.6, 15.9];

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1080, height: 1920 }, deviceScaleFactor: 1 });
  await page.goto('file://' + path.join(__dirname, 'index.html') + '?render=1');
  await page.evaluate(() => document.fonts.ready);
  const { fps, dur } = await page.evaluate(() => ({ fps: window.__fps, dur: window.__duration }));
  const frames = Math.round(fps * dur);

  const ff = spawn('ffmpeg', ['-y', '-loglevel', 'error', '-f', 'image2pipe', '-framerate', String(fps), '-i', '-',
    '-c:v', 'libx264', '-preset', 'slow', '-crf', '16', '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
    path.join(OUT, 'cleanup-promo.mp4')], { stdio: ['pipe', 'inherit', 'inherit'] });
  const done = new Promise((res, rej) => { ff.on('close', (c) => (c === 0 ? res() : rej(new Error('ffmpeg exit ' + c)))); });

  for (let f = 0; f < frames; f++) {
    await page.evaluate((t) => window.__render(t), f / fps);
    const buf = await page.screenshot({ type: 'png' });
    if (!ff.stdin.write(buf)) await new Promise((r) => ff.stdin.once('drain', r));
  }
  ff.stdin.end();
  await done;

  if (STILLS) {
    fs.mkdirSync(path.join(OUT, 'stills'), { recursive: true });
    for (const t of STILL_TIMES) {
      await page.evaluate((x) => window.__render(x), t);
      await page.screenshot({ path: path.join(OUT, 'stills', `t${t.toFixed(1)}.png`) });
    }
  }
  await browser.close();
  console.log(`OK: ${frames} images @ ${fps} fps -> motion/out/cleanup-promo.mp4`);
})().catch((e) => { console.error(e); process.exit(1); });
