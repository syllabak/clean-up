<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Database, Env, ValidationException};
use App\Services\Channels\{Mailer, SmsGateway, WhatsAppGateway};

/**
 * Campagnes vers les clients : email, SMS ou WhatsApp. Chaque campagne vise un groupe de clients (segment, canal d'acquisition, inactifs),
 * uniquement parmi ceux qui ont accepté de recevoir des offres. L'envoi se fait par lots (tâche planifiée), jamais d'un seul coup.
 */
final class MarketingService
{
    public const CHANNELS = ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'];
    public const AUDIENCES = [
        'consent' => 'Tous les clients qui acceptent nos offres', 'new' => 'Nouveaux clients (aucune intervention terminée)', 'occasional' => 'Clients occasionnels',
        'regular' => 'Clients réguliers', 'vip' => 'Clients VIP', 'inactive' => 'Clients inactifs depuis 90 jours', 'channel' => "Clients venus d'un canal précis",
    ];
    public const STATUSES = ['draft' => ['Brouillon', 'secondary'], 'queued' => ['Programmée', 'info'], 'sending' => ['En cours', 'primary'], 'done' => ['Terminée', 'success'], 'cancelled' => ['Annulée', 'dark']];
    private const MAX_ATTEMPTS = 3;

    public static function optout(): string { return ChannelConfig::get('optout_text', '', 'Répondez STOP pour ne plus recevoir nos offres.'); }

    /** Le canal est-il prêt à envoyer ? Renvoie le motif sinon. */
    public static function channelProblem(string $channel): ?string
    {
        if ($channel === 'whatsapp' && WhatsAppGateway::mode() === 'off') return "WhatsApp est désactivé : choisissez un mode dans Marketing → Canaux.";
        return null;
    }

    // ------------------------------------------------------------------ Public visé
    /** @return list<array{id:int,first_name:string,last_name:string,recipient:string}> */
    public function audience(string $channel, string $audience, ?int $acquisitionChannel = null): array
    {
        $rows = Database::all("SELECT c.id, c.first_name, c.last_name, c.phone, c.email, c.channel_id,
              SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) AS done, SUM(CASE WHEN b.status = 'completed' THEN b.total ELSE 0 END) AS revenue,
              MAX(CASE WHEN b.status = 'completed' THEN b.scheduled_date END) AS last_done
            FROM customers c LEFT JOIN bookings b ON b.customer_id = c.id
            WHERE c.company_id = ? AND c.deleted_at IS NULL AND c.marketing_consent = 1 GROUP BY c.id, c.first_name, c.last_name, c.phone, c.email, c.channel_id ORDER BY c.id", [Env::companyId()]);
        $limit = date('Y-m-d', strtotime('-90 days'));
        $out = []; $seen = [];
        foreach ($rows as $r) {
            $done = (int) $r['done'];
            $segment = CrmService::segment($done, (int) $r['revenue']);
            $ok = match ($audience) {
                'new' => $segment === 'Nouveau', 'occasional' => $segment === 'Occasionnel', 'regular' => $segment === 'Régulier', 'vip' => $segment === 'VIP',
                'inactive' => $done > 0 && $r['last_done'] !== null && $r['last_done'] < $limit,
                'channel' => $acquisitionChannel !== null && (int) $r['channel_id'] === $acquisitionChannel,
                default => true,
            };
            if (!$ok) continue;
            $to = $channel === 'email' ? (filter_var((string) $r['email'], FILTER_VALIDATE_EMAIL) ? mb_strtolower((string) $r['email']) : '') : normalize_phone((string) $r['phone']);
            if ($to === '' || isset($seen[$to])) continue;
            $seen[$to] = true;
            $out[] = ['id' => (int) $r['id'], 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'recipient' => $to];
        }
        return $out;
    }

