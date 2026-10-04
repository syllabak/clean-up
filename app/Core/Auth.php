<?php
declare(strict_types=1);
namespace App\Core;

final class Auth
{
    public static function id(): ?int { return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null; }

    public static function user(): ?array
    {
        static $cache = [];
        $id = self::id();
        if (!$id) return null;
        return $cache[$id] ??= Database::one('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.active = 1 AND u.deleted_at IS NULL', [$id]);
    }

    public static function attempt(string $email, string $password): bool
    {
        $u = Database::one('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? AND u.active = 1 AND u.deleted_at IS NULL', [mb_strtolower(trim($email))]);
        // Comparaison faite même si l'utilisateur n'existe pas (limite l'énumération de comptes par le temps de réponse).
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        if (!password_verify($password, $hash) || !$u) return false;
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $perms = Database::all('SELECT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ?', [$u['role_id']]);
        $_SESSION['perms'] = array_column($perms, 'name');
        $_SESSION['role'] = $u['role_name'];
        $_SESSION['staff_id'] = $u['staff_id'] ? (int) $u['staff_id'] : null;
        Database::update('users', (int) $u['id'], ['last_login_at' => now()]);
        Audit::log('login', 'user', (int) $u['id'], [], (int) $u['id']);
        return true;
    }

    public static function logout(): void
    {
        Audit::log('logout', 'user', self::id());
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function check(): bool { return self::user() !== null; }
    public static function can(string $perm): bool { return self::check() && in_array($perm, $_SESSION['perms'] ?? [], true); }
    public static function staffId(): ?int { return $_SESSION['staff_id'] ?? null; }
    public static function isAgentOnly(): bool { return self::can('agent.missions') && !self::can('dashboard.view'); }
}
