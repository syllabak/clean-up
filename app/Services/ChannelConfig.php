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

    /** Contrôle d'une valeur saisie dans les réglages. @return ?string message d'erreur, null si la valeur est acceptée */
    public static function invalid(string $key, string $type, string $v, ?array $options): ?string
    {
        if ($type === 'select' && !array_key_exists($v, $options ?? [])) return 'valeur non autorisée.';
        if ($v === '' || $type === 'bool' || $type === 'select' || $type === 'secret' || $type === 'textarea') return null;
        if ($type === 'number' && !preg_match('/^\d{1,5}$/', $v)) return 'nombre entier attendu.';
        $rules = ['meta_pixel_id' => ['/^\d{5,20}$/', 'chiffres uniquement.'], 'google_ads_id' => ['/^AW-\d{5,15}$/', 'format AW-123456789.'], 'google_ga4_id' => ['/^G-[A-Z0-9]{4,15}$/', 'format G-XXXXXXXXXX.'],
            'google_label_booking' => ['/^[A-Za-z0-9_\-]{4,60}$/', 'lettres, chiffres, - et _.'], 'google_label_lead' => ['/^[A-Za-z0-9_\-]{4,60}$/', 'lettres, chiffres, - et _.'],
            'wa_phone_id' => ['/^\d{5,20}$/', 'chiffres uniquement.'], 'wa_version' => ['/^v\d+\.\d+$/', 'format v21.0.'], 'wa_template' => ['/^[a-z0-9_]{1,512}$/', 'minuscules, chiffres et _ (nom donné par Meta).'],
            'wa_template_lang' => ['/^[a-z]{2}(_[A-Z]{2})?$/', 'ex. fr ou fr_FR.'], 'sms_api_url' => ['#^https?://[^\s]+$#i', 'adresse commençant par https://.'], 'email_port' => ['/^\d{2,5}$/', 'numéro de port.'],
            'meta_test_code' => ['/^[A-Za-z0-9]{1,30}$/', 'lettres et chiffres.'],
            'paytech_methods' => ['/^[\p{L}0-9 ,\-]{0,200}$/u', 'noms de méthodes séparés par des virgules (ex. Orange Money, Wave).'],
            'paydunya_channels' => ['/^[a-z0-9\-, ]{0,300}$/', 'identifiants séparés par des virgules (ex. wave-senegal, orange-money-senegal, card).']];
        if (isset($rules[$key]) && !preg_match($rules[$key][0], $v)) return $rules[$key][1];
        if ($key === 'email_from_address' && !filter_var($v, FILTER_VALIDATE_EMAIL)) return "email invalide.";
        return null;
    }

    /** Définition des champs éditables, par canal. */
    public static function sections(): array { return require BASE_PATH . '/config/marketing.php'; }

    /** Réglages de la messagerie (email, SMS) et du paiement (PayTech, PayDunya). */
    public static function integrations(): array { return require BASE_PATH . '/config/integrations.php'; }
}
