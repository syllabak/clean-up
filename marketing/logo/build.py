#!/usr/bin/env python3
"""Génère les fichiers du logo Clean Up (SVG avec texte en tracés, donc sans dépendance de police) puis les PNG.
Usage : python3 build.py && node render.cjs      (nécessite : pip install fonttools)"""
import pathlib
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen

OUT = pathlib.Path(__file__).parent
NAVY, LEMON, WHITE = '#0B2545', '#FFD23F', '#FFFFFF'
font = TTFont('/usr/share/fonts/opentype/inter/Inter-ExtraBold.otf')
gs, cmap, upm = font.getGlyphSet(), font.getBestCmap(), font['head'].unitsPerEm
adv = font['hmtx']

def text_path(txt, size, tracking=-0.025):
    """Texte converti en tracés SVG ; renvoie (d, largeur)."""
    k = size / upm; x = 0.0; pen = SVGPathPen(gs)
    for i, ch in enumerate(txt):
        g = cmap[ord(ch)]
        # kerning ignoré : la graisse et l'espacement serré suffisent pour un mot de deux syllabes
        gs[g].draw(TransformPen(pen, (k, 0, 0, -k, x, 0)))
        x += adv[g][0] * k + tracking * size
    return pen.getCommands(), x - tracking * size

DROP = 'M12 2.5c3.6 4.3 6 7.6 6 11a6 6 0 0 1-12 0c0-3.4 2.4-6.7 6-11z'

def mark(cx, cy, r, drop_fill=NAVY, disc=LEMON):
    s = r * 1.1 / 24 * 1.0   # la goutte occupe environ 55 % du disque
    s = (r * 1.15) / 24
    return (f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{disc}"/>'
            f'<path d="{DROP}" fill="{drop_fill}" transform="translate({cx - 12 * s:.2f} {cy - 12.2 * s:.2f}) scale({s:.4f})"/>')

def horizontal(text_fill, drop_fill=NAVY, bg=None, pad=40):
    d, w = text_path('Clean Up', 150)
    r = 75; gap = 40
    W = pad * 2 + 2 * r + gap + w; H = pad * 2 + 2 * r
    bgr = f'<rect width="{W:.0f}" height="{H}" fill="{bg}"/>' if bg else ''
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W:.0f} {H}" width="{W:.0f}" height="{H}" role="img" aria-label="Clean Up">{bgr}'
            f'{mark(pad + r, pad + r, r, drop_fill)}<path d="{d}" fill="{text_fill}" transform="translate({pad + 2 * r + gap} {pad + r + 150 * 0.364:.1f})"/></svg>')

def icon(bg=None, rr=0):
    S = 512; r = 168
    bgr = f'<rect width="{S}" height="{S}" rx="{rr}" fill="{bg}"/>' if bg else ''
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {S} {S}" width="{S}" height="{S}" role="img" aria-label="Clean Up">{bgr}{mark(S / 2, S / 2, r)}</svg>'

files = {
    'logo-clean-up.svg': horizontal(NAVY),                          # fond clair ou transparent
    'logo-clean-up-blanc.svg': horizontal(WHITE),                   # sur fond marine ou photo sombre
    'logo-clean-up-fond-marine.svg': horizontal(WHITE, bg=NAVY),    # prêt à poser
    'icone-clean-up.svg': icon(),                                   # goutte seule, transparent
    'icone-clean-up-carre.svg': icon(NAVY, 112),                    # photo de profil WhatsApp, réseaux, icône d'application
}
for n, s in files.items(): (OUT / n).write_text(s, encoding='utf-8')
print('SVG :', ', '.join(files))
