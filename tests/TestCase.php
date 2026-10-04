<?php
declare(strict_types=1);

/** Mini-framework de test : aucun outil externe requis (php tests/run.php). */
final class T
{
    public static int $pass = 0, $fail = 0;
    public static array $failures = [];
    private static string $current = '';

    public static function group(string $name): void { self::$current = $name; echo "\n▶ $name\n"; }

    public static function ok(bool $cond, string $label): void
    {
        if ($cond) { self::$pass++; echo "  ✓ $label\n"; return; }
        self::$fail++; self::$failures[] = self::$current . ' — ' . $label; echo "  ✗ $label\n";
    }

    public static function eq(mixed $actual, mixed $expected, string $label): void
    {
        self::ok($actual === $expected, $label . ($actual === $expected ? '' : '  [obtenu: ' . json_encode($actual, JSON_UNESCAPED_UNICODE) . ' / attendu: ' . json_encode($expected, JSON_UNESCAPED_UNICODE) . ']'));
    }

    public static function throws(callable $fn, string $class, string $label): ?\Throwable
    {
        try { $fn(); } catch (\Throwable $e) { self::ok($e instanceof $class, $label . ($e instanceof $class ? '' : ' [exception: ' . get_class($e) . ': ' . $e->getMessage() . ']')); return $e; }
        self::ok(false, $label . ' [aucune exception]');
        return null;
    }

    public static function summary(): int
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo self::$pass . ' réussi(s), ' . self::$fail . " échoué(s)\n";
        foreach (self::$failures as $f) echo "  ✗ $f\n";
        return self::$fail ? 1 : 0;
    }
}

function fresh_db(string $path): void
{
    \App\Core\Database::reset();
    foreach (['', '-wal', '-shm'] as $s) @unlink($path . $s);
    putenv('DB_DRIVER=sqlite'); putenv('DB_SQLITE_PATH=' . $path);
    $GLOBALS['__settings_dirty'] = true;
    \App\Core\Migrator::run();
    require_once BASE_PATH . '/database/seeders/DemoSeeder.php';
    \Database\Seeders\DemoSeeder::run();
    // Pas de limite horaire pour les tests ; le jeu de données reste celui de la démo.
    setting_set('min_lead_hours', '1');
}

/** Prochaine date (≥ $minDays jours) tombant un jour ISO donné (1=lundi). */
function next_dow(int $dow, int $minDays = 3): string
{
    for ($i = $minDays; $i < $minDays + 14; $i++) {
        $t = strtotime("+$i day");
        if ((int) date('N', $t) === $dow) return date('Y-m-d', $t);
    }
    throw new RuntimeException('introuvable');
}

function nb(string $name): int { return (int) \App\Core\Database::val('SELECT id FROM neighborhoods WHERE name = ?', [$name]); }
function svc(string $slug): int { return (int) \App\Core\Database::val('SELECT id FROM services WHERE slug = ?', [$slug]); }
function formula(int $sid, string $name): int { return (int) \App\Core\Database::val('SELECT id FROM service_prices WHERE service_id = ? AND name = ?', [$sid, $name]); }
function choice(int $sid, string $key, string $label): int
{
    return (int) \App\Core\Database::val('SELECT c.id FROM service_field_choices c JOIN service_fields f ON f.id = c.field_id WHERE f.service_id = ? AND f.field_key = ? AND c.label = ?', [$sid, $key, $label]);
}
function opt(int $sid, string $name): int { return (int) \App\Core\Database::val('SELECT id FROM service_options WHERE service_id = ? AND name = ?', [$sid, $name]); }

/** Réservation de lavage simple (citadine ×1) prête à l'emploi. */
function wash_input(string $date, string $start, string $hood = 'Unité 15', array $extra = []): array
{
    $s = svc('lavage-automobile');
    return array_merge([
        'service_id' => $s, 'formula_id' => formula($s, 'Simple'), 'fields' => ['vehicle' => choice($s, 'vehicle', 'Citadine'), 'count' => 1], 'options' => [],
        'neighborhood_id' => nb($hood), 'date' => $date, 'start' => $start,
        'first_name' => 'Awa', 'last_name' => 'Gueye', 'phone' => '77 123 45 67', 'email' => '', 'address_text' => 'Villa 12, rue 3', 'instructions' => 'Sonner deux fois',
    ], $extra);
}
