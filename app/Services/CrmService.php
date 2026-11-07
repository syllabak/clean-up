<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Database, Env};

/** CRM : prospects (pipeline), interactions, rattachement des clients et réservations aux canaux d'acquisition. */
final class CrmService
{
    public const LEAD_STATUSES = ['new' => ['Nouveau', 'primary'], 'contacted' => ['Contacté', 'info'], 'quoted' => ['Devis envoyé', 'warning'], 'won' => ['Gagné', 'success'], 'lost' => ['Perdu', 'danger']];
    public const OPEN_STATUSES = ['new', 'contacted', 'quoted'];
    public const INTERACTION_TYPES = ['call' => 'Appel', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'visit' => 'Visite', 'note' => 'Note'];

    public static function statusBadge(string $s): string
    {
        [$l, $c] = self::LEAD_STATUSES[$s] ?? [$s, 'light'];
        return '<span class="badge text-bg-' . $c . '">' . e($l) . '</span>';
    }

    /** Segment d'un client d'après ses prestations terminées. */
    public static function segment(int $completed, int $revenue): string
    {
        if ($completed === 0) return 'Nouveau';
        if ($completed >= 5 || $revenue >= 100000) return 'VIP';
        return $completed >= 2 ? 'Régulier' : 'Occasionnel';
    }

    public function channelId(?int $id): ?int
    {
        if (!$id) return null;
        $ok = Database::val('SELECT id FROM acquisition_channels WHERE id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL', [$id, Env::companyId()]);
        return $ok ? (int) $ok : null;
    }

    public function channelByCode(string $code): ?int
    {
        $id = Database::val('SELECT id FROM acquisition_channels WHERE company_id = ? AND code = ? AND deleted_at IS NULL', [Env::companyId(), $code]);
        return $id ? (int) $id : null;
    }

    /** Crée un prospect. Un prospect encore ouvert avec le même téléphone est réutilisé (pas de doublon). */
    public function createLead(array $d, ?int $userId = null): int
    {
        $phone = !empty($d['phone']) ? normalize_phone((string) $d['phone']) : null;
        if ($phone) {
            $dup = Database::val("SELECT id FROM leads WHERE company_id = ? AND phone = ? AND deleted_at IS NULL AND status IN ('new','contacted','quoted')", [Env::companyId(), $phone]);
            if ($dup) { $this->addInteraction(null, (int) $dup, $userId, 'note', 'Nouvelle prise de contact : ' . mb_substr((string) ($d['notes'] ?? 'même numéro'), 0, 300)); return (int) $dup; }
        }
        $id = Database::insert('leads', [
            'company_id' => Env::companyId(), 'name' => mb_substr(trim((string) $d['name']), 0, 150), 'phone' => $phone, 'email' => ($d['email'] ?? '') ?: null,
            'channel_id' => $this->channelId((int) ($d['channel_id'] ?? 0)), 'service_id' => ((int) ($d['service_id'] ?? 0)) ?: null, 'estimated_value' => (int) ($d['estimated_value'] ?? 0),
            'assigned_user_id' => ((int) ($d['assigned_user_id'] ?? 0)) ?: null, 'next_follow_up' => ($d['next_follow_up'] ?? '') ?: date('Y-m-d', strtotime('+1 day')),
            'notes' => ($d['notes'] ?? '') ?: null, 'status' => 'new', 'created_at' => now(),
        ]);
        Audit::log('lead.create', 'lead', $id, [], $userId);
        return $id;
    }

