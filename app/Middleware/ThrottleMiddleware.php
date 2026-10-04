<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{RateLimiter, Request};

/** Spécification « nom,max,fenêtre_en_secondes » ex. : login,8,300 */
final class ThrottleMiddleware
{
    public static function handle(string $arg): void
    {
        [$name, $max, $win] = array_pad(explode(',', $arg), 3, '');
        if (!RateLimiter::hit($name . '|' . Request::ip(), (int) $max, (int) $win)) {
            abort(429, 'Trop de tentatives. Patientez quelques minutes avant de réessayer.');
        }
    }
}
