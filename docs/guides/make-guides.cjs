// Assemble les deux guides (client, administrateur) à partir des captures de img/ et génère les PDF.
// Usage : node make-guides.cjs    (après capture.cjs)  ->  guide-client.pdf, guide-administrateur.pdf (+ .html)
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path');
const D = __dirname;

const css = `
@page{size:A4;margin:0}
*{box-sizing:border-box}
body{margin:0;font:11pt/1.45 Inter,"Helvetica Neue",Arial,sans-serif;color:#17233a}
.page{width:210mm;height:297mm;padding:16mm 16mm 14mm;position:relative;page-break-after:always;overflow:hidden}
.page:last-child{page-break-after:auto}
.cover{background:#0e2442;color:#fff;display:flex;flex-direction:column;justify-content:center;padding:24mm}
.cover .tag{display:inline-block;background:#ffcf3f;color:#0e2442;font-weight:800;padding:2mm 4mm;border-radius:3mm;font-size:11pt;align-self:flex-start}
.cover h1{font-size:34pt;line-height:1.1;margin:10mm 0 5mm}
.cover p{font-size:14pt;color:#c9d6ea;max-width:140mm}
.cover .meta{position:absolute;left:24mm;bottom:20mm;color:#8ea3c4;font-size:10pt}
h2{font-size:20pt;margin:0 0 2mm;color:#0e2442}
.lead{color:#51607a;margin:0 0 6mm}
.step{display:flex;gap:8mm;align-items:flex-start;margin-bottom:5mm}
.step .shot{flex:0 0 44mm}
.step .shot img{width:44mm;height:88mm;object-fit:cover;object-position:top;border-radius:6mm;border:1.4mm solid #17233a;display:block}
.step .txt{flex:1}
.step h3{font-size:14pt;margin:1mm 0 3mm;color:#0e2442}
.step h3 .n{display:inline-block;width:8mm;height:8mm;border-radius:50%;background:#ffcf3f;text-align:center;line-height:8mm;margin-right:2mm;font-size:11pt}
.leg{list-style:none;padding:0;margin:0 0 3mm}
.leg li{display:flex;gap:3mm;margin-bottom:2.2mm}
.leg b.c{flex:0 0 6mm;height:6mm;border-radius:50%;background:#e8453c;color:#fff;font-size:9pt;text-align:center;line-height:6mm;margin-top:.6mm}
.tip{background:#eef4ff;border-left:1.2mm solid #2f6fdc;padding:2.5mm 3.5mm;border-radius:1.5mm;font-size:10pt;margin-top:3mm}
.wide{margin-bottom:6mm}
.wide img{width:102mm;margin:0 auto;border:.3mm solid #cfd7e6;border-radius:2mm;display:block;box-shadow:0 1mm 4mm rgba(14,36,66,.15)}
.wide h3{font-size:14pt;margin:0 0 2.5mm;color:#0e2442}
.wide h3 .n{display:inline-block;width:8mm;height:8mm;border-radius:50%;background:#ffcf3f;text-align:center;line-height:8mm;margin-right:2mm;font-size:11pt}
.cols{display:flex;gap:6mm}.cols>div{flex:1}.cols .wide img{width:100%}
.foot{position:absolute;left:16mm;right:16mm;bottom:7mm;font-size:8.5pt;color:#8793aa;display:flex;justify-content:space-between;border-top:.2mm solid #dde3ee;padding-top:2mm}
.stat{display:inline-block;padding:.6mm 2.4mm;border-radius:2mm;font-weight:700;font-size:9.5pt;margin-right:1mm}
.s1{background:#ffd54a}.s2{background:#2f6fdc;color:#fff}.s3{background:#17233a;color:#fff}.s4{background:#2e9d5b;color:#fff}.s5{background:#d64545;color:#fff}
table.t{border-collapse:collapse;width:100%;font-size:10pt;margin:2mm 0 5mm}table.t td,table.t th{border:.2mm solid #cfd7e6;padding:2mm 3mm;text-align:left;vertical-align:top}table.t th{background:#eef2f8}
`;
const img = (n) => `img/${n}.jpg`;
const leg = (arr) => `<ul class="leg">${arr.map((t, i) => `<li><b class="c">${i + 1}</b><span>${t}</span></li>`).join('')}</ul>`;
let pageNo = 0;
const page = (title, body) => `<section class="page">${body}<div class="foot"><span>${title}</span><span>${++pageNo}</span></div></section>`;
const cover = (tag, title, sub) => `<section class="page cover"><span class="tag">${tag}</span><h1>${title}</h1><p>${sub}</p><div class="meta">Clean Up · Réservation de nettoyage à domicile · Dakar<br>Captures réalisées sur des données d'exemple (clients fictifs).</div></section>`;
const step = (n, t, shot, items, tip) => `<div class="step"><div class="shot"><img src="${img(shot)}"></div><div class="txt"><h3><span class="n">${n}</span>${t}</h3>${leg(items)}${tip ? `<div class="tip">${tip}</div>` : ''}</div></div>`;
const wide = (n, t, shot, items, tip) => `<div class="wide"><h3><span class="n">${n}</span>${t}</h3><img src="${img(shot)}">${leg(items).replace('<ul class="leg">', '<ul class="leg" style="margin-top:3mm">')}${tip ? `<div class="tip">${tip}</div>` : ''}</div>`;
const doc = (title, body) => `<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>${title}</title><style>${css}</style></head><body>${body}</body></html>`;

