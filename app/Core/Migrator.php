<?php
declare(strict_types=1);
namespace App\Core;

/**
 * Migrations versionnées. Chaque fichier database/migrations/NNN_nom.php retourne :
 *  - un tableau d'instructions SQL (jetons {{PK}} et {{ENGINE}} adaptés au moteur), ou
 *  - une fonction callable (aucun argument) pour des données initiales.
 */
final class Migrator
{
    public static function translate(string $sql): string
    {
        if (Database::isSqlite()) {
            return strtr($sql, ['{{PK}}' => 'INTEGER PRIMARY KEY AUTOINCREMENT', '{{ENGINE}}' => '']);
        }
        return strtr($sql, ['{{PK}}' => 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY', '{{ENGINE}}' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci']);
    }

    public static function pending(): array
    {
        self::ensureTable();
        $done = array_column(Database::all('SELECT name FROM migrations'), 'name');
        $files = glob(BASE_PATH . '/database/migrations/*.php') ?: [];
        sort($files);
        return array_values(array_filter($files, fn($f) => !in_array(basename($f, '.php'), $done, true)));
    }

    private static function ensureTable(): void
    {
        if (!Database::tableExists('migrations')) {
            Database::pdo()->exec(self::translate('CREATE TABLE migrations (id {{PK}}, name VARCHAR(190) NOT NULL, batch INT NOT NULL DEFAULT 1, run_at DATETIME NOT NULL) {{ENGINE}}'));
        }
    }

    /** Sauvegarde automatique du fichier SQLite avant migration. Pour MySQL : voir bin/export.php. */
    public static function backup(): ?string
    {
        if (!Database::isSqlite() || !is_file(Database::sqlitePath())) return null;
        $dest = BASE_PATH . '/storage/backups/' . date('Ymd_His') . '_' . basename(Database::sqlitePath());
        copy(Database::sqlitePath(), $dest);
        return $dest;
    }

    public static function run(?callable $log = null): int
    {
        $log ??= fn($m) => null;
        $pending = self::pending();
        if (!$pending) { $log('Rien à migrer.'); return 0; }
        if ($b = self::backup()) $log("Sauvegarde : $b");
        $batch = (int) Database::val('SELECT COALESCE(MAX(batch),0) FROM migrations') + 1;
        foreach ($pending as $file) {
            $name = basename($file, '.php');
            $def = require $file;
            if (is_callable($def)) {
                $def();
            } else {
                foreach ($def as $sql) Database::pdo()->exec(self::translate($sql));
            }
            Database::insert('migrations', ['name' => $name, 'batch' => $batch, 'run_at' => now()]);
            $log("OK  $name");
        }
        return count($pending);
    }
}
