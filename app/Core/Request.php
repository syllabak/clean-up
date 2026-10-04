<?php
declare(strict_types=1);
namespace App\Core;

final class Request
{
    private static ?array $json = null;

    public static function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }

    public static function path(): string
    {
        $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $p = '/' . trim($p, '/');
        return $p;
    }

    public static function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

    public static function isJson(): bool
    {
        return str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_starts_with(self::path(), '/api/');
    }

    public static function json(): array
    {
        if (self::$json === null) {
            $raw = file_get_contents('php://input') ?: '';
            $d = json_decode($raw, true);
            self::$json = is_array($d) ? $d : [];
        }
        return self::$json;
    }

    /** Entrée fusionnée : JSON > POST > GET. */
    public static function all(): array
    {
        return array_merge($_GET, $_POST, str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ? self::json() : []);
    }

    public static function input(string $key, mixed $default = null): mixed { return self::all()[$key] ?? $default; }
    public static function str(string $key, string $default = ''): string { $v = self::input($key, $default); return is_scalar($v) ? trim((string) $v) : $default; }
    public static function int(string $key, int $default = 0): int { $v = self::input($key); return is_numeric($v) ? (int) $v : $default; }
    public static function setJson(array $d): void { self::$json = $d; }
}
