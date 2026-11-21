<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Database, Env, Validator, ValidationException};

/**
 * Abonnements : un client ou une entreprise demande un passage régulier (chaque semaine, toutes les 2 semaines…) avec une remise.
 * Une fois l'abonnement activé par l'équipe, chaque passage devient une vraie réservation dans le planning, générée à l'avance (21 jours).
 * Le règlement reste celui des réservations : après l'intervention.
 */
final class SubscriptionService
{
    /** clé => [libellé, intervalle en jours]. « Chaque mois » = toutes les 4 semaines : le même jour de la semaine, sans surprise de calendrier. */
    public const FREQUENCIES = ['weekly' => ['Chaque semaine', 7], 'biweekly' => ['Toutes les 2 semaines', 14], 'monthly' => ['Toutes les 4 semaines', 28], 'quarterly' => ['Tous les 3 mois (13 semaines)', 91]];
    public const STATUSES = ['requested' => ['Demande', 'warning'], 'active' => ['Actif', 'success'], 'paused' => ['En pause', 'secondary'], 'cancelled' => ['Résilié', 'danger']];
    public const HORIZON_DAYS = 21;

    public static function frequencyLabel(string $f): string { return self::FREQUENCIES[$f][0] ?? $f; }

    /** @throws ValidationException */
    public function request(array $in): array
    {
        $plan = Database::one('SELECT * FROM subscription_plans WHERE id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL', [(int) ($in['plan_id'] ?? 0), Env::companyId()]);
        if (!$plan) throw new ValidationException(['plan_id' => "Cette offre n'existe plus."]);
        $kind = ($in['kind'] ?? '') === 'business' ? 'business' : 'person';
        if ($plan['audience'] !== 'all' && $plan['audience'] !== $kind) throw new ValidationException(['kind' => $plan['audience'] === 'business' ? 'Cette offre est réservée aux entreprises.' : 'Cette offre est réservée aux particuliers.']);

        $errors = Validator::check($in, ['first_name' => 'required|maxlen:100', 'last_name' => 'required|maxlen:100', 'phone' => 'required|phone', 'email' => 'email|maxlen:190',
            'neighborhood_id' => 'required|int', 'address_text' => 'required|minlen:5|maxlen:500', 'instructions' => 'maxlen:1000', 'notes' => 'maxlen:1500', 'business_name' => 'maxlen:150',
            'weekday' => 'required|in:1,2,3,4,5,6', 'start_date' => 'required|date'],
            ['first_name' => 'Le prénom', 'last_name' => 'Le nom', 'phone' => 'Le téléphone', 'email' => "L'email", 'neighborhood_id' => 'Le quartier', 'address_text' => "L'adresse",
             'instructions' => 'Les instructions', 'notes' => 'Le message', 'business_name' => "Le nom de l'entreprise", 'weekday' => 'Le jour', 'start_date' => 'La date de début']);
        if ($kind === 'business' && trim((string) ($in['business_name'] ?? '')) === '') $errors['business_name'] = "Indiquez le nom de l'entreprise.";
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($in['start_time'] ?? ''))) $errors['start_time'] = "L'heure est invalide.";
        $sd = (string) ($in['start_date'] ?? '');
        if (!isset($errors['start_date']) && ($sd < date('Y-m-d', strtotime('+1 day')) || $sd > date('Y-m-d', strtotime('+180 days')))) $errors['start_date'] = 'Choisissez une date entre demain et dans 6 mois.';
        $nid = (int) ($in['neighborhood_id'] ?? 0);
        if (!isset($errors['neighborhood_id']) && !(new AvailabilityService())->neighborhood($nid)) $errors['neighborhood_id'] = "Ce quartier n'est pas desservi.";

        $inputs = ['formula_id' => (int) ($in['formula_id'] ?? 0) ?: null, 'fields' => is_array($in['fields'] ?? null) ? array_map(fn($v) => is_scalar($v) ? (string) $v : '', $in['fields']) : [],
                   'options' => array_values(array_filter(array_map('intval', is_array($in['options'] ?? null) ? $in['options'] : [])))];
        try { (new PricingService())->quote((int) $plan['service_id'], $inputs, $nid ?: null); }
        catch (ValidationException $e) { $errors += $e->errors; }
        if ($errors) throw new ValidationException($errors);

        $cs = new CustomerService();
        $customerId = $cs->findOrCreate(trim($in['first_name']), trim($in['last_name']), $in['phone'], trim((string) ($in['email'] ?? '')) ?: null);
        $dup = Database::one("SELECT * FROM subscriptions WHERE company_id = ? AND customer_id = ? AND plan_id = ? AND status = 'requested' AND created_at > ?", [Env::companyId(), $customerId, (int) $plan['id'], gmdate('Y-m-d H:i:s', time() - 86400)]);
        if ($dup) return $dup;   // double clic ou réseau lent : une seule demande
        $id = Database::insert('subscriptions', ['company_id' => Env::companyId(), 'plan_id' => (int) $plan['id'], 'customer_id' => $customerId, 'kind' => $kind,
            'business_name' => $kind === 'business' ? trim((string) $in['business_name']) : null, 'service_id' => (int) $plan['service_id'], 'neighborhood_id' => $nid,
            'address_text' => trim($in['address_text']), 'instructions' => trim((string) ($in['instructions'] ?? '')) ?: null, 'frequency' => $plan['frequency'], 'weekday' => (int) $in['weekday'],
            'start_time' => $in['start_time'], 'start_date' => $sd, 'discount_percent' => (int) $plan['discount_percent'], 'inputs' => json_encode($inputs, JSON_UNESCAPED_UNICODE),
            'status' => 'requested', 'notes' => trim((string) ($in['notes'] ?? '')) ?: null, 'track_token' => bin2hex(random_bytes(16)), 'created_at' => now()]);
        Audit::log('subscription.request', 'subscription', $id, ['plan' => $plan['name']], null);
        try {
            $to = setting('admin_notification_email', '');
            if ($to) \App\Services\Channels\Mailer::send($to, 'Demande d\'abonnement — ' . trim($in['first_name'] . ' ' . $in['last_name']), $plan['name'] . ' · ' . $in['phone']);
        } catch (\Throwable $e) { error_log('Abonnement mail: ' . $e->getMessage()); }
        return $this->find($id);
    }

    public function find(int $id): ?array
    {
        return Database::one('SELECT s.*, p.name AS plan_name, c.first_name, c.last_name, c.phone, c.email, sv.name AS service_name, n.name AS neighborhood_name
            FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id JOIN customers c ON c.id = s.customer_id JOIN services sv ON sv.id = s.service_id JOIN neighborhoods n ON n.id = s.neighborhood_id
            WHERE s.id = ? AND s.company_id = ?', [$id, Env::companyId()]);
    }

    public function findByToken(string $token): ?array
    {
        $id = Database::val('SELECT id FROM subscriptions WHERE track_token = ? AND company_id = ?', [$token, Env::companyId()]);
        return $id ? $this->find((int) $id) : null;
    }

    /** Prix d'un passage : tarif actuel du service, remise comprise. @return array{subtotal:int,travel_fee:int,discount:int,total:int,duration:int,details:array} */
    public function estimate(array $sub): array
    {
        $inputs = json_decode((string) $sub['inputs'], true) ?: [];
        $q = (new PricingService())->quote((int) $sub['service_id'], $inputs, (int) $sub['neighborhood_id'], true);
        $discount = (int) round($q['subtotal'] * max(0, min(50, (int) $sub['discount_percent'])) / 100);
        return ['subtotal' => $q['subtotal'], 'travel_fee' => $q['travel_fee'], 'discount' => $discount, 'total' => max(0, $q['total'] - $discount), 'duration' => $q['duration'], 'details' => $q['details']];
    }

    /** Premier jour de la semaine voulu à partir de la date de début : il fixe le rythme (toutes les 2 semaines reste toutes les 2 semaines). */
    public static function anchor(array $sub): string
    {
        $t = strtotime((string) $sub['start_date']);
        $shift = ((int) $sub['weekday'] - (int) date('N', $t) + 7) % 7;
        return date('Y-m-d', strtotime("+$shift day", $t));
    }

    /** Dates des passages entre deux jours (inclus). @return string[] */
    public static function dates(array $sub, string $from, string $to): array
    {
        $step = (self::FREQUENCIES[$sub['frequency']][1] ?? 7) * 86400;
        $t = strtotime(self::anchor($sub)); $out = [];
        $end = strtotime($to);
        for ($i = 0; $t <= $end && $i < 400; $t += $step, $i++) {
            $d = date('Y-m-d', $t);
            if ($d >= $from) $out[] = $d;
        }
        return $out;
    }

    /** L'équipe active l'abonnement : on peut ajuster jour, heure, début et remise, puis les passages sont générés. */
    public function activate(int $id, array $changes, ?int $userId): array
    {
        $s = $this->find($id) ?? throw new \RuntimeException('Abonnement introuvable.');
        if ($s['status'] !== 'requested') throw new \RuntimeException('Cet abonnement est déjà traité.');
        $upd = ['status' => 'active', 'activated_at' => now(), 'updated_at' => now()];
        if (isset($changes['weekday']) && in_array((int) $changes['weekday'], [1, 2, 3, 4, 5, 6], true)) $upd['weekday'] = (int) $changes['weekday'];
        if (isset($changes['start_time']) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $changes['start_time'])) $upd['start_time'] = $changes['start_time'];
        if (isset($changes['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $changes['start_date'])) $upd['start_date'] = $changes['start_date'];
        if (isset($changes['discount_percent']) && is_numeric($changes['discount_percent'])) $upd['discount_percent'] = max(0, min(50, (int) $changes['discount_percent']));
        Database::update('subscriptions', $id, $upd);
        Audit::log('subscription.activate', 'subscription', $id, $upd, $userId);
        return $this->generate($id);
    }

    public function pause(int $id, ?string $until, string $by, ?int $userId): void
    {
        $s = $this->find($id) ?? throw new \RuntimeException('Abonnement introuvable.');
        if ($s['status'] !== 'active') throw new \RuntimeException("Seul un abonnement actif peut être mis en pause.");
        if ($until !== null && ($until < date('Y-m-d', strtotime('+1 day')) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until))) throw new \RuntimeException('La date de reprise doit être dans le futur.');
        Database::update('subscriptions', $id, ['status' => 'paused', 'paused_until' => $until, 'updated_at' => now()]);
        $n = $this->cancelFuture($id, $until, 'Abonnement en pause', $userId);
        Audit::log('subscription.pause', 'subscription', $id, ['until' => $until, 'by' => $by, 'cancelled' => $n], $userId);
    }

    public function resume(int $id, ?int $userId): array
    {
        $s = $this->find($id) ?? throw new \RuntimeException('Abonnement introuvable.');
        if ($s['status'] !== 'paused') throw new \RuntimeException("Cet abonnement n'est pas en pause.");
        Database::update('subscriptions', $id, ['status' => 'active', 'paused_until' => null, 'updated_at' => now()]);
        Database::exec("DELETE FROM subscription_visits WHERE subscription_id = ? AND status = 'skipped' AND visit_date >= ?", [$id, date('Y-m-d', strtotime('+1 day'))]);   // les dates annulées par la pause redeviennent réservables
        Audit::log('subscription.resume', 'subscription', $id, [], $userId);
        return $this->generate($id);
    }

    public function cancel(int $id, string $reason, ?int $userId): void
    {
        $s = $this->find($id) ?? throw new \RuntimeException('Abonnement introuvable.');
        if ($s['status'] === 'cancelled') return;
        Database::update('subscriptions', $id, ['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 255) ?: null, 'updated_at' => now()]);
        $n = $this->cancelFuture($id, null, 'Abonnement résilié', $userId);
        Audit::log('subscription.cancel', 'subscription', $id, ['reason' => $reason, 'cancelled' => $n], $userId);
    }

    /** Annule, sans message au client, les passages à venir (jusqu'à $until inclus, ou tous si null). Les passages déjà en cours ou terminés ne sont jamais touchés. */
    private function cancelFuture(int $id, ?string $until, string $note, ?int $userId): int
    {
        $svc = new BookingService(); $n = 0;
        $rows = Database::all("SELECT b.id, b.scheduled_date FROM bookings b WHERE b.subscription_id = ? AND b.status IN ('pending','confirmed') AND b.scheduled_date >= ?" . ($until ? ' AND b.scheduled_date <= ?' : ''),
            $until ? [$id, date('Y-m-d', strtotime('+1 day')), $until] : [$id, date('Y-m-d', strtotime('+1 day'))]);
        foreach ($rows as $r) {
            try { $svc->changeStatus((int) $r['id'], 'cancelled', $userId, $note, true); $n++; } catch (\Throwable $e) { error_log('Abonnement annulation: ' . $e->getMessage()); }
            Database::exec("UPDATE subscription_visits SET status = 'skipped', note = ? WHERE subscription_id = ? AND visit_date = ?", [$note, $id, $r['scheduled_date']]);
        }
        return $n;
    }

    /** Génère les passages des 21 prochains jours qui n'existent pas encore. Idempotent : peut être relancé à tout moment. @return array{booked:int,unavailable:int} */
    public function generate(int $id, int $horizon = self::HORIZON_DAYS): array
    {
        $s = $this->find($id);
        $res = ['booked' => 0, 'unavailable' => 0];
        if (!$s || $s['status'] !== 'active') return $res;
        $from = date('Y-m-d', strtotime('+1 day')); $to = date('Y-m-d', strtotime("+$horizon day"));
        foreach (self::dates($s, $from, $to) as $date) {
            $v = Database::one('SELECT * FROM subscription_visits WHERE subscription_id = ? AND visit_date = ?', [$id, $date]);
            if ($v && $v['status'] !== 'unavailable') continue;   // déjà réservé, ou volontairement ignoré
            if ($v) Database::exec("UPDATE subscription_visits SET status = 'booked', note = NULL WHERE id = ? AND status = 'unavailable'", [(int) $v['id']]);
            else {
                try { Database::insert('subscription_visits', ['subscription_id' => $id, 'visit_date' => $date, 'status' => 'booked', 'created_at' => now()]); }
                catch (\PDOException $e) { continue; }   // un autre processus vient de prendre cette date
            }
            [$bookingId, $note] = $this->book($s, $date);
            if ($bookingId) { Database::exec('UPDATE subscription_visits SET booking_id = ?, note = ? WHERE subscription_id = ? AND visit_date = ?', [$bookingId, $note, $id, $date]); $res['booked']++; }
            else { Database::exec("UPDATE subscription_visits SET status = 'unavailable', note = ? WHERE subscription_id = ? AND visit_date = ?", [mb_substr((string) $note, 0, 255), $id, $date]); $res['unavailable']++; }
        }
        return $res;
    }

    /** Tâche planifiée : reprend les pauses échues, puis génère les passages de tous les abonnements actifs. */
    public function generateAll(): array
    {
        foreach (Database::all("SELECT id FROM subscriptions WHERE status = 'paused' AND paused_until IS NOT NULL AND paused_until <= ?", [date('Y-m-d')]) as $r) {
            try { $this->resume((int) $r['id'], null); } catch (\Throwable $e) { error_log('Abonnement reprise: ' . $e->getMessage()); }
        }
        $tot = ['subscriptions' => 0, 'booked' => 0, 'unavailable' => 0];
        foreach (Database::all("SELECT id FROM subscriptions WHERE status = 'active' AND company_id = ?", [Env::companyId()]) as $r) {
            try { $g = $this->generate((int) $r['id']); $tot['subscriptions']++; $tot['booked'] += $g['booked']; $tot['unavailable'] += $g['unavailable']; }
            catch (\Throwable $e) { error_log('Abonnement génération: ' . $e->getMessage()); }
        }
        return $tot;
    }

    /** @return array{0:?int,1:?string} identifiant de la réservation, ou null et la raison */
    private function book(array $s, string $date): array
    {
        $inputs = json_decode((string) $s['inputs'], true) ?: [];
        $in = $inputs + ['service_id' => (int) $s['service_id'], 'neighborhood_id' => (int) $s['neighborhood_id'], 'first_name' => $s['first_name'], 'last_name' => $s['last_name'], 'phone' => $s['phone'],
            'email' => $s['email'], 'address_text' => $s['address_text'], 'instructions' => $s['instructions'], 'date' => $date, 'start' => $s['start_time'], 'source' => 'subscription',
            'subscription_id' => (int) $s['id'], 'discount_percent' => (int) $s['discount_percent'], 'silent' => true, 'status' => 'confirmed'];
        $svc = new BookingService();
        try { return [(int) $svc->create($in)['id'], null]; }
        catch (SlotUnavailableException $e) {
            // Créneau pris : on cherche l'heure libre la plus proche le même jour (à moins de 3 heures).
            try {
                $q = (new PricingService())->quote((int) $s['service_id'], $inputs, (int) $s['neighborhood_id']);
                $want = hm_to_min($s['start_time']); $best = null;
                foreach ((new AvailabilityService())->slots((int) $s['service_id'], (int) $s['neighborhood_id'], $q['duration'], $date) as $sl) {
                    $d = abs(hm_to_min($sl['start']) - $want);
                    if ($d <= 180 && ($best === null || $d < $best[0])) $best = [$d, $sl['start']];
                }
                if ($best) return [(int) $svc->create(['start' => $best[1]] + $in)['id'], 'Heure décalée à ' . $best[1] . ' (créneau habituel pris)'];
            } catch (\Throwable $e2) { /* repli ci-dessous */ }
            return [null, 'Aucun créneau libre ce jour-là : à replanifier.'];
        }
        catch (ValidationException $e) { return [null, 'Réservation impossible : ' . implode(' ', array_map('strval', $e->errors))]; }
        catch (\Throwable $e) { error_log('Abonnement passage: ' . $e->getMessage()); return [null, 'Erreur technique : à replanifier.']; }
    }
}
