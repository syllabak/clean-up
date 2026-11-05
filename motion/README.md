# Net'Express — vidéo promo (motion design)

Vidéo verticale **1080×1920, 30 fps, 16 s** (Reels / TikTok / WhatsApp Status), en français.
Rendu final : `motion/out/netexpress-promo.mp4` (H.264, ~1,7 Mo, sans piste audio).

## Concept
« Du sale au propre » : un balayage de raclette révèle le message, puis le service se présente
en trois univers et se réserve en trois étapes. Palette tirée de la PWA (marine `#0B2545`),
cyan `#2EC4F1` pour l'action, mint `#8EE3C8` pour la confirmation. Police : Inter.

## Beat sheet
| Temps | Action | Point focal | Son suggéré |
|---|---|---|---|
| 0–1,3 s | « Taches. Poussière. Pas le temps ? » entrent en cascade | texte | ambiance sourde |
| 1,35–2,1 s | Raclette diagonale → « Propre. Chez vous. » | bord du wipe | whoosh + squeak |
| 2,55–4,9 s | Cercle marine, logo, lettres en cascade, bulles | logo | pop |
| 4,6–9,7 s | 3 cartes services, icônes tracées | cartes | tick x3 |
| 9,15–13,8 s | Téléphone : service → quartier → créneau → confirmation | sélection | clic x3, carillon |
| 13,0–16 s | Cercle clair, « Prêt pour le propre ? », bouton (pulse 1x) | bouton | note finale |

## Structure
- `src/tokens.js` couleurs, FPS, durée, minutage (`T`)
- `src/easing.js` easing et helpers déterministes
- `src/scenes.js` une fonction `update(t)` pure par scène
- `src/main.js` aperçu en boucle ; `render.cjs` rendu image par image

## Commandes
Aperçu : ouvrir `motion/index.html` dans un navigateur (`?t=6.4` fige une image).

```bash
NODE_PATH=$(npm root -g) node motion/render.cjs --stills
```
Prérequis : Node 18+, `playwright` (Chromium) et `ffmpeg`. `--stills` exporte des images de contrôle dans `motion/out/stills/` (ignorées par git).

## QA
- [x] 480 images, 1080×1920, 30 fps, 16,0 s (ffprobe)
- [x] Contrôle visuel de 11 images clés
- [ ] Zones de sécurité Reels/TikTok : contenu clé entre y≈200 et y≈1500
- [ ] Piste audio : non incluse (suggestions ci-dessus)
- [ ] Les textes de démo (quartiers, créneaux) sont illustratifs
