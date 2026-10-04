<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Request, Session, View};

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'layouts/public'): void
    {
        $data += ['flash_ok' => Session::pull('ok'), 'flash_err' => Session::pull('err'), 'errors' => Session::pull('errors', [])];
        echo View::render($view, $data, $layout);
        unset($_SESSION['_old']);
    }

    protected function admin(string $view, array $data = []): void
    {
        $this->view($view, $data + ['noindex' => true], 'layouts/admin');
    }

    protected function back(string $fallback = '/'): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = parse_url($ref, PHP_URL_HOST);
        $self = $_SERVER['HTTP_HOST'] ?? '';
        $path = ($host && explode(':', $self)[0] === $host) ? (parse_url($ref, PHP_URL_PATH) . (parse_url($ref, PHP_URL_QUERY) ? '?' . parse_url($ref, PHP_URL_QUERY) : '')) : $fallback;
        $base = base_path();
        if ($path && $base !== '' && str_starts_with($path, $base . '/')) $path = substr($path, strlen($base));
        redirect($path ?: $fallback);
    }

    protected function ok(string $msg): void { Session::flash('ok', $msg); }
    protected function err(string $msg): void { Session::flash('err', $msg); }
    protected function withOld(): void { $_SESSION['_old'] = array_map(fn($v) => is_scalar($v) ? $v : '', Request::all()); }
}
