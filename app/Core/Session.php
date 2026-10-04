<?php
declare(strict_types=1);
namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') return;
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name('lv_session');
        session_set_cookie_params(['lifetime' => 0, 'path' => base_path() ?: '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
        // expiration d'inactivité : 2 h
        if (isset($_SESSION['_last']) && time() - $_SESSION['_last'] > 7200) { $_SESSION = []; session_regenerate_id(true); }
        $_SESSION['_last'] = time();
    }

    public static function get(string $k, mixed $d = null): mixed { return $_SESSION[$k] ?? $d; }
    public static function set(string $k, mixed $v): void { $_SESSION[$k] = $v; }
    public static function forget(string $k): void { unset($_SESSION[$k]); }
    public static function flash(string $k, mixed $v): void { $_SESSION['_flash'][$k] = $v; }
    public static function pull(string $k, mixed $d = null): mixed
    {
        $v = $_SESSION['_flash'][$k] ?? $d;
        unset($_SESSION['_flash'][$k]);
        return $v;
    }
}
