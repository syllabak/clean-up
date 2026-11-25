#!/usr/bin/env python3
"""Deux affiches « acquisition » 1080 x 1350 (4:5) : A) simple et épurée, B) avant / après d'un fauteuil.
Usage : python3 build-variantes.py [--maquette] && node render-social.cjs      (nécessite : pip install segno)

B utilise de VRAIES photos si photos/avant.(jpg|png|webp) et photos/apres.(jpg|png|webp) existent (vos propres chantiers, avec l'accord du client).
Sans photos, B affiche le fauteuil dessiné, étiqueté « Illustration » : on ne présente jamais un dessin ou la photo d'un autre comme un résultat de Clean Up.
--maquette : génère B avec des photos de référence pour juger la mise en page seulement (filigrane « MAQUETTE », jamais à publier).
"""
import io, json, pathlib, re, sys
import segno

ROOT = pathlib.Path(__file__).parent
C = json.loads((ROOT / "contact.json").read_text(encoding="utf-8"))
SITE = C.get("site_en_ligne") or C["site"]
URL = f"https://{SITE}/reserver"
TEL = C["telephone"]
OUT = ROOT / "html-social"
OUT.mkdir(exist_ok=True)

_src = (ROOT / "build.py").read_text(encoding="utf-8")   # on réutilise le fauteuil dessiné de l'affiche A3
exec(_src[_src.index("def armchair"):_src.index("def photo")])


def qr_svg(url):
    buf = io.BytesIO()
    segno.make(url, error="m").save(buf, kind="svg", scale=1, border=0, dark="#0B2545", light=None, xmldecl=False, svgns=True, nl=False)
    svg = buf.getvalue().decode()
    w, h = re.search(r'width="(\d+)"', svg).group(1), re.search(r'height="(\d+)"', svg).group(1)
    svg = re.sub(r'\s(width|height)="\d+"', '', svg, count=2)
    return svg.replace('<svg', f'<svg viewBox="0 0 {w} {h}" shape-rendering="crispEdges"', 1)


def real_photo(kind):
    for ext in ("jpg", "jpeg", "png", "webp"):
        if (ROOT / "photos" / f"{kind}.{ext}").exists():
            return f"../photos/{kind}.{ext}"
    return None


BASE = """*{box-sizing:border-box;margin:0;padding:0}
:root{--ink:#0B2545;--ink2:#13315C;--water:#1D7FD6;--foam:#E9F4FD;--foam2:#D3E9FA;--lemon:#FFD23F}
html,body{width:1080px;height:1350px}
body{font-family:Inter,'Segoe UI','Helvetica Neue',Arial,sans-serif;color:#fff;background:var(--ink);-webkit-print-color-adjust:exact}
.p{position:relative;width:1080px;height:1350px;overflow:hidden;display:flex;flex-direction:column;
 background:radial-gradient(90% 55% at 88% 0%,#2a74c9 0%,rgba(42,116,201,0) 62%),linear-gradient(180deg,var(--ink) 0%,var(--ink2) 100%)}
.b{position:absolute;border-radius:50%;border:3px solid rgba(255,255,255,.2);background:radial-gradient(circle at 30% 28%,rgba(255,255,255,.35),rgba(255,255,255,.04) 60%)}
.top{display:flex;align-items:center;gap:18px;padding:52px 64px 0;position:relative;z-index:2}
.mark{width:62px;height:62px;border-radius:50%;background:var(--lemon);display:grid;place-items:center}
.mark i{width:26px;height:26px;background:var(--ink);border-radius:50% 50% 50% 0;transform:rotate(-45deg)}
.top b{font-size:44px;font-weight:800;letter-spacing:-1px} .top span{margin-left:auto;font-size:20px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--foam2)}
.cta{position:relative;z-index:2;margin-top:auto;background:var(--lemon);color:var(--ink);padding:26px 64px 30px;display:grid;grid-template-columns:1fr 190px;gap:30px;align-items:center}
.cta h3{font-size:50px;font-weight:900;line-height:1;letter-spacing:-1.5px} .cta .sub{margin-top:10px;font-size:24px;font-weight:700}
.rows{margin-top:14px;display:grid;gap:6px;font-size:30px;font-weight:800} .rows small{font-size:18px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;opacity:.7;margin-right:10px}
.qr{background:#fff;border-radius:22px;padding:16px;text-align:center;font-size:17px;font-weight:800;letter-spacing:1px;text-transform:uppercase}
.qr svg{display:block;width:100%;height:auto;margin-bottom:8px}
"""

