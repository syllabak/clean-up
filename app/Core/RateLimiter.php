<?php
declare(strict_types=1);
namespace App\Core;

/** Limitation de débit par clé, stockée en base (fonctionne sans Redis ni APCu). */
final class RateLimiter
{
    /** Retourne true si la requête est autorisée. */
    public static function hit(string $key, int $max, int $windowSeconds): bool
    {
        $key = substr(hash('sha256', $key), 0, 40);
        $now = time();
        return (bool) Database::transaction(function () use ($key, $max, $windowSeconds, $now) {
            $row = Database::one('SELECT hits, window_start FROM rate_limits WHERE rkey = ?', [$key]);
            if (!$row || $now - (int) $row['window_start'] >= $windowSeconds) {
                Database::exec('DELETE FROM rate_limits WHERE rkey = ?', [$key]);
                Database::insert('rate_limits', ['rkey' => $key, 'hits' => 1, 'window_start' => $now]);
                return true;
            }
            if ((int) $row['hits'] >= $max) return false;
            Database::exec('UPDATE rate_limits SET hits = hits + 1 WHERE rkey = ?', [$key]);
            return true;
        });
    }

    /** Lecture seule : la clé a-t-elle déjà atteint son plafond dans la fenêtre ? */
    public static function blocked(string $key, int $max, int $windowSeconds): bool
    {
        $row = Database::one('SELECT hits, window_start FROM rate_limits WHERE rkey = ?', [substr(hash('sha256', $key), 0, 40)]);
        return $row && time() - (int) $row['window_start'] < $windowSeconds && (int) $row['hits'] >= $max;
    }

    public static function clear(string $key): void
    {
        Database::exec('DELETE FROM rate_limits WHERE rkey = ?', [substr(hash('sha256', $key), 0, 40)]);
    }
}
