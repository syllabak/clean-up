# Clean Up — motion design « le parcours client » (40 s, 1080×1920)

Vidéo verticale (Reels, TikTok, WhatsApp Status) qui raconte la réservation en trois écrans, avec **voix off française** et **musique de fond** qui baisse sous la voix.

## Déroulé (calé sur la voix)

| Temps | Scène | Voix off |
|---|---|---|
| 0,3 – 4,8 s | Canapé taché, voiture poussiéreuse, horloge | « Un canapé taché. Une voiture poussiéreuse. Et pas une minute à perdre ? » |
| 5,2 – 7,5 s | Cercle marine, logo, trois écrans | « Chez Clean Up, tout se réserve en trois écrans. » |
| 7,9 – 12,9 s | **Écran 1** : service, formule, prix en direct | « Premier écran : vous choisissez votre service. Le prix s'affiche tout de suite, sans devis. » |
| 12,9 – 18,0 s | **Écran 2** : position détectée (GPS), carte, quartier trouvé, créneaux | « Deuxième écran : votre quartier, puis l'heure qui vous arrange. Les créneaux libres apparaissent aussitôt. » |
| 18,3 – 22,5 s | **Écran 3** : nom, téléphone, photos avant, confirmation | « Troisième écran : votre nom, votre numéro. Un clic, et c'est confirmé. » |
| 22,8 – 27,4 s | SMS avec lien de suivi, page de suivi | « Vous recevez un lien de suivi par message. Un clic, et vous savez où en est votre réservation. » |
| 27,7 – 31,6 s | Camionnette, puis avant / après | « Notre équipe arrive chez vous, avec son matériel. Et le résultat, le voilà. » |
| 31,9 – 40 s | Appel à l'action | « Clean Up. Réservez dès maintenant. » |

Les écrans du téléphone reprennent le vrai parcours de l'application (position GPS et carte, photos avant intervention, récapitulatif, prix en direct, lien de suivi personnel). La voix off date de la version précédente : elle dit encore « votre quartier » et « votre nom, votre numéro » ; à régénérer pour citer la position et les photos (texte prévu dans `audio/align.py`).

## Fichiers

- `index.html`, `style.css`, `scenes.js` : l'animation. Chaque image est une fonction pure du temps (`window.__render(t)`), donc le rendu est reproductible. Aperçu : ouvrir `index.html` ; image fixe : `index.html?t=12.5`.
- `vo_timing.js` : début et fin de chaque phrase de la voix off, **généré** par `audio/align.py` à partir de `audio/voice.mp3`.
- `render.cjs` : Playwright + ffmpeg, vidéo muette. `mix.sh` : musique + voix + vidéo.
- `audio/voice.mp3` (voix) et la musique ne sont **pas** versionnées (voir `.gitignore`).

## Refaire la vidéo

```bash
cd motion/parcours
python3 audio/align.py                   # (dans audio/) recalcule vo_timing.json si la voix change
node render.cjs                          # -> out/parcours-muet.mp4   (≈ 5 min)
bash mix.sh /chemin/vers/musique.mp4     # -> out/cleanup-parcours-client.mp4
```
Images de contrôle : `node render.cjs --stills 7,12,19,28`. Prérequis : Node 18+, `playwright`, `ffmpeg`, Python 3.

## Changer le texte ou la voix

1. Modifier le texte dans `audio/align.py` (liste `TEXT`) et regénérer la voix.
2. Placer le nouveau `voice.mp3` dans `audio/`, relancer `python3 align.py` (depuis `audio/`) puis régénérer `vo_timing.js` (copie du JSON, voir l'en-tête du fichier).
3. Les scènes se recalent seules sur les nouvelles durées ; vérifier avec `--stills`.

## À vérifier avant diffusion

- **Coordonnées** : `cleanup.sn` et la référence `CU-261108-K7QD` sont illustratifs. Remplacer par votre vrai site.
- **Illustrations** : fauteuil, voiture et camionnette sont dessinés. Pour de vraies photos avant / après de vos chantiers, remplacer le bloc « avant / après » dans `scenes.js`.
- **Musique** : fournie par vous ; vérifiez que vous avez le droit de l'utiliser dans une publicité.
- Zones de sécurité Reels / TikTok : le contenu essentiel reste entre y ≈ 120 et y ≈ 1500 ; les sous-titres sont à y ≈ 1390.
