<?php
// Usage : php bin/export.php [fichier.json]
// Exporte toutes les données (sauf migrations et compteurs) en JSON : sauvegarde indépendante du moteur,
// utilisable pour passer de SQLite (test) à MySQL/MariaDB (production) avec bin/import.php.
require __DIR__ . '/../bootstrap.php';
use App\Core\Database as DB;

$file = $argv[1] ?? BASE_PATH . '/storage/backups/export_' . date('Ymd_His') . '.json';
$skip = ['migrations', 'rate_limits'];
$out = ['format' => 1, 'exported_at' => date('c'), 'driver' => DB::driver(), 'migrations' => array_column(DB::all('SELECT name FROM migrations ORDER BY id'), 'name'), 'tables' => []];
$n = 0;
foreach (DB::tables() as $t) {
    if (in_array($t, $skip, true)) continue;
    $rows = DB::all("SELECT * FROM $t");
    $out['tables'][$t] = $rows; $n += count($rows);
}
if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
file_put_contents($file, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
chmod($file, 0600);
echo "Export : " . count($out['tables']) . " tables, $n lignes → $file\n";
