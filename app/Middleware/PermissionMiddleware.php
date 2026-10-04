<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Audit, Auth};

final class PermissionMiddleware
{
    public static function handle(string $perm): void
    {
        AuthMiddleware::handle();
        if (Auth::can($perm)) return;
        Audit::log('access_denied', 'permission', null, ['perm' => $perm]);
        abort(403, "Vous n'avez pas la permission d'accéder à cette page.");
    }
}
