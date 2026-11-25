#!/usr/bin/env python3
"""Affiche « acquisition de clients » au format réseaux sociaux (1080 x 1350, portrait 4:5) : Facebook, Instagram, WhatsApp, LinkedIn.
Usage : python3 build-social.py && node render-social.cjs      (nécessite : pip install segno)
Coordonnées dans contact.json (site : champ « site_en_ligne » s'il existe, sinon « site »). Tous les prix et remises viennent du catalogue de départ.
"""
import io, json, pathlib, re
import segno

ROOT = pathlib.Path(__file__).parent
C = json.loads((ROOT / "contact.json").read_text(encoding="utf-8"))
SITE = C.get("site_en_ligne") or C["site"]
URL = f"https://{SITE}/reserver"
TEL = C["telephone"]
WA = f"https://wa.me/{C['whatsapp']}"
OUT = ROOT / "html-social"
OUT.mkdir(exist_ok=True)


def qr_svg(url):
    buf = io.BytesIO()
    segno.make(url, error="m").save(buf, kind="svg", scale=1, border=0, dark="#0B2545", light=None, xmldecl=False, svgns=True, nl=False)
    svg = buf.getvalue().decode()
    w, h = re.search(r'width="(\d+)"', svg).group(1), re.search(r'height="(\d+)"', svg).group(1)
    svg = re.sub(r'\s(width|height)="\d+"', '', svg, count=2)
    return svg.replace('<svg', f'<svg viewBox="0 0 {w} {h}" shape-rendering="crispEdges"', 1)


def icon(body):
    return f'<svg viewBox="0 0 48 48" fill="none" stroke="#1D7FD6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">{body}</svg>'


ICONS = {
    "auto": icon('<path d="M6 30v-6l5-9h26l5 9v6"/><path d="M6 30h36v6H6z"/><circle cx="14" cy="36" r="3.5" fill="#fff"/><circle cx="34" cy="36" r="3.5" fill="#fff"/><path d="M11 24h26"/>'),
    "canape": icon('<path d="M8 24v-6a5 5 0 0 1 5-5h22a5 5 0 0 1 5 5v6"/><path d="M4 26a4 4 0 0 1 8 0v4h24v-4a4 4 0 0 1 8 0v10H4z"/><path d="M12 40v3M36 40v3"/>'),
    "matelas": icon('<rect x="5" y="20" width="38" height="14" rx="3"/><path d="M5 34v5M43 34v5"/><rect x="9" y="12" width="12" height="8" rx="3"/><path d="M25 27h14"/>'),
    "tapis": icon('<rect x="6" y="14" width="36" height="22" rx="3"/><path d="M6 20h36M6 30h36M12 14v-4M20 14v-4M28 14v-4M36 14v-4"/>'),
    "menage": icon('<path d="M30 6 22 24"/><path d="M14 26h20l4 14H10z"/><path d="M18 26v14M24 26v14M30 26v14"/>'),
    "vitres": icon('<rect x="8" y="6" width="32" height="36" rx="3"/><path d="M24 6v36M8 24h32"/><path d="M14 12l5 5M29 30l5 5" />'),
}

SERVICES = [
    ("auto", "Lavage auto", "5 000"),
    ("canape", "Canapés & fauteuils", "5 000"),
    ("matelas", "Matelas", "15 000"),
    ("tapis", "Tapis & moquettes", "2 000", "/m²"),
    ("menage", "Ménage à domicile", "8 000"),
    ("vitres", "Vitres", "1 500"),
]


def card(s):
    key, name, price = s[0], s[1], s[2]
    unit = s[3] if len(s) > 3 else ""
    return f'<div class="svc">{ICONS[key]}<b>{name}</b><span>dès <strong>{price}</strong> FCFA{unit}</span></div>'