    // ------------------------------------------------------------------ Campagnes
    public function create(array $in, ?int $userId): int
    {
        $e = [];
        $name = trim((string) ($in['name'] ?? '')); $channel = (string) ($in['channel'] ?? ''); $audience = (string) ($in['audience'] ?? 'consent');
        $subject = trim((string) ($in['subject'] ?? '')); $body = trim((string) ($in['body'] ?? '')); $ach = (int) ($in['audience_channel_id'] ?? 0);
        if ($name === '' || mb_strlen($name) > 150) $e['name'] = 'Donnez un nom à la campagne (150 caractères maximum).';
        if (!isset(self::CHANNELS[$channel])) $e['channel'] = 'Choisissez un canal.';
        if (!isset(self::AUDIENCES[$audience])) $e['audience'] = 'Choisissez le public visé.';
        if ($audience === 'channel' && !(new CrmService())->channelId($ach)) $e['audience_channel_id'] = "Choisissez le canal d'acquisition.";
        if ($channel === 'email' && $subject === '') $e['subject'] = "L'objet de l'email est obligatoire.";
        if ($body === '' || mb_strlen($body) > 1500) $e['body'] = 'Le message est obligatoire (1 500 caractères maximum).';
        $at = trim((string) ($in['scheduled_at'] ?? ''));
        $when = null;
        if ($at !== '') {
            $t = \DateTime::createFromFormat('Y-m-d\TH:i', $at, new \DateTimeZone(date_default_timezone_get()));
            if (!$t) $e['scheduled_at'] = 'Date ou heure invalide.';
            else $when = $t->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        if ($e) throw new ValidationException($e);
        $id = Database::insert('marketing_campaigns', ['company_id' => Env::companyId(), 'name' => $name, 'channel' => $channel, 'audience' => $audience,
            'audience_channel_id' => $audience === 'channel' ? $ach : null, 'subject' => $channel === 'email' ? mb_substr($subject, 0, 190) : null, 'body' => $body,
            'status' => 'draft', 'scheduled_at' => $when, 'created_by' => $userId, 'created_at' => now()]);
        Audit::log('marketing.campaign.create', 'campaign', $id, ['channel' => $channel], $userId);
        return $id;
    }

    public function find(int $id): ?array
    {
        return Database::one('SELECT * FROM marketing_campaigns WHERE id = ? AND company_id = ?', [$id, Env::companyId()]);
    }

    /** Prépare les messages (un par client) et programme l'envoi. Chaque texte est figé ici : l'historique reste exact. @return int destinataires */
    public function launch(int $id, ?int $userId): int
    {
        $c = $this->find($id) ?? throw new \RuntimeException('Campagne introuvable.');
        if ($c['status'] !== 'draft') throw new \RuntimeException('Cette campagne a déjà été lancée.');
        if ($problem = self::channelProblem($c['channel'])) throw new \RuntimeException($problem);
        $audience = $this->audience($c['channel'], $c['audience'], $c['audience_channel_id'] ? (int) $c['audience_channel_id'] : null);
        if (!$audience) throw new \RuntimeException("Aucun destinataire : aucun client ne correspond, avec le consentement et la coordonnée nécessaires.");
        $manual = $c['channel'] === 'whatsapp' && WhatsAppGateway::mode() === 'link';
        Database::transaction(function () use ($c, $audience, $manual) {
            foreach ($audience as $a) {
                Database::insert('marketing_messages', ['campaign_id' => (int) $c['id'], 'customer_id' => $a['id'], 'recipient' => $a['recipient'],
                    'content' => $this->render($c, $a), 'status' => $manual ? 'manual' : 'pending', 'created_at' => now()]);
            }
            Database::update('marketing_campaigns', (int) $c['id'], ['status' => $manual ? 'sending' : 'queued', 'started_at' => $manual ? now() : null]);
        });
        Audit::log('marketing.campaign.launch', 'campaign', $id, ['recipients' => count($audience)], $userId);
        return count($audience);
    }

    public function cancel(int $id, ?int $userId): void
    {
        $c = $this->find($id) ?? throw new \RuntimeException('Campagne introuvable.');
        if (!in_array($c['status'], ['queued', 'sending'], true)) throw new \RuntimeException("Seule une campagne programmée ou en cours peut être annulée.");
        Database::exec("UPDATE marketing_messages SET status = 'cancelled' WHERE campaign_id = ? AND status IN ('pending','manual')", [$id]);
        Database::update('marketing_campaigns', $id, ['status' => 'cancelled', 'finished_at' => now()]);
        Audit::log('marketing.campaign.cancel', 'campaign', $id, [], $userId);
    }

    /** Texte final pour un client : variables remplacées, lien suivi, mention de désinscription. */
    public function render(array $campaign, array $customer): string
    {
        $link = TrackingService::link('/reserver', $campaign['channel'], 'campaign', (string) $campaign['name']);
        $text = strtr((string) $campaign['body'], ['{client}' => $customer['first_name'], '{nom}' => $customer['last_name'], '{entreprise}' => (string) setting('company_name', ''),
            '{telephone}' => (string) setting('company_phone', ''), '{lien}' => $link]);
        $optout = self::optout();
        return $optout === '' ? $text : ($campaign['channel'] === 'email' ? $text . "\n\n--\n" . $optout : $text . "\n" . $optout);
    }

    // ------------------------------------------------------------------ Envoi par lots
    /** À appeler régulièrement (tâche planifiée). @return int messages envoyés */
    public function processQueue(?int $limit = null): int
    {
        $limit ??= max(1, min(500, (int) ChannelConfig::get('batch_size', '', '50')));
        $campaigns = Database::all("SELECT * FROM marketing_campaigns WHERE company_id = ? AND status IN ('queued','sending') AND (scheduled_at IS NULL OR scheduled_at <= ?) ORDER BY id", [Env::companyId(), now()]);
        $sent = 0;
        foreach ($campaigns as $c) {
            if ($limit <= 0) break;
            if ($c['status'] === 'queued') Database::update('marketing_campaigns', (int) $c['id'], ['status' => 'sending', 'started_at' => now()]);
            $n = $this->processCampaign($c, $limit);
            $sent += $n; $limit -= $n;
            $this->finishIfComplete((int) $c['id']);
        }
        return $sent;
    }

    /** Envoie un lot d'une campagne précise (bouton « Envoyer un lot maintenant »). */
    public function processOne(int $id, int $limit = 50): int
    {
        $c = $this->find($id) ?? throw new \RuntimeException('Campagne introuvable.');
        if (!in_array($c['status'], ['queued', 'sending'], true)) throw new \RuntimeException("Cette campagne n'est pas en cours d'envoi.");
        if ($c['status'] === 'queued') Database::update('marketing_campaigns', $id, ['status' => 'sending', 'started_at' => now()]);
        $n = $this->processCampaign($c, $limit);
        $this->finishIfComplete($id);
        return $n;
    }

    private function processCampaign(array $c, int $limit): int
    {
        $msgs = Database::all("SELECT m.*, cu.marketing_consent FROM marketing_messages m JOIN customers cu ON cu.id = m.customer_id
            WHERE m.campaign_id = ? AND m.status = 'pending' ORDER BY m.id LIMIT " . (int) $limit, [(int) $c['id']]);
        $sent = 0;
        foreach ($msgs as $m) {
            if ((int) $m['marketing_consent'] !== 1) { Database::update('marketing_messages', (int) $m['id'], ['status' => 'skipped', 'error' => 'Le client a retiré son consentement.']); continue; }
            $attempts = (int) $m['attempts'] + 1;
            try {
                $this->deliver($c, $m);
                Database::update('marketing_messages', (int) $m['id'], ['status' => 'sent', 'attempts' => $attempts, 'error' => null, 'sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Database::update('marketing_messages', (int) $m['id'], ['status' => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending', 'attempts' => $attempts, 'error' => mb_substr($e->getMessage(), 0, 500)]);
            }
        }
        return $sent;
    }

    private function deliver(array $c, array $m): void
    {
        match ($c['channel']) {
            'email' => Mailer::send($m['recipient'], (string) $c['subject'], $m['content']),
            'sms' => SmsGateway::send($m['recipient'], $m['content']),
            'whatsapp' => WhatsAppGateway::send($m['recipient'], $m['content'], $this->templateVars($m)),
            default => throw new \RuntimeException('Canal inconnu.'),
        };
    }

    private function templateVars(array $m): array
    {
        $cu = Database::one('SELECT first_name FROM customers WHERE id = ?', [(int) $m['customer_id']]);
        return ['{client}' => (string) ($cu['first_name'] ?? ''), '{entreprise}' => (string) setting('company_name', ''), '{message}' => $m['content']];
    }

    private function finishIfComplete(int $id): void
    {
        $open = (int) Database::val("SELECT COUNT(*) FROM marketing_messages WHERE campaign_id = ? AND status IN ('pending','manual')", [$id]);
        if ($open === 0) Database::exec("UPDATE marketing_campaigns SET status = 'done', finished_at = ? WHERE id = ? AND status = 'sending'", [now(), $id]);
    }

    // ------------------------------------------------------------------ WhatsApp sans API
    public function markManual(int $messageId, bool $sent): void
    {
        $m = Database::one('SELECT m.id, m.campaign_id, m.status FROM marketing_messages m JOIN marketing_campaigns c ON c.id = m.campaign_id WHERE m.id = ? AND c.company_id = ?', [$messageId, Env::companyId()]);
        if (!$m || $m['status'] !== 'manual') return;
        Database::update('marketing_messages', (int) $m['id'], ['status' => $sent ? 'sent' : 'skipped', 'sent_at' => $sent ? now() : null]);
        $this->finishIfComplete((int) $m['campaign_id']);
    }

    /** Envoi d'essai vers un seul numéro ou email. Renvoie un lien WhatsApp à ouvrir quand le mode est « sans API ». */
    public function sendTest(array $campaign, string $to): ?string
    {
        $sample = ['first_name' => 'Awa', 'last_name' => 'Test'];
        $text = '[TEST] ' . $this->render($campaign, $sample);
        return $this->sendDirect($campaign['channel'], $to, $text, (string) $campaign['subject'] ?: 'Test', ['{client}' => 'Awa', '{entreprise}' => (string) setting('company_name', '')]);
    }

    /** Envoi unitaire sur un canal ; renvoie un lien wa.me en mode WhatsApp « sans API ». */
    public function sendDirect(string $channel, string $to, string $text, string $subject = 'Test', array $vars = []): ?string
    {
        switch ($channel) {
            case 'email':
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException("Adresse email invalide.");
                Mailer::send($to, $subject, $text); return null;
            case 'sms':
                $p = normalize_phone($to); if (strlen($p) < 9) throw new \RuntimeException('Numéro invalide.');
                SmsGateway::send($p, $text); return null;
            case 'whatsapp':
                $p = normalize_phone($to); if (strlen($p) < 9) throw new \RuntimeException('Numéro invalide.');
                if (WhatsAppGateway::mode() === 'link') return WhatsAppGateway::link($p, $text);
                WhatsAppGateway::send($p, $text, $vars); return null;
        }
        throw new \RuntimeException('Canal inconnu.');
    }

    public function counts(int $id): array
    {
        $out = ['pending' => 0, 'manual' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'cancelled' => 0];
        foreach (Database::all('SELECT status, COUNT(*) AS n FROM marketing_messages WHERE campaign_id = ? GROUP BY status', [$id]) as $r) $out[$r['status']] = (int) $r['n'];
        $out['total'] = array_sum($out);
        return $out;
    }

    /** Réservations issues d'une campagne (lien suivi utm_campaign = nom de la campagne). */
    public function bookingsFrom(array $campaign): array
    {
        $r = Database::one("SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS revenue FROM bookings WHERE company_id = ? AND utm_medium = 'campaign' AND utm_source = ? AND utm_campaign = ? AND status <> 'cancelled'",
            [Env::companyId(), $campaign['channel'], slugify((string) $campaign['name'])]);
        return ['bookings' => (int) $r['n'], 'revenue' => (int) $r['revenue']];
    }
}