// ============================================================ GUIDE CLIENT
pageNo = 0;
const client = doc('Guide client — Clean Up',
  cover('GUIDE CLIENT', 'Réserver un nettoyage en 2 minutes', 'Depuis votre téléphone : choisissez le service, partagez votre position, ajoutez quelques photos, validez. Aucun compte à créer, aucun paiement en ligne.') +
  page('Guide client', `<h2>Avant de commencer</h2><p class="lead">Tout se fait en trois écrans. Prévoyez votre téléphone, un numéro joignable (WhatsApp de préférence) et quelques photos de ce qui doit être nettoyé.</p>
    <table class="t"><tr><th>Ce dont vous avez besoin</th><th>Pourquoi</th></tr>
    <tr><td>Votre téléphone avec la localisation activée</td><td>L'équipe trouve votre adresse sans appel de guidage. Vous pourrez corriger le repère sur la carte.</td></tr>
    <tr><td>Un numéro de téléphone</td><td>Nous confirmons votre créneau par appel ou WhatsApp.</td></tr>
    <tr><td>Des photos « avant »</td><td>Facultatif, mais l'équipe arrive avec le bon matériel et le prix est plus juste.</td></tr></table>
    ${step(1, 'Choisir le service', 'c02-service', ['Touchez le service voulu : canapé, matelas, tapis, ménage, vitres, voiture…', 'Le prix de départ s\'affiche sous chaque service.'], 'Pas de compte, pas de mot de passe : on vous demande seulement votre nom et votre numéro à la fin.')}
    `) +
  page('Guide client', `<h2>Votre besoin, puis où et quand</h2><p class="lead">Premier écran : le besoin. Deuxième écran : on vous localise, vous choisissez un créneau.</p>
    ${step(2, 'Préciser votre besoin', 'c03-besoin', ['Répondez aux 2 ou 3 questions (nombre de places, matière, surface…).', 'Le total estimé se met à jour en bas de l\'écran, en direct.'])}
    ${step(3, 'Partager votre position', 'c04-position', ['Le téléphone vous localise automatiquement (acceptez la demande de localisation).', 'Touchez la carte ou déplacez le repère pour corriger l\'endroit exact.', 'Le quartier est choisi pour vous ; changez-le si besoin.'], 'Pas de localisation ? Écrivez simplement votre adresse ou un repère (« près de la pharmacie… »).')}
    `) +
  page('Guide client', `<h2>Créneau, photos et validation</h2><p class="lead">Fin du deuxième écran, puis le dernier : coordonnées, photos, récapitulatif.</p>
    ${step(4, 'Choisir le jour et l\'heure', 'c05-creneau', ['Touchez un jour disponible.', 'Touchez l\'heure qui vous convient. Les créneaux jaunes regroupent nos passages dans votre quartier.'])}
    ${step(5, 'Vos coordonnées et vos photos', 'c06-photos', ['Votre nom.', 'Votre téléphone.', '« Prendre une photo » ouvre l\'appareil photo ; « Importer des photos » prend celles de la galerie.', 'Vos photos apparaissent en miniature ; la croix les retire.'], 'Jusqu\'à 8 photos. Elles sont réduites automatiquement pour passer même avec une connexion lente.')}
    `) +
  page('Guide client', `<h2>Après la réservation</h2><p class="lead">Votre commande est enregistrée. Gardez votre numéro de référence.</p>
    ${step(6, 'Vérifier puis confirmer', 'c07-recapitulatif', ['Relisez le récapitulatif : service, date, adresse, nombre de photos, total.', 'Touchez « Confirmer la réservation ».'], 'Aucun paiement en ligne : vous réglez directement à l\'équipe après l\'intervention.')}
    ${step(7, 'Confirmation', 'c08-confirmation', ['Votre numéro de référence (ex. CU-261108-XXXX). Notez-le ou faites une capture d\'écran.'], 'Nous vous appelons très vite pour confirmer le créneau, puis vous prévenons avant l\'arrivée de l\'équipe.')}
    `) +
  page('Guide client', `<h2>Suivre votre commande</h2><p class="lead">Photos supplémentaires et suivi.</p>
    ${step(8, 'Ajouter des photos plus tard', 'c09-photos-recues', ['Vos photos reçues sont listées ici ; vous pouvez en ajouter depuis cette page.', 'Votre lien de suivi personnel : conservez-le, ou envoyez-le sur WhatsApp en un geste.'])}
    ${step(9, 'Suivre votre réservation', 'c10-suivi', ['La barre d\'avancement : Reçue, Confirmée, En cours, Terminée.', 'Les photos envoyées restent visibles ici.'], 'Besoin de changer l\'horaire ? Écrivez-nous sur WhatsApp en rappelant votre référence.')}`));
