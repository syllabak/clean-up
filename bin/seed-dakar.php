<?php
// Usage : php bin/seed-dakar.php [--force]
// Charge le catalogue de départ pour Dakar : quartiers (avec coordonnées), services et tarifs, équipes et horaires.
// À lancer une fois, après « php bin/migrate.php », sur une base vide. Tout se modifie ensuite dans l'administration (Configuration).
require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../database/seeders/DakarCatalogSeeder.php';

use Database\Seeders\DakarCatalogSeeder;

if (DakarCatalogSeeder::alreadyLoaded() && !in_array('--force', $argv ?? [], true)) {
    fwrite(STDERR, "Refus : des services ou des quartiers existent déjà. Les ajouter créerait des doublons.\nRelancez avec --force si vous savez ce que vous faites.\n");
    exit(1);
}
$n = DakarCatalogSeeder::run();
echo "Catalogue de Dakar chargé : {$n['services']} services, {$n['neighborhoods']} quartiers, {$n['teams']} équipes." . PHP_EOL;
echo "Renommez les agents modèles (Configuration → Agents) et vérifiez les tarifs (Configuration → Services et tarifs)." . PHP_EOL;
