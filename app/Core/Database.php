<?php
declare(strict_types=1);
namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Accès aux données unique pour SQLite (dev/tests) et MySQL/MariaDB (production).
 * Le code métier n'utilise que cette classe : le moteur se choisit via DB_DRIVER.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;

    public static function driver(): string
    {
        $d = strtolower(Env::get('DB_DRIVER', 'sqlite'));
        return in_array($d, ['mysql', 'mariadb'], true) ? 'mysql' : 'sqlite';
    }

    public static function isSqlite(): bool { return self::driver() === 'sqlite'; }

    public static function sqlitePath(): string
    {
        $p = Env::get('DB_SQLITE_PATH', 'storage/database/lavage_test.db');
        return ($p[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $p)) ? $p : BASE_PATH . '/' . $p;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
        if (self::isSqlite()) {
            $path = self::sqlitePath();
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
            self::$pdo = new PDO('sqlite:' . $path, null, null, $opts);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA busy_timeout = 8000');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', 'localhost'), Env::get('DB_PORT', '3306'), Env::get('DB_DATABASE', ''));
            $opts[PDO::ATTR_EMULATE_PREPARES] = false;
            self::$pdo = new PDO($dsn, Env::get('DB_USERNAME', ''), Env::get('DB_PASSWORD', ''), $opts);
            self::$pdo->exec("SET time_zone = '+00:00'");
        }
        return self::$pdo;
    }

    public static function reset(): void { self::$pdo = null; self::$depth = 0; }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(array_values($params));
        return $st;
    }

    public static function all(string $sql, array $params = []): array { return self::query($sql, $params)->fetchAll(); }
    public static function one(string $sql, array $params = []): ?array { $r = self::query($sql, $params)->fetch(); return $r ?: null; }
    public static function val(string $sql, array $params = []): mixed { $r = self::query($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
    public static function exec(string $sql, array $params = []): int { return self::query($sql, $params)->rowCount(); }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::query($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, int $id, array $data, string $pk = 'id'): int
    {
        $set = implode(',', array_map(fn($c) => $c . ' = ?', array_keys($data)));
        return self::exec("UPDATE $table SET $set WHERE $pk = ?", [...array_values($data), $id]);
    }

    /**
     * Transaction. Sur SQLite : BEGIN IMMEDIATE (verrou d'écriture immédiat, sérialise les réservations).
     * Sur MySQL : transaction InnoDB ; les verrous de ligne se prennent avec lockRow().
     */
    public static function transaction(callable $fn): mixed
    {
        if (self::$depth > 0) { self::$depth++; try { return $fn(); } finally { self::$depth--; } }
        $pdo = self::pdo();
        self::isSqlite() ? $pdo->exec('BEGIN IMMEDIATE') : $pdo->beginTransaction();
        self::$depth = 1;
        try {
            $r = $fn();
            self::isSqlite() ? $pdo->exec('COMMIT') : $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            try { self::isSqlite() ? $pdo->exec('ROLLBACK') : $pdo->rollBack(); } catch (\Throwable) {}
            throw $e;
        } finally {
            self::$depth = 0;
        }
    }

    /** Verrou de ligne pessimiste (MySQL). Sur SQLite le verrou d'écriture global suffit. */
    public static function lockRow(string $table, int $id): void
    {
        if (!self::isSqlite()) self::query("SELECT id FROM $table WHERE id = ? FOR UPDATE", [$id]);
    }

    public static function tableExists(string $table): bool
    {
        if (self::isSqlite()) return (bool) self::val("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
        return (bool) self::val('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
    }

    public static function tables(): array
    {
        if (self::isSqlite()) return array_column(self::all("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name');
        return array_column(self::all('SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name'), 'name');
    }

    public static function hasColumn(string $table, string $col): bool
    {
        if (self::isSqlite()) {
            foreach (self::all("PRAGMA table_info($table)") as $c) if ($c['name'] === $col) return true;
            return false;
        }
        return (bool) self::val('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $col]);
    }
}
