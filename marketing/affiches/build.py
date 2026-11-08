#!/usr/bin/env python3
"""Génère les affiches Clean Up (HTML A3 portrait) à partir de contact.json.
Usage : python3 build.py && node render.cjs      (nécessite : pip install segno)
Les coordonnées se modifient dans contact.json, puis on relance les deux commandes.
"""
import html, io, json, pathlib
import segno

ROOT = pathlib.Path(__file__).parent
C = json.loads((ROOT / "contact.json").read_text(encoding="utf-8"))
OUT = ROOT / "html"
OUT.mkdir(exist_ok=True)


def qr_svg(url: str) -> str:
    buf = io.BytesIO()
    segno.make(url, error="m").save(buf, kind="svg", scale=1, border=0, dark="#0B2545", light=None, xmldecl=False, svgns=True, nl=False)
    svg = buf.getvalue().decode()
    import re
    w, h = re.search(r'width="(\d+)"', svg).group(1), re.search(r'height="(\d+)"', svg).group(1)
    svg = re.sub(r'\s(width|height)="\d+"', '', svg, count=2)
    return svg.replace('<svg', f'<svg viewBox="0 0 {w} {h}" shape-rendering="crispEdges"', 1)


CSS = """
@page { size: 297mm 420mm; margin: 0 }
:root { --ink:#0B2545; --ink2:#13315C; --water:#1D7FD6; --foam:#E9F4FD; --foam2:#D3E9FA; --lemon:#FFD23F; }
* { box-sizing: border-box; margin: 0; padding: 0 }
html, body { width: 297mm; height: 420mm }
body { font-family: Inter, 'Helvetica Neue', Arial, sans-serif; color: #fff; background: var(--ink); -webkit-print-color-adjust: exact; print-color-adjust: exact }
.poster { position: relative; width: 297mm; height: 420mm; overflow: hidden; display: flex; flex-direction: column; padding: 20mm 20mm 0;
  background: radial-gradient(120% 70% at 85% 0%, #1d5aa8 0%, rgba(29,90,168,0) 60%), linear-gradient(180deg, var(--ink) 0%, var(--ink2) 100%) }
.bubble { position: absolute; border-radius: 50%; border: 1.2mm solid rgba(255,255,255,.14); background: rgba(255,255,255,.05) }
.brand { display: flex; align-items: center; gap: 5mm; position: relative; z-index: 2 }
.brand .mark { width: 16mm; height: 16mm; border-radius: 50%; background: var(--lemon); display: grid; place-items: center }
.brand .mark svg { width: 9mm; height: 9mm }
.brand b { font-size: 11mm; font-weight: 800; letter-spacing: -.3mm }
.brand span { margin-left: auto; font-size: 5mm; font-weight: 600; color: var(--foam2); letter-spacing: .6mm; text-transform: uppercase }
.kicker { margin-top: 18mm; font-size: 6mm; font-weight: 700; letter-spacing: 1.2mm; text-transform: uppercase; color: var(--lemon); position: relative; z-index: 2 }
h1 { margin-top: 6mm; font-size: 25mm; line-height: .98; font-weight: 900; letter-spacing: -1.2mm; position: relative; z-index: 2 }
h1 em { font-style: normal; color: var(--lemon) }
.lead { margin-top: 10mm; font-size: 8mm; line-height: 1.35; max-width: 215mm; color: var(--foam2); font-weight: 500; position: relative; z-index: 2 }
.cards { margin-top: 14mm; flex: 1; align-content: center; padding-bottom: 6mm; display: grid; gap: 5mm; position: relative; z-index: 2 }
.cards.c3 { grid-template-columns: repeat(3, 1fr) } .cards.c2 { grid-template-columns: repeat(2, 1fr) }
.card { background: #fff; color: var(--ink); border-radius: 6mm; padding: 7mm 7mm 8mm; box-shadow: 0 2mm 0 rgba(0,0,0,.18) }
.card.hot { background: var(--lemon) }
.card h3 { font-size: 8mm; font-weight: 800; letter-spacing: -.2mm; line-height: 1.1 }
.card p { margin-top: 3mm; font-size: 5.2mm; line-height: 1.35; color: #3b4d63; font-weight: 500 }
.card.hot p { color: #4a3b00 }
.card ul { margin-top: 4mm; list-style: none; font-size: 5.2mm; line-height: 1.55; font-weight: 600 }
.card li::before { content: '✓'; color: var(--water); font-weight: 900; margin-right: 2.5mm }
.card.hot li::before { color: var(--ink) }
.tag { display: inline-block; margin-bottom: 3mm; padding: 1mm 3mm; border-radius: 20mm; background: var(--ink); color: var(--lemon); font-size: 3.8mm; font-weight: 800; letter-spacing: .4mm; text-transform: uppercase }
.spacer { flex: 1 }
.cta { position: relative; z-index: 2; display: grid; grid-template-columns: 1fr 62mm; gap: 10mm; align-items: center; background: var(--foam); color: var(--ink); border-radius: 8mm 8mm 0 0; margin: 12mm -20mm 0; padding: 12mm 20mm 14mm }
.cta h2 { font-size: 13mm; font-weight: 900; line-height: 1.02; letter-spacing: -.5mm }
.cta .rows { margin-top: 6mm; display: grid; gap: 2.5mm; font-size: 6.4mm; font-weight: 700 }
.cta .rows small { display: block; font-size: 4.2mm; font-weight: 600; color: #4d6178; text-transform: uppercase; letter-spacing: .5mm }
.qr { background: #fff; border-radius: 5mm; padding: 5mm; text-align: center; font-size: 4mm; font-weight: 800; color: var(--ink); text-transform: uppercase; letter-spacing: .4mm }
.qr svg { display: block; width: 100%; height: auto; margin-bottom: 3mm }
.ba { position: relative; z-index: 2; margin-top: 12mm; display: grid; grid-template-columns: 1fr 1fr; gap: 6mm }
.ba figure { position: relative; border-radius: 6mm; overflow: hidden; height: 88mm; box-shadow: 0 2mm 0 rgba(0,0,0,.18) }
.ba figure.before { background: linear-gradient(180deg, #c9cfd6, #aab2bb) }
.ba figure.after { background: linear-gradient(180deg, #ffffff, #d9ecfb) }
.ba svg { position: absolute; inset: 6mm 4mm 0 4mm; width: calc(100% - 8mm); height: calc(100% - 6mm) }
.ba figcaption { position: absolute; left: 5mm; top: 5mm; padding: 1.4mm 4mm; border-radius: 20mm; font-size: 5mm; font-weight: 900; letter-spacing: .8mm; text-transform: uppercase }
.before figcaption { background: #5b4636; color: #fff } .after figcaption { background: var(--lemon); color: var(--ink) }
.ba .arrow { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 20mm; height: 20mm; border-radius: 50%; background: var(--lemon); color: var(--ink); display: grid; place-items: center; font-size: 12mm; font-weight: 900; border: 2mm solid var(--ink); z-index: 3 }
.dense h1 { font-size: 21mm; margin-top: 4mm } .dense .kicker { margin-top: 12mm } .dense .lead { margin-top: 6mm; font-size: 7mm }
.dense .ba { margin-top: 8mm } .dense .ba figure { height: 82mm } .dense .cards { margin-top: 6mm; padding-bottom: 0; gap: 4mm } .dense .card { padding: 5mm 6mm 6mm }
.dense .card h3 { font-size: 7mm } .dense .card p { margin-top: 2mm; font-size: 4.8mm } .dense .cta { margin-top: 6mm; padding-top: 9mm; padding-bottom: 10mm } .dense .cta h2 { font-size: 11.5mm }
.strip { position: relative; z-index: 2; margin: 0 -20mm; background: var(--water); color: #fff; text-align: center; padding: 4.5mm; font-size: 4.6mm; font-weight: 700; letter-spacing: .3mm }
"""

