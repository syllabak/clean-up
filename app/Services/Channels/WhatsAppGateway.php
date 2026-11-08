<?php
declare(strict_types=1);
namespace App\Services\Channels;

use App\Services\ChannelConfig;

/**
 * WhatsApp, deux modes :
 *  - « link » (sans API) : l'application prépare un lien wa.me avec le message ; l'envoi se fait à la main depuis WhatsApp.
 *  - « cloud » (avec API) : WhatsApp Business Cloud API de Meta. Écrire le premier à un client impose un modèle de message approuvé par Meta.
 */
final class WhatsAppGateway
{
    public static function mode(): string
    {
        $m = ChannelConfig::get('wa_mode', 'WHATSAPP_MODE', 'off');
        return in_array($m, ['link', 'cloud'], true) ? $m : 'off';
    }

    public static function isCloud(): bool { return self::mode() === 'cloud'; }

    /** Lien « cliquer pour écrire » avec le message prérempli. */
    public static function link(string $phone, string $text): string
    {
        return 'https://wa.me/' . preg_replace('/\D/', '', $phone) . '?text=' . rawurlencode($text);
    }

    /**
     * Envoi par l'API Cloud.
     * @param array<string,string> $vars variables utilisables dans les paramètres du modèle ({client}, {message}…)
     * @throws \RuntimeException
     */
    public static function send(string $to, string $text, array $vars = []): void
    {
        if (!self::isCloud()) throw new \RuntimeException('WhatsApp n\'est pas en mode API.');
        $id = ChannelConfig::get('wa_phone_id', 'WHATSAPP_PHONE_ID');
        $token = ChannelConfig::get('wa_token', 'WHATSAPP_TOKEN');
        if (!preg_match('/^\d{5,20}$/', $id) || $token === '') throw new \RuntimeException('WhatsApp API : identifiant du numéro ou jeton manquant.');
        $version = ChannelConfig::get('wa_version', '', 'v21.0');
        if (!preg_match('/^v\d+\.\d+$/', $version)) $version = 'v21.0';
        $to = preg_replace('/\D/', '', $to) ?? '';

        $payload = ['messaging_product' => 'whatsapp', 'to' => $to];
        $tpl = ChannelConfig::get('wa_template');
        if ($tpl !== '') {
            $params = [];
            foreach (array_filter(array_map('trim', explode(',', ChannelConfig::get('wa_template_params', '', '{client}')))) as $p) {
                $params[] = ['type' => 'text', 'text' => mb_substr(strtr($p, $vars + ['{message}' => $text]), 0, 1000)];
            }
            $payload += ['type' => 'template', 'template' => ['name' => $tpl, 'language' => ['code' => ChannelConfig::get('wa_template_lang', '', 'fr')]]];
            if ($params) $payload['template']['components'] = [['type' => 'body', 'parameters' => $params]];
        } else {
            $payload += ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $text]];
        }
        [$status, $body] = Http::postJson("https://graph.facebook.com/$version/$id/messages", json_encode($payload, JSON_UNESCAPED_UNICODE), ['Authorization: Bearer ' . $token]);
        if ($status < 200 || $status >= 300) throw new \RuntimeException('WhatsApp API : ' . Http::errorText($status, $body));
    }
}
