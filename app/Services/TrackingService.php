<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Database, Env, Request};
use App\Services\Channels\Http;

/**
 * Publicité en ligne : origine des visiteurs (UTM, clics Facebook et Google), balises Meta et Google, API Conversions de Meta.
 * Les balises ne se chargent qu'après accord du visiteur (bandeau). Le suivi de l'origine d'une réservation, lui, ne dépose aucun cookie.
 */
final class TrackingService
{
    private const KEYS = ['utm_source' => 80, 'utm_medium' => 80, 'utm_campaign' => 120, 'fbclid' => 255, 'gclid' => 255];

    /** Retient l'origine du visiteur (paramètres de l'adresse) pour la durée de sa session. */
    public static function capture(): void
    {
        if (Request::method() !== 'GET') return;
        $attr = [];
        foreach (self::KEYS as $k => $max) {
            $v = $_GET[$k] ?? null;
            if (!is_string($v) || $v === '') continue;
            $v = mb_substr(preg_replace('/[^\p{L}\p{N}_\-\.\s:+~=]/u', '', $v) ?? '', 0, $max);
            if ($v !== '') $attr[$k] = $v;
        }
        if ($attr) $_SESSION['attr'] = $attr;
    }

    public static function attribution(): array { return is_array($_SESSION['attr'] ?? null) ? $_SESSION['attr'] : []; }

    /** Colonnes à enregistrer avec une réservation ou un prospect. */
    public static function columns(array $attr): array
    {
        $out = [];
        foreach (array_keys(self::KEYS) as $k) $out[$k] = isset($attr[$k]) && $attr[$k] !== '' ? $attr[$k] : null;
        return $out;
    }

    /** Canal d'acquisition déduit de l'origine publicitaire ; null si le visiteur est arrivé sans repère. */
    public static function channelId(?array $attr = null): ?int
    {
        $attr ??= self::attribution();
        $src = mb_strtolower((string) ($attr['utm_source'] ?? ''));
        $medium = mb_strtolower((string) ($attr['utm_medium'] ?? ''));
        $code = match (true) {
            !empty($attr['gclid']) || in_array($src, ['google', 'googleads', 'google_ads', 'adwords'], true) => 'google',
            in_array($src, ['instagram', 'ig'], true) => 'instagram',
            !empty($attr['fbclid']) || in_array($src, ['facebook', 'fb', 'meta'], true) => 'facebook',
            $src === 'tiktok' => 'tiktok', $src === 'flyers' => 'flyers', $src === 'whatsapp' => 'whatsapp', $src === 'sms' => 'sms', $src === 'email' => 'email',
            in_array($medium, ['cpc', 'ppc', 'paid', 'paidsocial', 'display'], true) => 'ads',
            default => null,
        };
        return $code ? (new CrmService())->channelByCode($code) : null;
    }

    // ------------------------------------------------------------------ Configuration des balises
    public static function metaPixel(): ?string
    {
        $id = ChannelConfig::get('meta_pixel_id');
        return ChannelConfig::flag('meta_enabled') && preg_match('/^\d{5,20}$/', $id) ? $id : null;
    }

    /** @return array{ads:?string,ga4:?string,label_booking:?string,label_lead:?string} */
    public static function google(): array
    {
        $none = ['ads' => null, 'ga4' => null, 'label_booking' => null, 'label_lead' => null];
        if (!ChannelConfig::flag('google_enabled')) return $none;
        $ads = ChannelConfig::get('google_ads_id'); $ga4 = ChannelConfig::get('google_ga4_id');
        $label = fn(string $l) => preg_match('/^[A-Za-z0-9_\-]{4,60}$/', $l) ? $l : null;
        $ads = preg_match('/^AW-\d{5,15}$/', $ads) ? $ads : null;
        return ['ads' => $ads, 'ga4' => preg_match('/^G-[A-Z0-9]{4,15}$/', $ga4) ? $ga4 : null,
            'label_booking' => $ads ? $label(ChannelConfig::get('google_label_booking')) : null, 'label_lead' => $ads ? $label(ChannelConfig::get('google_label_lead')) : null];
    }

    public static function enabled(): bool { $g = self::google(); return self::metaPixel() !== null || $g['ads'] !== null || $g['ga4'] !== null; }

    /** Données lues par assets/js/marketing.js (balises du <head>). */
    public static function publicConfig(): array
    {
        $g = self::google();
        return ['meta' => self::metaPixel(), 'gads' => $g['ads'], 'ga4' => $g['ga4'], 'labelBooking' => $g['label_booking'], 'labelLead' => $g['label_lead'], 'currency' => 'XOF'];
    }

