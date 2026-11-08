<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Database, Env};

/** Consentement aux offres (marketing) : daté, rattaché à sa source, retirable par le client (mot STOP) ou par l'équipe. */
final class ConsentService
{
    public const SOURCES = ['reservation' => 'Case cochée à la réservation', 'admin' => "Saisi par l'équipe", 'whatsapp' => 'Réponse WhatsApp', 'sms' => 'Réponse SMS'];

    /** Mots qui retirent le consentement (comparés sans accents ni casse, ponctuation ignorée). */
    private const STOP_WORDS = ['stop', 'arret', 'arreter', 'unsubscribe', 'desabonner', 'desabonnement', 'desinscrire', 'desinscription', 'annuler'];

    public static function isStop(string $text): bool
    {
        $t = mb_strtolower(trim($text));
        $t = strtr($t, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c']);
        $t = trim(preg_replace('/[^a-z ]+/', '', $t) ?? '');
        return in_array($t, self::STOP_WORDS, true);
    }

    /** Colonnes à ajouter à une mise à jour de fiche client quand la case « accepte nos offres » change. */
    public static function stamp(array $data, ?array $existing, string $source = 'admin'): array
    {
        if (!array_key_exists('marketing_consent', $data)) return $data;
        $was = (int) ($existing['marketing_consent'] ?? 0); $is = (int) $data['marketing_consent'];
        if ($is === $was) return $data;
        return $data + ($is ? ['marketing_consent_at' => now(), 'marketing_optout_at' => null, 'marketing_consent_source' => $source] : ['marketing_optout_at' => now(), 'marketing_consent_source' => $source]);
    }

    public function grant(int $customerId, string $source = 'reservation', ?int $userId = null): void
    {
        $c = Database::one('SELECT id, marketing_consent FROM customers WHERE id = ?', [$customerId]);
        if (!$c || (int) $c['marketing_consent'] === 1) return;
        Database::update('customers', $customerId, ['marketing_consent' => 1, 'marketing_consent_at' => now(), 'marketing_optout_at' => null, 'marketing_consent_source' => $source]);
        $this->trace($customerId, 'Consentement aux offres donné (' . (self::SOURCES[$source] ?? $source) . ').', 'marketing.consent.grant', $userId);
    }

    public function revoke(int $customerId, string $source, ?int $userId = null): void
    {
        $c = Database::one('SELECT id, marketing_consent FROM customers WHERE id = ?', [$customerId]);
        if (!$c || (int) $c['marketing_consent'] === 0) return;
        Database::update('customers', $customerId, ['marketing_consent' => 0, 'marketing_optout_at' => now(), 'marketing_consent_source' => $source]);
        $this->trace($customerId, 'Consentement aux offres retiré (' . (self::SOURCES[$source] ?? $source) . ').', 'marketing.consent.revoke', $userId);
    }

    /** Désinscrit tous les clients portant ce numéro. @return int clients désinscrits */
    public function optOutByPhone(string $phone, string $source): int
    {
        $p = normalize_phone($phone);
        if (strlen($p) < 9) return 0;
        $n = 0;
        foreach (Database::all('SELECT id FROM customers WHERE company_id = ? AND phone = ? AND marketing_consent = 1', [Env::companyId(), $p]) as $r) { $this->revoke((int) $r['id'], $source); $n++; }
        return $n;
    }

    private function trace(int $customerId, string $text, string $audit, ?int $userId): void
    {
        (new CrmService())->addInteraction($customerId, null, $userId, 'note', $text);
        Audit::log($audit, 'customer', $customerId, [], $userId);
    }
}