HTML = f"""<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Clean Up — Comme neuf. Chez vous.</title><style>
*{{box-sizing:border-box;margin:0;padding:0}}
:root{{--ink:#0B2545;--ink2:#13315C;--water:#1D7FD6;--foam:#E9F4FD;--foam2:#D3E9FA;--lemon:#FFD23F}}
html,body{{width:1080px;height:1350px}}
body{{font-family:Inter,'Segoe UI','Helvetica Neue',Arial,sans-serif;color:#fff;background:var(--ink);-webkit-print-color-adjust:exact}}
.p{{position:relative;width:1080px;height:1350px;overflow:hidden;display:flex;flex-direction:column;
  background:radial-gradient(90% 55% at 88% 0%,#2a74c9 0%,rgba(42,116,201,0) 62%),radial-gradient(70% 40% at 0% 45%,rgba(29,127,214,.35) 0%,rgba(29,127,214,0) 70%),linear-gradient(180deg,var(--ink) 0%,var(--ink2) 100%)}}
.b{{position:absolute;border-radius:50%;border:3px solid rgba(255,255,255,.2);background:radial-gradient(circle at 30% 28%,rgba(255,255,255,.35),rgba(255,255,255,.04) 60%)}}
.top{{display:flex;align-items:center;gap:18px;padding:52px 64px 0;position:relative;z-index:2}}
.mark{{width:62px;height:62px;border-radius:50%;background:var(--lemon);display:grid;place-items:center}}
.mark i{{width:26px;height:26px;background:var(--ink);border-radius:50% 50% 50% 0;transform:rotate(-45deg)}}
.top b{{font-size:44px;font-weight:800;letter-spacing:-1px}}
.top span{{margin-left:auto;font-size:20px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--foam2)}}
.hero{{padding:34px 64px 0;position:relative;z-index:2}}
.kick{{display:inline-block;background:var(--lemon);color:var(--ink);font-weight:800;font-size:22px;letter-spacing:1.5px;text-transform:uppercase;padding:9px 20px;border-radius:40px}}
h1{{margin-top:20px;font-size:100px;line-height:.95;font-weight:900;letter-spacing:-4px}}
h1 em{{font-style:normal;color:var(--lemon)}}
.lead{{margin-top:18px;font-size:28px;line-height:1.3;font-weight:500;color:var(--foam2);max-width:900px}}
.panel{{position:relative;z-index:2;margin:26px 64px 0;background:#fff;color:var(--ink);border-radius:34px;padding:22px 26px 18px;box-shadow:0 10px 0 rgba(0,0,0,.14)}}
.panel h2{{font-size:25px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:#4d6178;text-align:center;margin-bottom:14px}}
.grid{{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}}
.svc{{background:var(--foam);border-radius:22px;padding:14px 10px 12px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:5px}}
.svc svg{{width:44px;height:44px}}
.svc b{{font-size:23px;line-height:1.15;font-weight:800}}
.svc span{{font-size:21px;font-weight:600;color:#3b4d63}} .svc strong{{font-size:27px;color:var(--water);font-weight:900}}
.trust{{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:14px}}
.trust div{{display:flex;gap:10px;align-items:flex-start;font-size:21px;font-weight:700;line-height:1.2}}
.trust i{{flex:none;width:30px;height:30px;border-radius:50%;background:var(--water);color:#fff;font-style:normal;display:grid;place-items:center;font-size:19px;font-weight:900;margin-top:1px}}
.cta{{position:relative;z-index:2;margin-top:auto;background:var(--lemon);color:var(--ink);padding:24px 64px 26px;display:grid;grid-template-columns:1fr 190px;gap:30px;align-items:center}}
.cta h3{{font-size:48px;font-weight:900;line-height:1;letter-spacing:-1.5px}}
.cta .sub{{margin-top:10px;font-size:24px;font-weight:700}}
.rows{{margin-top:12px;display:grid;gap:6px;font-size:30px;font-weight:800}}
.rows small{{font-size:18px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;opacity:.7;margin-right:10px}}
.qr{{background:#fff;border-radius:22px;padding:16px;text-align:center;font-size:17px;font-weight:800;letter-spacing:1px;text-transform:uppercase}}
.qr svg{{display:block;width:100%;height:auto;margin-bottom:8px}}
.abo{{margin-top:10px;text-align:center;font-size:22px;font-weight:700;color:var(--ink)}} .abo b{{background:var(--ink);color:var(--lemon);border-radius:40px;padding:5px 16px;margin-right:8px}}
</style></head><body><div class="p">
<div class="b" style="width:300px;height:300px;right:-70px;top:150px"></div><div class="b" style="width:130px;height:130px;right:140px;top:420px"></div>
<div class="b" style="width:200px;height:200px;left:-90px;top:640px"></div><div class="b" style="width:70px;height:70px;right:60px;top:560px"></div>
<div class="top"><div class="mark"><i></i></div><b>Clean Up</b><span>Dakar · À domicile</span></div>
<div class="hero"><span class="kick">Nettoyage à domicile</span>
<h1>Comme neuf.<br><em>Chez vous.</em></h1>
<p class="lead">Une équipe mobile nettoie votre voiture, vos canapés, vos matelas et vos tapis. Vous ne bougez pas.</p></div>
<div class="panel"><h2>Nos services · prix annoncés d'avance</h2>
<div class="grid">{''.join(card(s) for s in SERVICES)}</div>
<div class="trust"><div><i>✓</i>Réservation en 2 minutes</div><div><i>✓</i>Confirmation par email ou SMS</div><div><i>✓</i>Équipes équipées, matériel fourni</div></div>
<p class="abo"><b>Abonnement</b>semaine ou mois : jusqu'à 10 % de remise</p></div>
<div class="cta"><div><h3>Réservez en 2 minutes</h3><p class="sub">Choisissez votre service, votre quartier, votre créneau.</p>
<div class="rows"><div><small>WhatsApp</small>{TEL}</div><div><small>Web</small>{SITE}</div></div></div>
<div class="qr">{qr_svg(URL)}Scannez pour réserver</div></div>
</div></body></html>"""
(OUT / "01-acquisition.html").write_text(HTML, encoding="utf-8")
print("ok", OUT / "01-acquisition.html")
