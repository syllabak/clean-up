# Clean Up — plateforme de réservation de services de nettoyage

Application PHP 8.2+ (MVC maison, PDO), Bootstrap 5 local, PWA. Une entreprise, avec `company_id` partout pour préparer un futur mode SaaS. Aucun paiement en ligne : le chiffre d'affaires se saisit à la main sur chaque réservation.

## Démarrage rapide (SQLite)

```bash
cp .env.example .env
php bin/migrate.php --seed          # crée la base de test + données de démonstration
php -S localhost:8000 -t public public/index.php
```

Comptes de démonstration (à ne jamais garder en production) :

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | admin@cleanup.test | Admin#2026 |
| Gestionnaire | gestion@cleanup.test | Gestion#2026 |
| Agent (Équipe Auto) | moussa@cleanup.test | Agent#2026 |
| Agent (Équipe Textile) | awa@cleanup.test | Agent#2026 |

Espace équipe : `/connexion` (les agents arrivent sur `/agent`, les autres sur `/admin`).

## Déploiement en production (SQLite par défaut, MySQL en option)

L'application tourne sur **SQLite**, y compris en production : un seul fichier, aucun serveur de base à installer, adapté à une entreprise. **MySQL / MariaDB est optionnel**, pour ceux qui le préfèrent.

1. Dans `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://votre-domaine`, SMTP, SMS, un `CRON_TOKEN` long et aléatoire. Laissez `DB_DRIVER=sqlite`.
2. Faites pointer le serveur web sur `public/` (sinon le `.htaccess` racine redirige vers `public/`). `storage/` (donc la base) et `.env` ne doivent jamais être exposés. Le dossier `storage/` doit être accessible en écriture par PHP.
3. `php bin/migrate.php` (sans `--seed`, qui est de toute façon refusé en production), puis `php bin/create-admin.php vous@domaine.sn "Votre nom"`.
4. Saisissez vos vraies données dans l'administration : services, zones, équipes, horaires.
5. Passez le site en HTTPS (les cookies de session deviennent `Secure`, HSTS est envoyé).

**Sauvegardes SQLite** : `php bin/backup.php [nombre]` fait une copie cohérente dans `storage/backups/` (14 conservées par défaut). À planifier chaque nuit (`0 2 * * * php /chemin/du/projet/bin/backup.php`) et à copier régulièrement hors du serveur. Une copie est aussi faite avant chaque migration.

**Passer à MySQL / MariaDB (optionnel)** : créez une base `utf8mb4` et un utilisateur dédié, puis `php bin/export.php fichier.json` (base SQLite), configurez `.env` (`DB_DRIVER=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), `php bin/migrate.php` et `php bin/import.php fichier.json --replace`. Sauvegardes : `mysqldump` et/ou `bin/export.php`.

## Tâches planifiées

Rappels clients et nouvelles tentatives d'envoi (toutes les 5 minutes) :

```
*/5 * * * * php /chemin/du/projet/bin/queue.php
```

Sans accès à cron : un service externe appelle `https://domaine/cron/run` avec l'en-tête `X-Cron-Token: VOTRE_CRON_TOKEN` (ou `Authorization: Bearer …`). Le paramètre `?token=` reste accepté mais apparaît dans les journaux du serveur.

**Derrière un proxy ou un CDN** : renseignez `TRUSTED_PROXIES` dans `.env` (IP ou plages CIDR). Sans cela, tous les visiteurs ont la même IP et la limitation de débit les bloque ensemble ; avec, `X-Forwarded-For` et `X-Forwarded-Proto` ne sont lus que s'ils viennent de ces proxys.

**Connexion** : après 10 échecs en 15 minutes, l'identifiant est bloqué quelle que soit l'IP. Les permissions d'un rôle sont relues à chaque requête.

## Tests

```bash
php tests/run.php     # cœur métier : prix, créneaux, doubles réservations, concurrence, notifications, sécurité
php tests/smoke.php   # parcours HTTP complet sur un serveur temporaire (CSRF, droits, réservation, agent, CRUD)
```

## Comment ça marche

- **Prix et durée** : calculés uniquement côté serveur (`PricingService`) à partir de formules, champs dynamiques (coefficients, suppléments), options, quantité ou surface, frais de déplacement du quartier.
- **Créneaux** (`AvailabilityService`) : une heure n'est proposée que si une équipe compétente dessert le quartier ce jour-là, travaille, n'est ni en congé ni fermée, n'a pas atteint sa capacité, et n'a aucune intervention qui chevauche (trajet compris). Les créneaux dans un quartier déjà visité le même jour sont marqués « regroupés ».
- **Pas de double réservation** : vérification et insertion dans une même transaction verrouillée (`BEGIN IMMEDIATE` sur SQLite, verrou de ligne `FOR UPDATE` sur MySQL).
- **Statuts** : en attente → confirmée → affectée → en route → en intervention → terminée (ou annulée), transitions contrôlées et historisées.
- **Affectation** : manuelle (équipe et/ou agent, forçage tracé). L'interface `AssignmentStrategy` et le paramètre `auto_assign` préparent l'affectation automatique, désactivée par défaut.
- **Notifications** : modèles modifiables, activation par événement et canal, journal, reprises plafonnées. Une panne email/SMS ne bloque jamais une réservation.
- **Administration** : un CRUD générique piloté par `config/crud.php` couvre ~25 ressources ; ajoutez une ressource en ajoutant un bloc de configuration.
- **PWA** : `sw.js` ne met en cache que les fichiers statiques publics, jamais `/admin`, `/agent`, `/api`, `/reservation`, `/suivi`, ni les photos.

## Services et tarifs (tout se gère dans l'administration)

- **Fiche service** (`/admin/services/{id}`, depuis Services → Fiche) : tout le tarif d'un service sur une page : formules, options, questions posées au client (listes avec supplément ou coefficient, quantité, surface), photos, durées.
- **Contrôle « prêt à être réservé »** : signale ce qui empêcherait de réserver (aucun prix, aucune équipe ni compétence, aucun quartier ou horaire, liste sans choix) et ce qui est conseillé (résumé, description, photo, formule mise en avant).
- **Simulateur de prix** : même calcul que le site public, utilisable sur un service encore inactif. **Duplication** d'un service (copie inactive, à relire avant d'activer).
- **Formules** : prix, durée ajoutée, liste de ce qui est inclus (une ligne par élément) et badge « La plus choisie ».
- **Parcours client** : page service avec formules comparées et bouton « Choisir cette formule » (formule présélectionnée dans la réservation), total toujours visible sur mobile, page de confirmation avec les prochaines étapes, ajout à l'agenda (.ics), suivi avec barre de progression, contact WhatsApp.
- Après mise à jour : `php bin/migrate.php` (migration 010).