CTA = f"""<div class="cta"><div><h3>Réservez en 2 minutes</h3><p class="sub">Choisissez votre service, votre quartier, votre créneau.</p>
<div class="rows"><div><small>WhatsApp</small>{TEL}</div><div><small>Web</small>{SITE}</div></div></div>
<div class="qr">{qr_svg(URL)}Scannez pour réserver</div></div>"""
TOP = '<div class="top"><div class="mark"><i></i></div><b>Clean Up</b><span>Dakar · À domicile</span></div>'

# ---------------------------------------------------------------- A : simple
A = f"""<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Clean Up — simple</title><style>{BASE}
.hero{{position:relative;z-index:2;padding:0 64px;margin-top:auto;margin-bottom:auto}}
.kick{{display:inline-block;background:var(--lemon);color:var(--ink);font-weight:800;font-size:24px;letter-spacing:1.6px;text-transform:uppercase;padding:10px 22px;border-radius:40px}}
h1{{margin-top:30px;font-size:150px;line-height:.92;font-weight:900;letter-spacing:-6px}} h1 em{{font-style:normal;color:var(--lemon)}}
.lead{{margin-top:34px;font-size:38px;line-height:1.3;font-weight:500;color:var(--foam2);max-width:860px}}
.pills{{margin-top:38px;display:flex;flex-wrap:wrap;gap:14px}}
.pills span{{background:rgba(255,255,255,.12);border:2px solid rgba(255,255,255,.28);border-radius:40px;padding:12px 24px;font-size:28px;font-weight:700}}
.pills b{{color:var(--lemon)}}
</style></head><body><div class="p">
<div class="b" style="width:320px;height:320px;right:-90px;top:220px"></div><div class="b" style="width:120px;height:120px;right:230px;top:560px"></div><div class="b" style="width:200px;height:200px;left:-70px;top:760px"></div>
{TOP}
<div class="hero"><span class="kick">Nettoyage à domicile · Dakar</span>
<h1>Comme neuf.<br><em>Chez vous.</em></h1>
<p class="lead">Voiture, canapés, matelas, tapis. L'équipe vient à vous, le prix est annoncé avant de réserver.</p>
<div class="pills"><span>Canapés <b>dès 5 000 FCFA</b></span><span>Auto <b>dès 5 000 FCFA</b></span><span>Matelas <b>dès 15 000 FCFA</b></span></div></div>
{CTA}</div></body></html>"""

# ---------------------------------------------------------------- B : avant / après
maquette = "--maquette" in sys.argv
pa, pb = real_photo("avant"), real_photo("apres")
if maquette:
    pa, pb = "../maquette/avant.jpg", "../maquette/apres.jpg"
if pa and pb:
    fig = lambda cls, label, src: f'<figure class="{cls}"><img src="{src}" alt=""><figcaption>{label}</figcaption></figure>'
    ba = fig("before", "Avant", pa) + fig("after", "Après", pb)
    note = '<p class="note">MAQUETTE · photos de référence, ne pas publier</p>' if maquette else '<p class="note">Photos réelles d\'un nettoyage Clean Up</p>'
else:
    ba = (f'<figure class="before ill"><figcaption>Avant</figcaption>{armchair(True)}</figure><figure class="after ill"><figcaption>Après</figcaption>{armchair(False)}</figure>')
    note = '<p class="note">Illustration · remplacez par vos photos réelles dans photos/</p>'

