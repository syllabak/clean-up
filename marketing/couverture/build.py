#!/usr/bin/env python3
"""Photos de couverture Clean Up (Facebook 820x312, partage de lien 1200x630) à partir de ../affiches/contact.json.
Usage : python3 build.py && node render.cjs"""
import json, pathlib, html
ROOT = pathlib.Path(__file__).parent
C = json.loads((ROOT.parent / 'affiches' / 'contact.json').read_text(encoding='utf-8'))
SLOGAN = ('Comme neuf.', 'Chez vous.')
DROP = '<svg viewBox="0 0 24 24" fill="#0B2545"><path d="M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z"/></svg>'

def armchair(clean):
    c, c2, c3 = ('#2f7fd0', '#4592e0', '#3a89d8') if clean else ('#8b7b69', '#7d6e5d', '#85755f')
    s = (f'<rect x="70" y="26" width="160" height="124" rx="32" fill="{c}"/><rect x="86" y="42" width="128" height="96" rx="22" fill="{c2}"/>'
         f'<rect x="30" y="102" width="62" height="116" rx="28" fill="{c}"/><rect x="208" y="102" width="62" height="116" rx="28" fill="{c}"/><rect x="72" y="140" width="156" height="62" rx="20" fill="{c3}"/>'
         f'<rect x="38" y="196" width="224" height="30" rx="13" fill="{c}"/><rect x="54" y="224" width="14" height="30" rx="5" fill="#5a3d2b"/><rect x="232" y="224" width="14" height="30" rx="5" fill="#5a3d2b"/>'
         '<path d="M92 170h116" stroke="rgba(255,255,255,.28)" stroke-width="3" stroke-dasharray="7 7" stroke-linecap="round" fill="none"/>')
    star = lambda x, y, r: f'<path d="M{x} {y-r}Q{x} {y} {x+r} {y}Q{x} {y} {x} {y+r}Q{x} {y} {x-r} {y}Q{x} {y} {x} {y-r}Z" fill="#FFD23F"/>'
    if clean:
        s += '<path d="M96 58c18-8 44-10 70-6" stroke="rgba(255,255,255,.55)" stroke-width="9" stroke-linecap="round" fill="none"/>' + star(246, 40, 22) + star(48, 70, 13) + star(258, 196, 13) + star(150, 8, 10)
    else:
        s += '<ellipse cx="118" cy="168" rx="26" ry="14" fill="#4a3a2c" opacity=".55"/><ellipse cx="190" cy="82" rx="20" ry="26" fill="#3e3025" opacity=".5" transform="rotate(20 190 82)"/><ellipse cx="58" cy="150" rx="14" ry="20" fill="#3e3025" opacity=".5"/>'
    return f'<svg viewBox="0 0 300 260" xmlns="http://www.w3.org/2000/svg">{s}</svg>'

CSS = """*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,'Helvetica Neue',Arial,sans-serif;color:#fff;overflow:hidden;
 background:radial-gradient(120% 140% at 85% 0%,#1d5aa8 0%,rgba(29,90,168,0) 60%),linear-gradient(135deg,#0B2545,#13315C)}
.bubble{position:absolute;border-radius:50%;border:1.5px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05)}
.brand{display:flex;align-items:center;gap:10px;font-weight:900;letter-spacing:-.5px}.brand i{display:grid;place-items:center;border-radius:50%;background:#FFD23F;flex:none}
.sl{font-weight:900;line-height:1;letter-spacing:-.04em}.sl em{font-style:normal;color:#FFD23F}
.chips{display:flex;gap:6px;flex-wrap:wrap}.chips span{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);border-radius:20px;font-weight:700}
.chips span.cta{background:#FFD23F;border:0;color:#0B2545}.cta{font-weight:800;color:#0B2545;background:#FFD23F;border-radius:30px;display:inline-block;white-space:nowrap}
.sofa{position:absolute}.sofa svg{width:100%;height:100%}
.tag{position:absolute;background:#FFD23F;color:#0B2545;font-weight:900;border-radius:20px}
"""
def page(w, h, body, extra=''):
    return f'<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>{CSS}body{{width:{w}px;height:{h}px;position:relative}}{extra}</style></head><body>{body}</body></html>'

