<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Core\{Auth, Request};

final class AuthMiddleware
{
    public static function handle(): void
    {
        if (Auth::check()) return;
        if (Request::isJson()) json_response(['error' => 'Authentification requise'], 401);
        $_SESSION['intended'] = Request::method() === 'GET' ? Request::path() : null;
        redirect('/connexion');
    }
}