    /** Domaines à autoriser dans la politique de sécurité (CSP), seulement quand le suivi est actif. @return array<string,string[]> */
    public static function cspExtra(): array
    {
        $x = ['script-src' => [], 'connect-src' => [], 'img-src' => []];
        if (self::metaPixel()) {
            $x['script-src'][] = 'https://connect.facebook.net';
            array_push($x['connect-src'], 'https://www.facebook.com', 'https://connect.facebook.net');
            $x['img-src'][] = 'https://www.facebook.com';
        }
        $g = self::google();
        if ($g['ads'] || $g['ga4']) {
            $x['script-src'][] = 'https://www.googletagmanager.com';
            array_push($x['connect-src'], 'https://www.googletagmanager.com', 'https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://*.g.doubleclick.net', 'https://www.google.com');
            array_push($x['img-src'], 'https://www.googletagmanager.com', 'https://*.g.doubleclick.net', 'https://www.google.com', 'https://*.google-analytics.com');
        }
        return $x;
    }

    // ------------------------------------------------------------------ API Conversions (Meta), côté serveur
    /** Charge utile envoyée à Meta. Les coordonnées sont hachées (SHA-256) avant tout envoi. */
    public static function metaPayload(string $event, string $eventId, array $user, int $value, array $attr, string $sourceUrl, ?string $testCode = null): array
    {
        $h = fn(string $s) => hash('sha256', mb_strtolower(trim($s)));
        $u = [];
        if (!empty($user['phone'])) $u['ph'] = [$h(normalize_phone((string) $user['phone']))];
        if (!empty($user['email'])) $u['em'] = [$h((string) $user['email'])];
        if (!empty($user['first_name'])) $u['fn'] = [$h((string) $user['first_name'])];
        if (!empty($user['last_name'])) $u['ln'] = [$h((string) $user['last_name'])];
        if (!empty($user['ip'])) $u['client_ip_address'] = $user['ip'];
        if (!empty($user['ua'])) $u['client_user_agent'] = $user['ua'];
        if (!empty($attr['fbclid'])) $u['fbc'] = 'fb.1.' . time() . '000.' . $attr['fbclid'];
        $payload = ['data' => [['event_name' => $event, 'event_time' => time(), 'event_id' => $eventId, 'action_source' => 'website', 'event_source_url' => $sourceUrl,
            'user_data' => $u, 'custom_data' => ['value' => $value, 'currency' => 'XOF']]]];
        if ($testCode) $payload['test_event_code'] = $testCode;
        return $payload;
    }

    /**
     * Remonte un événement à Meta. Ne lève jamais d'exception : une panne publicitaire ne doit pas gêner une réservation.
     * N'envoie rien sans l'accord du visiteur (cookie du bandeau) ni sans jeton configuré.
     */
    public static function sendMeta(string $event, string $eventId, array $user, int $value = 0): void
    {
        $pixel = self::metaPixel(); $token = ChannelConfig::get('meta_capi_token');
        if (!$pixel || $token === '' || ($_COOKIE['mk_consent'] ?? '') !== '1') return;
        try {
            $version = 'v21.0';
            $payload = self::metaPayload($event, $eventId, $user + ['ip' => Request::ip(), 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''], $value, self::attribution(),
                rtrim((string) Env::get('APP_URL', ''), '/') . '/', ChannelConfig::get('meta_test_code') ?: null);
            [$status, $body] = Http::postJson("https://graph.facebook.com/$version/$pixel/events", json_encode($payload, JSON_UNESCAPED_UNICODE), ['Authorization: Bearer ' . $token], 4);
            $ok = $status >= 200 && $status < 300;
            self::log('meta', $event, $eventId, $ok, $ok ? null : Http::errorText($status, $body));
        } catch (\Throwable $e) {
            self::log('meta', $event, $eventId, false, $e->getMessage());
        }
    }

    private static function log(string $provider, string $event, string $ref, bool $ok, ?string $error): void
    {
        try {
            Database::insert('marketing_events', ['company_id' => Env::companyId(), 'provider' => $provider, 'event' => $event, 'ref' => mb_substr($ref, 0, 60),
                'status' => $ok ? 'ok' : 'failed', 'error' => $error ? mb_substr($error, 0, 500) : null, 'created_at' => now()]);
        } catch (\Throwable) {}
    }

    // ------------------------------------------------------------------ Liens suivis
    /** Adresse de la boutique ou d'une page, étiquetée pour qu'une réservation soit rattachée à la bonne publicité. */
    public static function link(string $path, string $source, string $medium, string $campaign): string
    {
        $q = http_build_query(array_filter(['utm_source' => $source, 'utm_medium' => $medium, 'utm_campaign' => $campaign !== '' ? slugify($campaign) : '']), '', '&', PHP_QUERY_RFC3986);
        return rtrim((string) Env::get('APP_URL', ''), '/') . '/' . ltrim($path, '/') . ($q !== '' ? '?' . $q : '');
    }
}
