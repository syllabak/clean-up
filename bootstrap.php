<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__);

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) return;
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

require BASE_PATH . '/app/Helpers/helpers.php';

use App\Core\Env;
Env::load(BASE_PATH . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Africa/Dakar'));

$debug = filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug && PHP_SAPI !== 'cli' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
error_reporting(E_ALL);