B = f"""<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Clean Up — avant / après</title><style>{BASE}
.hero{{position:relative;z-index:2;padding:34px 64px 0}}
h1{{font-size:84px;line-height:.98;font-weight:900;letter-spacing:-3px}} h1 em{{font-style:normal;color:var(--lemon)}}
.lead{{margin-top:16px;font-size:30px;line-height:1.3;font-weight:500;color:var(--foam2)}}
.ba{{position:relative;z-index:2;margin:30px 64px 0;display:grid;grid-template-columns:1fr 1fr;gap:12px}}
.ba figure{{position:relative;border-radius:26px;overflow:hidden;height:520px;background:#cfd6dd;box-shadow:0 10px 0 rgba(0,0,0,.16)}}
.ba figure.ill.before{{background:linear-gradient(180deg,#c9cfd6,#aab2bb)}} .ba figure.ill.after{{background:linear-gradient(180deg,#fff,#d9ecfb)}}
.ba img{{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}}
.ba svg{{position:absolute;left:14px;right:14px;bottom:0;top:90px;width:calc(100% - 28px);height:calc(100% - 90px)}}
.ba figcaption{{position:absolute;left:0;bottom:0;z-index:3;padding:14px 34px;border-radius:0 22px 0 0;font-size:34px;font-weight:900;letter-spacing:2px;text-transform:uppercase}}
.ba .ill figcaption{{top:22px;bottom:auto;left:22px;border-radius:40px;padding:8px 24px;font-size:28px}}
.before figcaption{{background:#3a2d26;color:#fff}} .after figcaption{{background:var(--lemon);color:var(--ink)}}
.arrow{{position:absolute;z-index:4;left:50%;top:50%;transform:translate(-50%,-50%);width:92px;height:92px;border-radius:50%;background:var(--lemon);color:var(--ink);display:grid;place-items:center;font-size:54px;font-weight:900;box-shadow:0 6px 0 rgba(0,0,0,.2)}}
.note{{position:relative;z-index:2;margin:12px 64px 0;font-size:19px;font-weight:600;color:var(--foam2);opacity:.85;text-align:center}}
.offer{{position:relative;z-index:2;margin:18px 64px 0;background:#fff;color:var(--ink);border-radius:28px;padding:20px 28px;display:flex;align-items:center;gap:22px;box-shadow:0 10px 0 rgba(0,0,0,.14)}}
.offer b{{font-size:34px;font-weight:900;line-height:1.1}} .offer small{{display:block;font-size:22px;font-weight:600;color:#4d6178;margin-top:4px}}
.offer .px{{margin-left:auto;text-align:right;font-size:24px;font-weight:700;color:#3b4d63;line-height:1}} .offer .px strong{{display:block;font-size:58px;color:var(--water);font-weight:900;letter-spacing:-2px;margin-top:4px}}
{('.wm{position:absolute;z-index:9;inset:0;display:grid;place-items:center;font-size:150px;font-weight:900;color:rgba(255,255,255,.28);transform:rotate(-20deg);letter-spacing:10px;pointer-events:none}' if maquette else '')}
</style></head><body><div class="p">
{TOP}
<div class="hero"><h1>Votre fauteuil mérite<br>une <em>seconde vie.</em></h1></div>
<div class="ba">{ba}<div class="arrow">→</div></div>{note}
<div class="offer"><div><b>Nettoyage de canapés et fauteuils</b><small>Injection-extraction, détachage, séchage rapide · à domicile</small></div><div class="px">dès<strong>5 000</strong>FCFA</div></div>
{CTA}{'<div class="wm">MAQUETTE</div>' if maquette else ''}</div></body></html>"""

if maquette:
    (OUT / "99-maquette-avant-apres.html").write_text(B, encoding="utf-8")
else:
    (OUT / "02-simple.html").write_text(A, encoding="utf-8")
    (OUT / "03-avant-apres.html").write_text(B, encoding="utf-8")
print("ok", "maquette" if maquette else "02-simple + 03-avant-apres")