DROP = '<svg viewBox="0 0 24 24" fill="#0B2545"><path d="M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z"/></svg>'


def armchair(dirty: bool) -> str:
    """Fauteuil dessiné : taché et terne (avant) ou propre et éclatant (après)."""
    c, c2, c3 = ("#8b7b69", "#7d6e5d", "#85755f") if dirty else ("#2f7fd0", "#4592e0", "#3a89d8")
    parts = [
        f'<rect x="70" y="26" width="160" height="124" rx="32" fill="{c}"/>',
        f'<rect x="86" y="42" width="128" height="96" rx="22" fill="{c2}"/>',
        f'<rect x="30" y="102" width="62" height="116" rx="28" fill="{c}"/>',
        f'<rect x="208" y="102" width="62" height="116" rx="28" fill="{c}"/>',
        f'<rect x="72" y="140" width="156" height="62" rx="20" fill="{c3}"/>',
        f'<rect x="38" y="196" width="224" height="30" rx="13" fill="{c}"/>',
        '<rect x="54" y="224" width="14" height="30" rx="5" fill="#5a3d2b"/><rect x="232" y="224" width="14" height="30" rx="5" fill="#5a3d2b"/>',
        '<path d="M92 170h116" stroke="rgba(255,255,255,.28)" stroke-width="3" stroke-dasharray="7 7" stroke-linecap="round" fill="none"/>',
    ]
    if dirty:
        parts += [
            '<ellipse cx="118" cy="168" rx="26" ry="14" fill="#4a3a2c" opacity=".55"/>',
            '<ellipse cx="190" cy="82" rx="20" ry="26" fill="#3e3025" opacity=".5" transform="rotate(20 190 82)"/>',
            '<ellipse cx="58" cy="150" rx="14" ry="20" fill="#3e3025" opacity=".5"/>',
            '<ellipse cx="236" cy="170" rx="11" ry="16" fill="#4a3a2c" opacity=".5"/>',
            '<ellipse cx="130" cy="70" rx="12" ry="9" fill="#5b4a36" opacity=".45"/>',
        ] + [f'<circle cx="{x}" cy="{y}" r="2.4" fill="#4a3a2c" opacity=".5"/>' for x, y in
             [(100, 60), (160, 110), (215, 135), (88, 190), (178, 188), (140, 52), (70, 120), (250, 130)]]
        parts.append('<path d="M30 238c20-8 40-8 60 0M200 240c18-6 36-6 52 0" stroke="rgba(0,0,0,.18)" stroke-width="5" fill="none" stroke-linecap="round"/>')
    else:
        star = lambda x, y, r: f'<path d="M{x} {y-r}Q{x} {y} {x+r} {y}Q{x} {y} {x} {y+r}Q{x} {y} {x-r} {y}Q{x} {y} {x} {y-r}Z" fill="#FFD23F"/>'
        parts += [
            '<path d="M96 58c18-8 44-10 70-6" stroke="rgba(255,255,255,.55)" stroke-width="9" stroke-linecap="round" fill="none"/>',
            '<path d="M40 126c0-8 4-14 10-18" stroke="rgba(255,255,255,.45)" stroke-width="7" stroke-linecap="round" fill="none"/>',
            star(236, 44, 20), star(62, 70, 13), star(246, 196, 12), star(150, 8, 10),
        ]
    return f'<svg viewBox="0 0 300 260" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">{"".join(parts)}</svg>'


