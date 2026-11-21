// Calques transparents (1080x1920) pour la vidéo de recrutement : en-tête, titre, pastille ; carte de titre et carte finale.
// Usage : node overlays.cjs <dossier de sortie> <url du formulaire> <téléphone>
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs'), path = require('path');
const [out, formUrl, phone] = process.argv.slice(2);
fs.mkdirSync(out, { recursive: true });
const qr = execFileSync('python3', ['-c', `import segno,io,re,sys
b=io.BytesIO(); segno.make(sys.argv[1],error='m').save(b,kind='svg',scale=1,border=0,dark='#0B2545',light=None,xmldecl=False,svgns=True,nl=False)
s=b.getvalue().decode(); w=re.search(r'width="(\\d+)"',s).group(1); h=re.search(r'height="(\\d+)"',s).group(1)
s=re.sub(r'\\s(width|height)="\\d+"','',s,count=2); print(s.replace('<svg','<svg viewBox="0 0 %s %s" shape-rendering="crispEdges"'%(w,h),1))`, formUrl]).toString();
const DROP = '<svg viewBox="0 0 24 24" fill="#0B2545"><path d="M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z"/></svg>';
const css = `*{box-sizing:border-box;margin:0;padding:0}body{width:1080px;height:1920px;font-family:Inter,"Helvetica Neue",Arial,sans-serif;color:#fff;position:relative;overflow:hidden;background:transparent}
.navy{background:radial-gradient(1100px 1000px at 80% 8%,#1f4f93 0%,rgba(31,79,147,0) 60%),linear-gradient(180deg,#0B2545,#13315C)}
.brand{position:absolute;left:70px;top:90px;display:flex;align-items:center;gap:22px;font-weight:900;font-size:60px;letter-spacing:-1.5px}
.brand i{width:92px;height:92px;border-radius:50%;background:#FFD23F;display:grid;place-items:center}.brand i svg{width:52px;height:52px}
.brand span{margin-left:auto;font-size:30px;font-weight:800;letter-spacing:4px;text-transform:uppercase;color:#FFD23F;position:absolute;left:0;top:112px;white-space:nowrap}
h1{position:absolute;left:70px;right:70px;font-weight:900;letter-spacing:-2.5px;line-height:1.02}h1 em{font-style:normal;color:#FFD23F}
.pill{position:absolute;left:70px;background:#FFD23F;color:#0B2545;font-weight:900;font-size:44px;border-radius:60px;padding:18px 40px}
.frame{position:absolute;left:0;width:1080px;border-top:8px solid #FFD23F;border-bottom:8px solid #FFD23F}`;
const brandBlock = '<div class="brand"><i>' + DROP + '</i>Clean Up<span>On recrute à Dakar</span></div>';
const scenes = {
  'title': `<body class="navy">${brandBlock}<h1 style="top:560px;font-size:132px;text-align:center;left:60px;right:60px">Vous aimez le travail <em>bien fait&nbsp;?</em></h1><div class="pill" style="top:1130px;left:50%;transform:translateX(-50%);white-space:nowrap">Rejoignez l'équipe</div></body>`,
  'caption': (h, p) => `<body>${brandBlock}<h1 style="top:290px;font-size:96px">${h}</h1><div class="pill" style="top:1470px">${p}</div></body>`,
  'end': `<body class="navy">${brandBlock}<h1 style="top:330px;font-size:112px;text-align:center">Postulez <em>en 2 minutes</em></h1>
    <div style="position:absolute;left:290px;top:760px;width:500px;height:500px;background:#fff;border-radius:44px;padding:40px">${qr.replace('<svg', '<svg style="width:100%;height:100%;display:block"')}</div>
    <div style="position:absolute;left:0;right:0;top:1300px;text-align:center;font-size:44px;font-weight:800;color:#cfe2f5">Scannez pour remplir le formulaire</div>
    <div class="pill" style="top:1430px;left:50%;transform:translateX(-50%);white-space:nowrap;font-size:56px">WhatsApp · ${phone}</div>
    <div style="position:absolute;left:0;right:0;top:1640px;text-align:center;font-size:40px;font-weight:700;color:#cfe2f5">Matériel fourni · Formation · Missions de proximité</div></body>`,
};
const caps = [['cap1', 'Un geste simple <em>à apprendre</em>', 'Formation assurée'], ['cap2', 'Matériel et produits <em>fournis</em>', 'On vous équipe'], ['cap3', 'Une <em>formation</em> dès le premier jour', 'Débutants bienvenus'],
  ['cap4', 'Canapés, sièges, tapis : <em>le geste qui compte</em>', 'Nettoyage en profondeur'], ['cap5', 'Des missions <em>près de chez vous</em>', 'Votre quartier, votre équipe']];
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
  const shot = async (name, body, transparent) => { await p.setContent(`<html><head><meta charset="utf-8"><style>${css}</style></head>${body}</html>`); await p.evaluate(() => document.fonts.ready); await p.screenshot({ path: path.join(out, name + '.png'), omitBackground: transparent }); };
  await shot('title', scenes.title, false); await shot('end', scenes.end, false);
  for (const [n, h, pill] of caps) await shot(n, scenes.caption(h, pill), true);
  await b.close(); console.log('calques OK dans', out);
})();
