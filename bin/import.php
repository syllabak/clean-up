<?php
// Usage : php bin/import.php fichier.json [--replace]
// 1) Configurez .env sur la base cible, 2) php bin/migrate.php, 3) php bin/import.php export.json --replace
// --replace vide d'abord les tables concernées (y compris les données par défaut créées par les migrations).
require __DIR__ . '/../bootstrap.php';
use App\Core\Database as DB;

$file = $argv[1] ?? null;
if (!$file || !is_file($file)) { fwrite(STDERR, "Usage : php bin/import.php fichier.json [--replace]\n"); exit(1); }
$data = json_decode(file_get_contents($file), true);
if (!is_array($data) || ($data['format'] ?? 0) !== 1) { fwrite(STDERR, "Fichier d'export invalide.\n"); exit(1); }
$replace = in_array('--replace', $argv, true);
$pdo = DB::pdo();
$known = DB::tables();

DB::isSqlite() ? $pdo->exec('PRAGMA foreign_keys = OFF') : $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$total = 0;
try {
    foreach ($data['tables'] as $t => $rows) {
        if (!in_array($t, $known, true)) { echo "Ignorée (table absente de la cible) : $t\n"; continue; }
        $existing = (int) DB::val("SELECT COUNT(*) FROM $t");
        if ($existing > 0 && !$replace) { fwrite(STDERR, "La table $t n'est pas vide. Relancez avec --replace pour la remplacer.\n"); exit(1); }
    }
    $pdo->beginTransaction();
    foreach ($data['tables'] as $t => $rows) {
        if (!in_array($t, $known, true)) continue;
        if ($replace) DB::exec("DELETE FROM $t");
        $cols = $rows ? array_keys($rows[0]) : [];
        $tc = array_column(DB::isSqlite() ? DB::all("PRAGMA table_info($t)") : DB::all("SELECT column_name AS name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?", [$t]), 'name');
        foreach ($rows as $r) { DB::insert($t, array_intersect_key($r, array_flip($tc))); $total++; }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Import annulé : ' . $e->getMessage() . "\n"); exit(1);
} finally {
    DB::isSqlite() ? $pdo->exec('PRAGMA foreign_keys = ON') : $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}
echo "Import terminé : $total lignes.\n";