def before_after() -> str:
    return ('<div class="ba"><figure class="before"><figcaption>Avant</figcaption>' + armchair(True) + '</figure>'
            '<figure class="after"><figcaption>Après</figcaption>' + armchair(False) + '</figure><div class="arrow">→</div></div>')


def card(title, text="", items=(), hot=False, tag=""):
    li = "".join(f"<li>{html.escape(i)}</li>" for i in items)
    return (f'<div class="card{" hot" if hot else ""}">' + (f'<span class="tag">{html.escape(tag)}</span>' if tag else "")
            + f"<h3>{html.escape(title)}</h3>" + (f"<p>{html.escape(text)}</p>" if text else "") + (f"<ul>{li}</ul>" if li else "") + "</div>")


def poster(name, kicker, title_html, lead, cards_html, cols, cta_title, bubbles, visual=''):
    b = "".join(f'<div class="bubble" style="left:{x}mm;top:{y}mm;width:{d}mm;height:{d}mm"></div>' for x, y, d in bubbles)
    page = f"""<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Affiche {html.escape(name)}</title><style>{CSS}</style></head>
<body><div class="poster{" dense" if visual else ""}">{b}
<div class="brand"><div class="mark">{DROP}</div><b>{html.escape(C['nom'])}</b><span>{html.escape(C['zone'])}</span></div>
<div class="kicker">{html.escape(kicker)}</div>
<h1>{title_html}</h1>
<p class="lead">{html.escape(lead)}</p>
{visual}
<div class="cards c{cols}">{cards_html}</div>
<div class="cta"><div><h2>{html.escape(cta_title)}</h2>
<div class="rows"><div><small>Réservation en ligne</small>{html.escape(C['site'])}</div>
<div><small>Appel · WhatsApp</small>{html.escape(C['telephone'])}</div>
<div><small>Horaires</small>{html.escape(C['horaires'])}</div></div></div>
<div class="qr">{qr_svg(C['url_reservation'])}Scannez pour réserver</div></div>
<div class="strip">Paiement après l'intervention · Équipes équipées de leur matériel et de leur eau · Confirmation par SMS</div>
</div></body></html>"""
    (OUT / f"{name}.html").write_text(page, encoding="utf-8")


