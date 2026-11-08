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
.strip { position: relative; z-index: 2; margin: 0 -20mm; background: var(--water); color: #fff; text-align: center; padding: 4.5mm; font-size: 4.6mm; font-weight: 700; letter-spacing: .3mm }
"""

DROP = '<svg viewBox="0 0 24 24" fill="#0B2545"><path d="M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z"/></svg>'


def card(title, text="", items=(), hot=False, tag=""):
    li = "".join(f"<li>{html.escape(i)}</li>" for i in items)
    return (f'<div class="card{" hot" if hot else ""}">' + (f'<span class="tag">{html.escape(tag)}</span>' if tag else "")
            + f"<h3>{html.escape(title)}</h3>" + (f"<p>{html.escape(text)}</p>" if text else "") + (f"<ul>{li}</ul>" if li else "") + "</div>")


def poster(name, kicker, title_html, lead, cards_html, cols, cta_title, bubbles):
    b = "".join(f'<div class="bubble" style="left:{x}mm;top:{y}mm;width:{d}mm;height:{d}mm"></div>' for x, y, d in bubbles)
    page = f"""<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Affiche {html.escape(name)}</title><style>{CSS}</style></head>
<body><div class="poster">{b}
<div class="brand"><div class="mark">{DROP}</div><b>{html.escape(C['nom'])}</b><span>{html.escape(C['zone'])}</span></div>
<div class="kicker">{html.escape(kicker)}</div>
<h1>{title_html}</h1>
<p class="lead">{html.escape(lead)}</p>
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
       2, "Un devis immédiat, sans appeler.", BUB)

poster("04-bureaux", "Entreprises et professionnels",
       "Des bureaux propres <em>chaque lundi.</em>",
       "Un passage régulier, sans que vous ayez à y penser. Votre équipe arrive dans un espace net et prêt à travailler.",
       card("Nettoyage de bureaux", items=["Passage régulier ou ponctuel", "Sols, sanitaires, cuisine", "Vitres sur demande"], hot=True)
       + card("Flotte de véhicules", items=["Lavage sur votre parking", "Plusieurs véhicules par passage", "Facturation simple"]),
       2, "Parlons de votre site.", BUB)

print("HTML générés dans", OUT)
