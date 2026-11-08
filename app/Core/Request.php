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
        $base = base_path();
        if ($base !== '' && ($p === $base || str_starts_with($p, $base . '/'))) $p = '/' . ltrim(substr($p, strlen($base)), '/');
        return $p;
    }

    /** Adresses ou plages CIDR des proxys de confiance (TRUSTED_PROXIES, séparées par des virgules). Vide = aucun. */
    private static function trustedProxies(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) Env::get('TRUSTED_PROXIES', '')))));
    }

    public static function ipInList(string $ip, array $list): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false) return false;
        foreach ($list as $entry) {
            [$net, $bits] = array_pad(explode('/', $entry, 2), 2, null);
            $nb = @inet_pton($net);
            if ($nb === false || strlen($nb) !== strlen($bin)) continue;
            $bits = $bits === null ? strlen($bin) * 8 : max(0, min(strlen($bin) * 8, (int) $bits));
            $full = intdiv($bits, 8); $rest = $bits % 8;
            if (substr($bin, 0, $full) !== substr($nb, 0, $full)) continue;
            if ($rest === 0 || ((ord($bin[$full]) ^ ord($nb[$full])) >> (8 - $rest)) === 0) return true;
        }
        return false;
    }

    private static function viaTrustedProxy(): bool
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        return $remote !== '' && self::ipInList($remote, self::trustedProxies());
    }

    /** IP du visiteur. X-Forwarded-For n'est lu que si la requête vient d'un proxy de confiance (sinon il est falsifiable). */
    public static function ip(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!self::viaTrustedProxy()) return $remote;
        $trusted = self::trustedProxies();
        foreach (array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) && !self::ipInList($ip, $trusted)) return $ip;
        }
        return $remote;
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (self::viaTrustedProxy() && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

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