## CRM, acquisition et gestion

- **Canaux d'acquisition** (`/admin/crud/acquisition_channels`) : site web, WhatsApp, Facebook, Instagram, parrainage, flyers… modifiables. Le client peut indiquer « Comment nous avez-vous connus ? » à la réservation ; sans réponse, la réservation est rattachée au canal « Site web ».
- **Prospects** (`/admin/crm`) : pipeline Nouveau → Contacté → Devis envoyé → Gagné / Perdu, relances datées, historique des échanges (appel, WhatsApp, email, visite). Un message du formulaire de contact crée automatiquement un prospect. Une réservation faite avec le même numéro le passe en « Gagné » et le relie au client.
- **Fiche client à 360°** (`/admin/clients/{id}`) : réservations, chiffre d'affaires cumulé, panier moyen, segment (Nouveau, Occasionnel, Régulier, VIP), canal, consentement aux offres, échanges (y compris ceux du temps où il était prospect).
- **Gestion et résultats** (`/admin/gestion`, administrateur) : chiffre d'affaires, dépenses par catégorie, bénéfice et marge, 6 derniers mois, acquisition par canal (prospects, conversion, nouveaux clients, coût par client, ROI), meilleurs clients, clients inactifs depuis 90 jours à relancer. Les dépenses se saisissent dans `/admin/crud/expenses` ; rattachez une dépense marketing à un canal pour calculer son coût par client et son ROI.
- **Droits** : `crm.manage` (administrateur et gestionnaire), `finance.manage` (administrateur seul).
- Après mise à jour d'une installation existante : `php bin/migrate.php` (migrations 008 et 009).

## Limites connues

- Le mode MySQL/MariaDB (optionnel), SMTP réel et API SMS réelle n'ont pas été testés (seuls SQLite et les pilotes `log` l'ont été). SQLite n'admet qu'un écrit à la fois : largement suffisant pour une entreprise, à reconsidérer si le volume de réservations simultanées devient très élevé.
- Une prestation par réservation (la table `booking_items` permet le multi-services, pas l'interface).
- Le planning n'a pas de glisser-déposer : on déplace depuis la fiche réservation, avec créneaux vérifiés.
- Les textes légaux fournis (CGV, confidentialité) sont indicatifs et à faire valider.
- Le mode SaaS n'est que préparé : `company_id` existe, mais les tables enfants (formules, options…) se filtrent via leur parent, et il n'y a pas de gestion multi-entreprises.
