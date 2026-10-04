<?php
// Usage : php bin/seed.php   (données de démonstration, SQLite de test uniquement par défaut)
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../database/seeders/DemoSeeder.php';
if (!\App\Core\Database::isSqlite() && !in_array('--force', $argv ?? [], true)) {
    fwrite(STDERR, "Refus : les données de démonstration ne se chargent pas sur MySQL sans --force.\n");
    exit(1);
}
\Database\Seeders\DemoSeeder::run();
echo "Données de démonstration chargées." . PHP_EOL;
