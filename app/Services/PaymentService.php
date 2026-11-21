<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Database, Env};
use App\Services\Channels\Http;

/**
 * Paiement en ligne (facultatif) : PayTech (paytech.sn) et PayDunya, par redirection vers leur page de paiement sécurisée.
 * Aucune donnée de carte ni de compte mobile ne passe par ce serveur. Le client peut toujours payer à l'équipe après l'intervention.
 * Une réservation n'est marquée « payée » que sur notification vérifiée (signature) ; PayDunya est en plus confirmé auprès de son API.
 */
final class PaymentService
{
    public const PROVIDERS = ['paytech' => 'PayTech', 'paydunya' => 'PayDunya'];

    /** Remplaçable dans les tests : fn(string $method, string $url, array $headers, ?string $body): array{0:int,1:string}. */
    public static $transport = null;

    // ------------------------------------------------------------------ réglages
    public static function mode(): string { return ChannelConfig::get('pay_mode', '', 'after') === 'optional' ? 'optional' : 'after'; }

    public static function configured(string $p): bool
    {
        return match ($p) {
            'paytech' => ChannelConfig::flag('paytech_enabled') && ChannelConfig::get('paytech_api_key') !== '' && ChannelConfig::get('paytech_api_secret') !== '',
            'paydunya' => ChannelConfig::flag('paydunya_enabled') && ChannelConfig::get('paydunya_master_key') !== '' && ChannelConfig::get('paydunya_private_key') !== '' && ChannelConfig::get('paydunya_token') !== '',
            default => false,
        };
    }

    /** Prestataires proposés au client (vide si le paiement en ligne n'est pas activé). @return array<string,string> clé => nom */
    public static function available(): array
    {
        if (self::mode() !== 'optional') return [];
        return array_filter(self::PROVIDERS, fn($n, $k) => self::configured($k), ARRAY_FILTER_USE_BOTH);
    }

    private static function paytechEnv(): string { return ChannelConfig::get('paytech_env', '', 'test') === 'prod' ? 'prod' : 'test'; }
    private static function paydunyaLive(): bool { return ChannelConfig::get('paydunya_mode', '', 'test') === 'live'; }

    /** Résumé lisible pour la page de réglages. @return array{email:string,sms:string,payment:string} */
    public function status(): array
    {
        $mail = ChannelConfig::get('email_driver', 'MAIL_DRIVER', 'log');
        $sms = ChannelConfig::get('sms_driver', 'SMS_DRIVER', 'log');
        $pay = [];
        foreach (self::PROVIDERS as $k => $name) if (self::configured($k)) $pay[] = $name . ' (' . ($k === 'paytech' ? (self::paytechEnv() === 'prod' ? 'production' : 'test') : (self::paydunyaLive() ? 'production' : 'test')) . ')';
        return [
            'email' => match ($mail) { 'smtp' => ChannelConfig::get('email_host', 'MAIL_HOST') !== '' ? 'SMTP configuré (' . ChannelConfig::get('email_host', 'MAIL_HOST') . ')' : 'SMTP choisi mais le serveur n\'est pas renseigné', 'mail' => 'fonction mail() de l\'hébergeur', default => "mode test : rien n'est réellement envoyé" },
            'sms' => match ($sms) { 'http' => ChannelConfig::get('sms_api_url', 'SMS_API_URL') !== '' ? 'API du fournisseur configurée' : "API choisie mais l'adresse n'est pas renseignée", default => "mode test : rien n'est réellement envoyé" },
            'payment' => self::mode() !== 'optional' ? "désactivé (le client règle l'équipe après l'intervention)" : ($pay ? implode(' et ', $pay) . ' proposé(s) au client' : "activé, mais aucun prestataire n'est complet (clés manquantes)"),
        ];
    }

    // ------------------------------------------------------------------ transport
    private static function send(string $method, string $url, array $headers, ?string $body = null): array
    {
        if (self::$transport) return (self::$transport)($method, $url, $headers, $body);
        return $method === 'GET' ? Http::get($url, $headers) : Http::postJson($url, (string) $body, $headers, 15);
    }

    /** L'adresse de paiement doit être en https sur le domaine du prestataire : on ne redirige jamais un client ailleurs. */
    private static function trusted(string $url, string $domain): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https' && (bool) preg_match('/(^|\.)' . preg_quote($domain, '/') . '$/', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    private static function description(array $b): string { return 'Réservation ' . $b['reference'] . ' — ' . $b['service_name']; }

