<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Request;
use App\Services\{ChannelConfig, ConsentService};
use App\Services\Channels\{SmsGateway, WhatsAppGateway};

/**
 * Réponses entrantes des clients (WhatsApp Cloud API, SMS). Seule action possible : retirer le consentement quand le client écrit STOP.
 * Aucune session ici : chaque appel est authentifié par une signature (Meta) ou un jeton (SMS), faute de quoi il est refusé.
 */
final class WebhookController extends Controller
{
    /** Vérification de l'adresse par Meta lors de l'enregistrement du webhook. */
    public function whatsappVerify(): void
    {
        $token = ChannelConfig::get('wa_verify_token');
        $given = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
        $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');
        if ($token === '' || ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '') !== 'subscribe' || !hash_equals($token, $given) || !preg_match('/^\d{1,20}$/', $challenge)) abort(403, 'Vérification refusée.');
        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge;
        exit;
    }

    public function whatsappReceive(): void
    {
        $secret = ChannelConfig::get('wa_app_secret');
        $raw = (string) file_get_contents('php://input');
        $sig = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
        if ($secret === '' || !str_starts_with($sig, 'sha256=') || !hash_equals(hash_hmac('sha256', $raw, $secret), substr($sig, 7))) abort(403, 'Signature invalide.');
        $n = 0;
        foreach ((array) (json_decode($raw, true)['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['messages'] ?? []) as $msg) {
                    if (($msg['type'] ?? '') !== 'text' || !is_string($msg['from'] ?? null)) continue;
                    $n += $this->handle((string) $msg['from'], (string) ($msg['text']['body'] ?? ''), 'whatsapp');
                }
            }
        }
        json_response(['ok' => true, 'optouts' => $n]);
    }

    public function smsReceive(): void
    {
        $token = ChannelConfig::get('sms_inbound_token');
        $given = (string) ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '');
        if ($given === '' && preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) $given = trim($m[1]);
        if ($given === '') $given = (string) ($_GET['token'] ?? '');
        if ($token === '' || !hash_equals($token, $given)) abort(403, 'Jeton invalide.');
        $in = Request::isJson() ? Request::json() : $_POST;   // les fournisseurs nomment les champs différemment
        $from = (string) ($in['from'] ?? $in['msisdn'] ?? $in['sender'] ?? $in['phone'] ?? '');
        $text = (string) ($in['text'] ?? $in['message'] ?? $in['body'] ?? $in['content'] ?? '');
        json_response(['ok' => true, 'optouts' => $this->handle($from, $text, 'sms')]);
    }

    /** @return int clients désinscrits */
    private function handle(string $from, string $text, string $channel): int
    {
        if (!ConsentService::isStop($text)) return 0;
        $n = (new ConsentService())->optOutByPhone($from, $channel);
        if ($n > 0) {   // confirmation au client ; sans importance si elle échoue
            $msg = 'Vous êtes désinscrit des offres ' . setting('company_name', '') . '. Vous ne recevrez plus de messages promotionnels.';
            try { $channel === 'whatsapp' ? (WhatsAppGateway::isCloud() ? WhatsAppGateway::send($from, $msg) : null) : SmsGateway::send(normalize_phone($from), $msg); }
            catch (\Throwable $e) { error_log('Confirmation STOP : ' . $e->getMessage()); }
        }
        return $n;
    }
}