fs.writeFileSync(path.join(D, 'guide-client.html'), client);

// ============================================================ GUIDE ADMIN
pageNo = 0;
const admin = doc('Guide administrateur — Clean Up',
  cover('GUIDE ADMINISTRATEUR', 'Recevoir et gérer une commande', 'Du tableau de bord à la fin de l\'intervention : voir la localisation, regarder les photos, changer le statut en un clic, contacter le client.') +
  page('Guide administrateur', `<h2>Les cinq statuts</h2><p class="lead">Une commande suit un chemin simple. Chaque changement se fait en un clic.</p>
    <table class="t"><tr><th>Statut</th><th>Signification</th></tr>
    <tr><td><span class="stat s1">Nouvelle</span></td><td>Le client vient de commander. Rien n'est encore confirmé.</td></tr>
    <tr><td><span class="stat s2">Confirmée</span></td><td>Vous avez validé le créneau (affecter une équipe confirme aussi la commande).</td></tr>
    <tr><td><span class="stat s3">En cours</span></td><td>L'équipe est sur place.</td></tr>
    <tr><td><span class="stat s4">Terminée</span></td><td>Intervention faite. Statut final.</td></tr>
    <tr><td><span class="stat s5">Annulée</span></td><td>Commande annulée (le client est prévenu). Statut final, avec demande de confirmation.</td></tr></table>
    ${wide(1, 'Se connecter et lire le tableau de bord', 'a02-tableau-de-bord', ['« À faire maintenant » : les nouvelles commandes à confirmer, les interventions du jour. Le statut se change directement depuis cette liste.', 'La recherche (référence, nom, téléphone) est toujours en haut.', 'Le menu : Réservations, Planning, Clients, Marketing, Configuration.'])}`) +
  page('Guide administrateur', `<h2>Recevoir une commande</h2><p class="lead">La liste réunit tout ce qu'il faut pour décider, sans ouvrir de fiche.</p>
    ${wide(2, 'La liste des commandes', 'a03-liste-commandes', ['Contact rapide : « Appeler » ou « WhatsApp » avec un message déjà rédigé.', '« Voir la position » ouvre la localisation du client.', 'Photos envoyées par le client : touchez une miniature pour les agrandir sans quitter la liste.', 'Le statut : touchez-le pour le changer.'])}
    ${wide(3, 'Changer le statut en un clic', 'a04-statut-un-clic', ['Le menu propose uniquement les statuts possibles à cet instant. Un clic suffit ; seule l\'annulation demande une confirmation.'], 'Le client reçoit automatiquement le message correspondant (si les canaux de messagerie sont configurés).')}`) +
  page('Guide administrateur', `<h2>La fiche d'une commande</h2><p class="lead">Un seul écran : statut, client, adresse, photos, équipe.</p>
    ${wide(4, 'Statut et coordonnées du client', 'a05-fiche-commande', ['Les cinq statuts en haut : le statut actuel est surligné, les autres sont cliquables.', 'Le bloc Client : numéro cliquable, boutons Appeler et WhatsApp.'])}
    ${wide(5, 'Consulter la localisation', 'a06-localisation', ['La carte montre l\'endroit indiqué par le client (position GPS ou repère déplacé).', '« Itinéraire » lance la navigation dans Google Maps vers le client.'], 'Le texte sous la carte précise si la position vient du GPS ou a été placée à la main.')}`) +
  page('Guide administrateur', `<h2>Photos et équipe</h2><p class="lead">Voir l'état des lieux avant d'envoyer quelqu'un.</p>
    ${wide(6, 'Visualiser les photos avant intervention', 'a07-photos', ['Toutes les photos de la commande sont sur la même page, avec la mention « Client · avant ».'])}
    <div class="cols"><div><div class="wide"><h3><span class="n">7</span>Agrandir</h3><img src="${img('a08-visionneuse')}"><p class="lead" style="margin-top:2mm">Touchez une photo : elle s'agrandit, les flèches passent à la suivante. Échap ou la croix pour fermer.</p></div></div>
    <div><div class="wide"><h3><span class="n">8</span>Affecter une équipe</h3><img src="${img('a10-affectation')}"><p class="lead" style="margin-top:2mm">Choisissez l'équipe puis « Affecter » : la commande passe en Confirmée.</p></div></div></div>`) +
  page('Guide administrateur', `<h2>Retrouver et configurer</h2><p class="lead">Quelques réglages à connaître.</p>
    ${wide(9, 'Rechercher une commande ou un client', 'a11-recherche', ['La recherche couvre la référence, le nom et le téléphone.', 'Les résultats sont groupés par type, avec le même menu de statut.'])}
    <div class="cols"><div><div class="wide"><h3><span class="n">10</span>Services et tarifs</h3><img src="${img('a15-services')}"><p class="lead" style="margin-top:2mm">Menu Configuration puis Services : prix, durée, formules et options de chaque service.</p></div></div>
    <div><div class="wide"><h3><span class="n">11</span>Quartiers de Dakar</h3><img src="${img('a14-quartier-coordonnees')}"><p class="lead" style="margin-top:2mm">Chaque quartier a des frais de déplacement et des coordonnées GPS ; corrigez-les selon la réalité du terrain.</p></div></div></div>`));
fs.writeFileSync(path.join(D, 'guide-administrateur.html'), admin);

(async () => {
  const b = await chromium.launch(); const p = await b.newPage();
  for (const f of ['guide-client', 'guide-administrateur']) {
    await p.goto('file://' + path.join(D, f + '.html')); await p.waitForTimeout(500);
    await p.pdf({ path: path.join(D, f + '.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true });
    console.log('✓', f + '.pdf');
  }
  await b.close();
})();
