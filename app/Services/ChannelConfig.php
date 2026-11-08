<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Env;

/**
 * Configuration des canaux de communication et de publicité, modifiable dans l'administration (préfixe « mk_ » dans les paramètres).
 * Une valeur saisie dans l'administration l'emporte ; sinon on retombe sur le fichier .env (compatibilité avec les installations existantes).
 */
final class ChannelConfig
{
    public static function get(string $key, string $env = '', string $default = ''): string
    {
        $v = setting('mk_' . $key, '');
        if ($v !== null && $v !== '') return $v;
        return $env !== '' ? (string) Env::get($env, $default) : $default;
    }

    public static function flag(string $key): bool { return setting('mk_' . $key, '0') === '1'; }

    /** Définition des champs éditables, par canal. */
    public static function sections(): array { return require BASE_PATH . '/config/marketing.php'; }
}
