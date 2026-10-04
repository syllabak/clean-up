<?php
declare(strict_types=1);

// Serveur PHP intégré (dev) : sert les fichiers statiques existants (en sous-dossier, il faut les envoyer soi-même).
if (PHP_SAPI === 'cli-server') {
    $base = rtrim(getenv('APP_BASE_PATH') ?: '', '/');
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($base !== '' && str_starts_with($uri, $base . '/')) $uri = substr($uri, strlen($base));
    $f = __DIR__ . $uri;
    if (is_file($f) && !str_ends_with($f, '.php')) {
        if ($base === '') return false;
        $types = ['css' => 'text/css', 'js' => 'text/javascript', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'webmanifest' => 'application/manifest+json', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'];
        header('Content-Type: ' . ($types[pathinfo($f, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        readfile($f);
        return true;
    }
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\{Request, Session, ValidationException};

Session::start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://*.tile.openstreetmap.org; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
header('Permissions-Policy: geolocation=(self), camera=(self), microphone=()');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=31536000');

try {
    $router = require BASE_PATH . '/routes/web.php';
    $router->dispatch(Request::method(), Request::path());
} catch (ValidationException $e) {
    if (Request::isJson()) json_response(['error' => $e->getMessage(), 'errors' => $e->errors], 422);
    abort(422, $e->getMessage());
} catch (Throwable $e) {
    error_log(sprintf("[%s] %s in %s:%d\n%s", date('c'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));
    if (filter_var(\App\Core\Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        http_response_code(500); echo '<pre>' . e($e) . '</pre>'; exit;
    }
    abort(500, 'Une erreur est survenue. Notre équipe a été informée.');
}
