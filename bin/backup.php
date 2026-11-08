<?php
// Usage : php bin/backup.php [nombre_à_conserver]   (SQLite ; défaut : 14 sauvegardes). À planifier chaque nuit avec cron.
require __DIR__ . '/../bootstrap.php';
use App\Core\{Database, Migrator};

if (!Database::isSqlite()) { fwrite(STDERR, "SQLite uniquement. Sur MySQL : mysqldump ou php bin/export.php.\n"); exit(1); }
$keep = max(1, (int) ($argv[1] ?? 14));
$file = Migrator::backup();
if (!$file) { fwrite(STDERR, "Aucune base à sauvegarder.\n"); exit(1); }
echo "Sauvegarde : $file" . PHP_EOL;
$all = glob(BASE_PATH . '/storage/backups/*_' . basename(Database::sqlitePath())) ?: [];
sort($all);
foreach (array_slice($all, 0, max(0, count($all) - $keep)) as $old) { @unlink($old); echo "Supprimée : $old" . PHP_EOL; }