    public function setStatus(int $id, string $status, ?int $userId, ?string $lostReason = null): void
    {
        if (!isset(self::LEAD_STATUSES[$status])) throw new \RuntimeException('Statut inconnu.');
        $lead = $this->lead($id) ?? throw new \RuntimeException('Prospect introuvable.');
        if ($lead['status'] === $status) return;
        Database::update('leads', $id, ['status' => $status, 'lost_reason' => $status === 'lost' ? mb_substr((string) $lostReason, 0, 255) : null, 'updated_at' => now()]);
        $this->addInteraction($lead['customer_id'] ? (int) $lead['customer_id'] : null, $id, $userId, 'note', 'Statut : ' . self::LEAD_STATUSES[$lead['status']][0] . ' → ' . self::LEAD_STATUSES[$status][0] . ($lostReason && $status === 'lost' ? " ($lostReason)" : ''));
        Audit::log('lead.status', 'lead', $id, ['to' => $status], $userId);
    }

    /** Transforme un prospect en client (retrouvé par téléphone) et le marque « gagné ». @return int id client */
    public function convert(int $id, ?int $userId): int
    {
        $lead = $this->lead($id) ?? throw new \RuntimeException('Prospect introuvable.');
        if (!$lead['phone']) throw new \RuntimeException('Un numéro de téléphone est nécessaire pour créer le client.');
        $parts = preg_split('/\s+/', trim($lead['name']), 2) ?: [$lead['name']];
        $cid = (new CustomerService())->findOrCreate($parts[0], $parts[1] ?? '-', $lead['phone'], $lead['email']);
        Database::exec('UPDATE customers SET channel_id = ? WHERE id = ? AND channel_id IS NULL', [$lead['channel_id'], $cid]);
        Database::update('leads', $id, ['customer_id' => $cid, 'status' => 'won', 'lost_reason' => null, 'updated_at' => now()]);
        $this->addInteraction($cid, $id, $userId, 'note', 'Prospect converti en client.');
        Audit::log('lead.convert', 'lead', $id, ['customer_id' => $cid], $userId);
        return $cid;
    }

    public function addInteraction(?int $customerId, ?int $leadId, ?int $userId, string $type, string $summary): void
    {
        $summary = trim($summary);
        if ($summary === '' || (!$customerId && !$leadId)) return;
        Database::insert('interactions', ['company_id' => Env::companyId(), 'customer_id' => $customerId, 'lead_id' => $leadId, 'user_id' => $userId,
            'type' => isset(self::INTERACTION_TYPES[$type]) ? $type : 'note', 'summary' => mb_substr($summary, 0, 2000), 'created_at' => now()]);
    }

    /**
     * À appeler après la création d'une réservation : attribue le canal (choix du client, sinon canal du client, sinon « site web »)
     * et clôt comme « gagné » le prospect ouvert qui porte le même numéro.
     */
    public function onBookingCreated(int $bookingId, int $customerId, ?int $channelId, string $source = 'web'): void
    {
        $customer = Database::one('SELECT phone, channel_id FROM customers WHERE id = ?', [$customerId]);
        $lead = Database::one("SELECT id, channel_id FROM leads WHERE company_id = ? AND phone = ? AND deleted_at IS NULL AND status IN ('new','contacted','quoted') ORDER BY id DESC LIMIT 1", [Env::companyId(), $customer['phone']]);
        $channel = $this->channelId($channelId) ?? ($customer['channel_id'] ? (int) $customer['channel_id'] : null) ?? ($lead && $lead['channel_id'] ? (int) $lead['channel_id'] : null) ?? $this->channelByCode('web');
        Database::update('bookings', $bookingId, ['channel_id' => $channel]);
        if ($channel) Database::exec('UPDATE customers SET channel_id = ? WHERE id = ? AND channel_id IS NULL', [$channel, $customerId]);
        if ($lead) {
            Database::update('leads', (int) $lead['id'], ['status' => 'won', 'customer_id' => $customerId, 'updated_at' => now()]);
            $this->addInteraction($customerId, (int) $lead['id'], null, 'note', 'Réservation créée : prospect converti automatiquement.');
        }
    }

    public function lead(int $id): ?array
    {
        return Database::one('SELECT * FROM leads WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [$id, Env::companyId()]);
    }
}
