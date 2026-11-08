<?php
// Usage : php bin/seed.php   (données de démonstration, jamais en production par défaut)
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../database/seeders/DemoSeeder.php';
$force = in_array('--force', $argv ?? [], true);
if ((!\App\Core\Database::isSqlite() || \App\Core\Env::get('APP_ENV', 'local') === 'production') && !$force) {
    fwrite(STDERR, "Refus : les données de démonstration (comptes aux mots de passe connus) ne se chargent ni en production ni sur MySQL sans --force.\n");
    exit(1);
}
\Database\Seeders\DemoSeeder::run();
echo "Données de démonstration chargées." . PHP_EOL;
