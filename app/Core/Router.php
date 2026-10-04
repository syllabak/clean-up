<?php
declare(strict_types=1);
namespace App\Core;

use App\Middleware\{AuthMiddleware, PermissionMiddleware, ThrottleMiddleware};

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, array $handler, array $mw = []): void
    {
        $regex = preg_replace_callback('#\{(\w+)\}#', fn($m) => match ($m[1]) {
            'id' => '(\d+)', 'slug' => '([a-z0-9\-]+)', 'type' => '([a-z_]+)', default => '([^/]+)',
        }, $path);
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler, $mw];
    }

    public function get(string $p, array $h, array $mw = []): void { $this->add('GET', $p, $h, $mw); }
    public function post(string $p, array $h, array $mw = []): void { $this->add('POST', $p, $h, $mw); }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as [$m, $regex, $handler, $mw]) {
            if ($m !== $method || !preg_match($regex, $path, $match)) continue;
            array_shift($match);
            // CSRF systématique sur toute requête qui modifie l'état
            if ($method === 'POST' && !Csrf::valid()) abort(419, 'Jeton de sécurité invalide ou expiré. Rechargez la page et recommencez.');
            foreach ($mw as $spec) $this->middleware($spec);
            [$class, $action] = $handler;
            (new $class())->$action(...array_map(fn($v) => urldecode($v), $match));
            return;
        }
        abort(404);
    }

    private function middleware(string $spec): void
    {
        [$name, $arg] = array_pad(explode(':', $spec, 2), 2, '');
        match ($name) {
            'auth' => AuthMiddleware::handle(),
            'perm' => PermissionMiddleware::handle($arg),
            'throttle' => ThrottleMiddleware::handle($arg),
            default => throw new \RuntimeException("Middleware inconnu : $name"),
        };
    }
}