    // ------------------------------------------------------------------ lancement d'un paiement
    /** Crée la demande chez le prestataire et renvoie l'adresse de sa page de paiement. @throws \RuntimeException message affichable au client */
    public function start(array $b, string $provider): string
    {
        if (!isset(self::available()[$provider])) throw new \RuntimeException("Ce moyen de paiement n'est pas disponible.");
        if (!empty($b['paid_at'])) throw new \RuntimeException('Cette réservation est déjà payée.');
        if (in_array($b['status'], ['cancelled'], true)) throw new \RuntimeException('Cette réservation est annulée.');
        $amount = (int) $b['total'];
        if ($amount < 100) throw new \RuntimeException('Montant trop faible pour un paiement en ligne.');
        $ref = $b['reference'] . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $env = $provider === 'paytech' ? self::paytechEnv() : (self::paydunyaLive() ? 'live' : 'test');
        $id = Database::insert('payments', ['company_id' => Env::companyId(), 'booking_id' => (int) $b['id'], 'provider' => $provider, 'amount' => $amount, 'status' => 'pending', 'ref' => $ref, 'env' => $env, 'created_at' => now()]);
        try {
            [$token, $url] = $provider === 'paytech' ? $this->paytechCreate($b, $ref, $amount, $env) : $this->paydunyaCreate($b, $ref, $amount);
        } catch (\Throwable $e) {
            Database::update('payments', $id, ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500), 'updated_at' => now()]);
            error_log("Paiement $provider : " . $e->getMessage());
            throw new \RuntimeException("Le paiement en ligne est momentanément indisponible. Vous pouvez régler l'équipe après l'intervention.");
        }
        Database::update('payments', $id, ['token' => $token, 'checkout_url' => $url, 'updated_at' => now()]);
        Audit::log('payment.start', 'booking', (int) $b['id'], ['provider' => $provider, 'amount' => $amount, 'ref' => $ref], null);
        return $url;
    }

    /** @return array{0:string,1:string} jeton, adresse de paiement */
    private function paytechCreate(array $b, string $ref, int $amount, string $env): array
    {
        $body = ['item_name' => mb_substr($b['service_name'], 0, 120), 'item_price' => $amount, 'currency' => 'XOF', 'ref_command' => $ref, 'command_name' => mb_substr(self::description($b), 0, 200), 'env' => $env,
            'success_url' => url('/paiement/retour/' . $b['reference']), 'cancel_url' => url('/paiement/annule/' . $b['reference']),
            'custom_field' => json_encode(['booking' => $b['reference'], 'payment' => $ref], JSON_UNESCAPED_UNICODE)];
        if (str_starts_with(url('/'), 'https://')) $body['ipn_url'] = url('/webhooks/paytech');   // PayTech n'accepte que des adresses HTTPS
        $methods = ChannelConfig::get('paytech_methods');
        if ($methods !== '') $body['target_payment'] = $methods;
        [$st, $res] = self::send('POST', 'https://paytech.sn/api/payment/request-payment', ['API_KEY: ' . ChannelConfig::get('paytech_api_key'), 'API_SECRET: ' . ChannelConfig::get('paytech_api_secret'), 'Content-Type: application/json', 'Accept: application/json'], json_encode($body, JSON_UNESCAPED_UNICODE));
        $j = json_decode($res, true);
        if (!is_array($j) || (int) ($j['success'] ?? 0) !== 1 || empty($j['redirect_url'] ?? $j['redirectUrl'] ?? '')) throw new \RuntimeException('PayTech a refusé la demande (' . Http::errorText($st, $res) . ')');
        $u = (string) ($j['redirect_url'] ?? $j['redirectUrl']);
        if (!self::trusted($u, 'paytech.sn')) throw new \RuntimeException('PayTech a renvoyé une adresse de paiement inattendue.');
        return [(string) ($j['token'] ?? ''), $u];
    }

    private function paydunyaHeaders(): array
    {
        return ['PAYDUNYA-MASTER-KEY: ' . ChannelConfig::get('paydunya_master_key'), 'PAYDUNYA-PRIVATE-KEY: ' . ChannelConfig::get('paydunya_private_key'), 'PAYDUNYA-TOKEN: ' . ChannelConfig::get('paydunya_token'), 'Content-Type: application/json'];
    }
    private static function paydunyaBase(): string { return self::paydunyaLive() ? 'https://app.paydunya.com/api/v1/' : 'https://app.paydunya.com/sandbox-api/v1/'; }

    private function paydunyaCreate(array $b, string $ref, int $amount): array
    {
        $phone = preg_replace('/^221/', '', normalize_phone((string) ($b['customer_phone'] ?? '')));
        $invoice = ['total_amount' => $amount, 'description' => mb_substr(self::description($b), 0, 200),
            'customer' => array_filter(['name' => trim($b['customer_first_name'] . ' ' . $b['customer_last_name']), 'email' => $b['customer_email'] ?? '', 'phone' => $phone])];
        $channels = array_values(array_filter(array_map('trim', explode(',', ChannelConfig::get('paydunya_channels')))));
        if ($channels) $invoice['channels'] = $channels;
        $body = ['invoice' => $invoice, 'store' => ['name' => (string) setting('company_name', 'Clean Up')], 'custom_data' => ['reference' => $b['reference'], 'payment' => $ref],
            'actions' => ['return_url' => url('/paiement/retour/' . $b['reference']), 'cancel_url' => url('/paiement/annule/' . $b['reference']), 'callback_url' => url('/webhooks/paydunya')]];
        [$st, $res] = self::send('POST', self::paydunyaBase() . 'checkout-invoice/create', $this->paydunyaHeaders(), json_encode($body, JSON_UNESCAPED_UNICODE));
        $j = json_decode($res, true);
        if (!is_array($j) || ($j['response_code'] ?? '') !== '00' || !is_string($j['response_text'] ?? null) || empty($j['token'])) throw new \RuntimeException('PayDunya a refusé la demande (' . (is_array($j) ? (string) ($j['response_text'] ?? $j['description'] ?? "HTTP $st") : Http::errorText($st, $res)) . ')');
        if (!self::trusted($j['response_text'], 'paydunya.com')) throw new \RuntimeException('PayDunya a renvoyé une adresse de paiement inattendue.');
        return [(string) $j['token'], $j['response_text']];
    }

    // ------------------------------------------------------------------ notifications des prestataires
    /** IPN PayTech. @return string issue : paid, cancelled, ignored. @throws \RuntimeException signature invalide */
    public function paytechIpn(array $in): string
    {
        $key = ChannelConfig::get('paytech_api_key'); $secret = ChannelConfig::get('paytech_api_secret');
        if ($key === '' || $secret === '') throw new \RuntimeException('PayTech non configuré.');
        $ok = false;
        if (!empty($in['hmac_compute'])) $ok = hash_equals(hash_hmac('sha256', ($in['item_price'] ?? '') . '|' . ($in['ref_command'] ?? '') . '|' . $key, $secret), (string) $in['hmac_compute']);
        elseif (!empty($in['api_key_sha256']) && !empty($in['api_secret_sha256'])) $ok = hash_equals(hash('sha256', $key), (string) $in['api_key_sha256']) && hash_equals(hash('sha256', $secret), (string) $in['api_secret_sha256']);
        if (!$ok) throw new \RuntimeException('Signature PayTech invalide.');
        $p = Database::one("SELECT * FROM payments WHERE ref = ? AND provider = 'paytech'", [(string) ($in['ref_command'] ?? '')]);
        if (!$p) return 'ignored';
        $event = (string) ($in['type_event'] ?? '');
        if ($event === 'sale_complete') {
            // En sandbox, PayTech débite un montant aléatoire de 100 à 150 FCFA : on ne compare le montant qu'en production.
            if (($p['env'] ?? '') === 'prod' && (int) ($in['item_price'] ?? -1) !== (int) $p['amount']) {
                Database::update('payments', (int) $p['id'], ['error' => 'Montant notifié différent du montant demandé : à vérifier à la main.', 'updated_at' => now()]);
                return 'ignored';
            }
            $this->markPaid($p, (string) ($in['payment_method'] ?? '') ?: null);
            return 'paid';
        }
        if ($event === 'sale_canceled') { $this->markFinal($p, 'cancelled', 'Paiement annulé chez PayTech'); return 'cancelled'; }
        return 'ignored';
    }

    /** Rappel PayDunya (POST « data[...] »). Le statut est reconfirmé auprès de l'API avant tout. @return string paid, cancelled, failed, pending, ignored @throws \RuntimeException signature invalide */
    public function paydunyaCallback(array $in): string
    {
        $d = is_array($in['data'] ?? null) ? $in['data'] : [];
        $master = ChannelConfig::get('paydunya_master_key');
        if ($master === '' || empty($d['hash']) || !hash_equals(hash('sha512', $master), (string) $d['hash'])) throw new \RuntimeException('Signature PayDunya invalide.');
        $token = (string) ($d['invoice']['token'] ?? '');
        $p = $token !== '' ? Database::one("SELECT * FROM payments WHERE token = ? AND provider = 'paydunya'", [$token]) : null;
        return $p ? $this->reconcile($p) : 'ignored';
    }

    /** Interroge PayDunya pour le statut réel d'un paiement et met à jour la réservation. */
    public function reconcile(array $p): string
    {
        if ($p['provider'] !== 'paydunya' || empty($p['token'])) return $p['status'];
        if ($p['status'] === 'paid') return 'paid';
        [$st, $res] = self::send('GET', self::paydunyaBase() . 'checkout-invoice/confirm/' . rawurlencode($p['token']), $this->paydunyaHeaders());
        $j = json_decode($res, true);
        if (!is_array($j) || ($j['response_code'] ?? '') !== '00') return 'pending';
        $master = ChannelConfig::get('paydunya_master_key');
        if (empty($j['hash']) || !hash_equals(hash('sha512', $master), (string) $j['hash'])) return 'pending';   // réponse non authentifiée : on ne crédite rien
        $status = (string) ($j['status'] ?? '');
        if ($status === 'completed') {
            if ((int) ($j['invoice']['total_amount'] ?? -1) !== (int) $p['amount']) {
                Database::update('payments', (int) $p['id'], ['error' => 'Montant confirmé différent du montant demandé : à vérifier à la main.', 'updated_at' => now()]);
                return 'pending';
            }
            $this->markPaid($p, null);
            return 'paid';
        }
        if (in_array($status, ['cancelled', 'failed'], true)) { $this->markFinal($p, $status === 'failed' ? 'failed' : 'cancelled', (string) ($j['fail_reason'] ?? '') ?: null); return $status; }
        return 'pending';
    }

    // ------------------------------------------------------------------ mise à jour de la réservation
    public function markPaid(array $p, ?string $method): void
    {
        $fresh = Database::one('SELECT * FROM payments WHERE id = ?', [(int) $p['id']]);
        if (!$fresh || $fresh['status'] === 'paid') return;   // idempotent : les prestataires renvoient souvent la même notification
        $label = (self::PROVIDERS[$p['provider']] ?? $p['provider']) . ($method ? ' · ' . $method : '');
        Database::transaction(function () use ($p, $method, $label) {
            Database::update('payments', (int) $p['id'], ['status' => 'paid', 'method' => $method, 'paid_at' => now(), 'error' => null, 'updated_at' => now()]);
            Database::update('bookings', (int) $p['booking_id'], ['paid_at' => now(), 'declared_amount' => (int) $p['amount'], 'payment_note' => mb_substr('Payé en ligne (' . $label . ')', 0, 255), 'updated_at' => now()]);
        });
        $b = Database::one('SELECT status FROM bookings WHERE id = ?', [(int) $p['booking_id']]);
        try { (new BookingService())->history((int) $p['booking_id'], $b['status'] ?? null, $b['status'] ?? 'pending', null, 'Paiement en ligne reçu : ' . money((int) $p['amount']) . " ($label)"); } catch (\Throwable $e) { error_log('Historique paiement: ' . $e->getMessage()); }
        Audit::log('payment.paid', 'booking', (int) $p['booking_id'], ['provider' => $p['provider'], 'amount' => (int) $p['amount']], null);
    }

    private function markFinal(array $p, string $status, ?string $why): void
    {
        if (in_array($p['status'], ['paid', $status], true)) return;
        Database::update('payments', (int) $p['id'], ['status' => $status, 'error' => $why ? mb_substr($why, 0, 500) : null, 'updated_at' => now()]);
    }

    /** Dernier paiement d'une réservation (le plus récent). */
    public function latest(int $bookingId): ?array { return Database::one('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [$bookingId]); }
    public function forBooking(int $bookingId): array { return Database::all('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC', [$bookingId]); }

    // ------------------------------------------------------------------ test de connexion (administration)
    /** Crée une demande d'essai de 100 FCFA, jamais réglée : sert à vérifier les clés. @return string message de réussite @throws \RuntimeException */
    public function testConnection(string $provider): string
    {
        $fake = ['reference' => 'TEST-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)), 'service_name' => 'Test de connexion', 'customer_first_name' => 'Test', 'customer_last_name' => 'Clean Up', 'customer_email' => '', 'customer_phone' => ''];
        if ($provider === 'paytech') {
            if (ChannelConfig::get('paytech_api_key') === '' || ChannelConfig::get('paytech_api_secret') === '') throw new \RuntimeException('Renseignez la clé API et la clé secrète, puis enregistrez.');
            [$token] = $this->paytechCreate($fake, $fake['reference'], 100, 'test');   // toujours en environnement de test : on ne crée jamais de vraie demande pour un essai
            return 'Connexion PayTech réussie : une demande d\'essai a été créée (jeton ' . $token . '). Vos clés sont valides.';
        }
        if (ChannelConfig::get('paydunya_master_key') === '' || ChannelConfig::get('paydunya_private_key') === '' || ChannelConfig::get('paydunya_token') === '') throw new \RuntimeException('Renseignez les trois clés PayDunya, puis enregistrez.');
        [$token] = $this->paydunyaCreate($fake, $fake['reference'], 100);
        return 'Connexion PayDunya réussie : une facture d\'essai a été créée (' . $token . '), mode ' . (self::paydunyaLive() ? 'production' : 'test') . '. Vos clés sont valides.';
    }
}
