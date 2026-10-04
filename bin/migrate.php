<?php
// Usage : php bin/migrate.php [--seed]   (--seed ajoute les données de démonstration)
require __DIR__ . '/../bootstrap.php';
use App\Core\{Database, Migrator};

echo 'Moteur : ' . Database::driver() . (Database::isSqlite() ? ' (' . Database::sqlitePath() . ')' : '') . PHP_EOL;
$n = Migrator::run(fn($m) => print($m . PHP_EOL));
echo "$n migration(s) appliquée(s)." . PHP_EOL;
if (in_array('--seed', $argv, true)) require __DIR__ . '/seed.php';