BUB = [(210, -30, 120), (-35, 150, 90), (230, 215, 60), (20, 300, 40)]

poster("01-general", "Nettoyage à domicile et au bureau",
       "Votre voiture et votre intérieur, <em>comme neufs.</em> Chez vous.",
       "Nos équipes se déplacent avec leur matériel. Vous choisissez le service et le créneau, on s'occupe du reste.",
       card("Lavage auto", "Du lavage extérieur au traitement complet, pendant que vous travaillez.", hot=True, tag="Le plus demandé")
       + card("Canapés, matelas, tapis", "Injection-extraction, détachage, séchage rapide.")
       + card("Maison et bureaux", "Ménage ponctuel ou régulier, vitres et repassage en option."),
       3, "Réservez en deux minutes.", BUB)

poster("02-auto", "Lavage automobile à domicile",
       "On lave votre voiture. <em>Vous, vous travaillez.</em>",
       "Citadine, berline, SUV ou minibus : l'équipe vient à votre porte ou sur le parking du bureau.",
       card("Simple", items=["Carrosserie et vitres", "Jantes", "Séchage"])
       + card("Complet", items=["Extérieur et séchage", "Aspiration intérieure", "Tableau de bord", "Vitres int. et ext."], hot=True, tag="La plus choisie")
       + card("Premium", items=["Tout le Complet", "Lustrage", "Protection de la carrosserie"]),
       3, "Choisissez votre formule en ligne.", BUB)

poster("03-maison-textile", "Canapés · Matelas · Tapis · Maison",
       "Ce qui se cache dans vos tissus <em>disparaît.</em>",
       "Poussière, taches, odeurs : un nettoyage en profondeur, chez vous, avec un séchage en quelques heures.",
       card("Canapés et fauteuils", "Injection-extraction et détachage.", hot=True)
       + card("Matelas", "Acariens, taches et odeurs en moins.")
       + card("Tapis et moquettes", "Tarif au mètre carré.")
       + card("Nettoyage de maison", "Ponctuel ou régulier, vitres en option."),
       2, "Un devis immédiat, sans appeler.", BUB, visual=before_after())

poster("04-bureaux", "Entreprises et professionnels",
       "Des bureaux propres <em>chaque lundi.</em>",
       "Un passage régulier, sans que vous ayez à y penser. Votre équipe arrive dans un espace net et prêt à travailler.",
       card("Nettoyage de bureaux", items=["Passage régulier ou ponctuel", "Sols, sanitaires, cuisine", "Vitres sur demande"], hot=True)
       + card("Flotte de véhicules", items=["Lavage sur votre parking", "Plusieurs véhicules par passage", "Facturation simple"]),
       2, "Parlons de votre site.", BUB)

print("HTML générés dans", OUT)