phone = html.escape(C['telephone'])
# Facebook : le portrait de profil masque le coin bas-gauche ; tout le texte est à droite de x=250 et le fauteuil occupe la droite.
fb = page(820, 312, f"""
<div class="bubble" style="left:-40px;top:-60px;width:170px;height:170px"></div><div class="bubble" style="left:330px;top:230px;width:70px;height:70px"></div><div class="bubble" style="left:640px;top:-30px;width:90px;height:90px"></div>
<div style="position:absolute;left:250px;top:34px"><div class="brand" style="font-size:27px"><i style="width:40px;height:40px"><svg viewBox="0 0 24 24" width="22" height="22" fill="#0B2545"><path d="M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z"/></svg></i>Clean Up</div>
 <div class="sl" style="font-size:54px;margin-top:20px">{SLOGAN[0]}<br><em>{SLOGAN[1]}</em></div>
 <div class="chips" style="margin-top:16px;font-size:12px"><span style="padding:4px 10px">Voiture</span><span style="padding:4px 10px">Canapés</span><span style="padding:4px 10px">Matelas</span><span style="padding:4px 10px">Tapis</span><span style="padding:4px 10px">Bureaux</span></div>
 <div class="cta" style="margin-top:14px;font-size:14px;padding:7px 16px">WhatsApp · {phone}</div></div>
<div class="sofa" style="left:590px;top:44px;width:200px;height:173px">{armchair(True)}</div>
<div class="tag" style="left:650px;top:244px;font-size:12px;padding:4px 12px">Dakar et alentours</div>""")
# Image de partage 1200x630 (aperçu de lien WhatsApp, Facebook, site)
og = page(1200, 630, f"""
<div class="bubble" style="left:900px;top:-90px;width:300px;height:300px"></div><div class="bubble" style="left:-50px;top:420px;width:200px;height:200px"></div><div class="bubble" style="left:520px;top:520px;width:90px;height:90px"></div>
<div style="position:absolute;left:80px;top:56px"><div class="brand" style="font-size:54px;gap:18px"><i style="width:86px;height:86px"><svg viewBox="0 0 24 24" width="48" height="48" fill="#0B2545"><path d="M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z"/></svg></i>Clean Up</div>
 <div class="sl" style="font-size:104px;margin-top:34px">{SLOGAN[0]}<br><em>{SLOGAN[1]}</em></div>
 <div class="chips" style="margin-top:28px;font-size:24px"><span style="padding:8px 20px">Voiture</span><span style="padding:8px 20px">Canapés</span><span style="padding:8px 20px">Matelas</span><span style="padding:8px 20px">Tapis</span><span style="padding:8px 20px">Bureaux</span></div></div>
<div class="sofa" style="left:760px;top:150px;width:360px;height:312px">{armchair(True)}</div>
<div class="cta" style="position:absolute;left:80px;bottom:52px;font-size:30px;padding:12px 30px">WhatsApp · {phone}</div>
<div class="tag" style="right:80px;bottom:60px;font-size:26px;padding:8px 22px">Dakar et alentours</div>""")
# LinkedIn : page entreprise 1128x191 (logo de page en bas à gauche) et profil 1584x396 (photo de profil en bas à gauche) : texte décalé vers la droite
li_co = page(1128, 191, f"""
<div class="bubble" style="left:-30px;top:-60px;width:150px;height:150px"></div><div class="bubble" style="left:760px;top:120px;width:90px;height:90px"></div>
<div style="position:absolute;left:250px;top:30px"><div class="brand" style="font-size:20px;gap:8px"><i style="width:30px;height:30px"><svg viewBox="0 0 24 24" width="17" height="17" fill="#0B2545"><path d="M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z"/></svg></i>Clean Up</div>
 <div class="sl" style="font-size:38px;margin-top:12px;white-space:nowrap">{SLOGAN[0]} <em>{SLOGAN[1]}</em></div>
 <div style="margin-top:12px;font-size:13px;font-weight:700;color:#cfe2f5;white-space:nowrap">Voiture · Canapés · Matelas · Tapis · Bureaux &nbsp;|&nbsp; Dakar et alentours</div></div>
<div class="cta" style="position:absolute;left:740px;top:66px;font-size:15px;padding:9px 18px">WhatsApp · {phone}</div>
<div class="sofa" style="left:1010px;top:16px;width:104px;height:90px">{armchair(True)}</div>""")
li_pr = page(1584, 396, f"""
<div class="bubble" style="left:-60px;top:-90px;width:300px;height:300px"></div><div class="bubble" style="left:1000px;top:300px;width:150px;height:150px"></div><div class="bubble" style="left:1360px;top:-40px;width:150px;height:150px"></div>
<div style="position:absolute;left:520px;top:62px"><div class="brand" style="font-size:36px;gap:14px"><i style="width:60px;height:60px"><svg viewBox="0 0 24 24" width="33" height="33" fill="#0B2545"><path d="M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z"/></svg></i>Clean Up</div>
 <div class="sl" style="font-size:70px;margin-top:26px;white-space:nowrap">{SLOGAN[0]} <em>{SLOGAN[1]}</em></div>
 <div class="chips" style="margin-top:26px;font-size:19px"><span style="padding:6px 16px">Voiture</span><span style="padding:6px 16px">Canapés</span><span style="padding:6px 16px">Matelas</span><span style="padding:6px 16px">Tapis</span><span style="padding:6px 16px">Bureaux</span><span class="cta" style="padding:7px 18px;border:0;font-size:19px">WhatsApp · {phone}</span></div></div>
<div class="sofa" style="left:1380px;top:120px;width:170px;height:147px">{armchair(True)}</div>""")
(ROOT / 'couverture-linkedin-entreprise.html').write_text(li_co, encoding='utf-8'); (ROOT / 'couverture-linkedin-profil.html').write_text(li_pr, encoding='utf-8')
(ROOT / 'couverture-facebook.html').write_text(fb, encoding='utf-8'); (ROOT / 'image-partage.html').write_text(og, encoding='utf-8'); print('HTML OK')
