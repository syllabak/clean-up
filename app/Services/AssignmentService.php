<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Audit, Database};
use App\Repositories\BookingRepository;

final class AssignmentService
{
    private BookingRepository $repo;
    private AvailabilityService $availability;

    public function __construct()
    {
        $this->repo = new BookingRepository();
        $this->availability = new AvailabilityService();
    }

    /**
     * Affectation manuelle à une équipe et/ou un agent.
     * @return string[] avertissements ignorés grâce à $force (traçables dans le journal d'audit)
     * @throws \RuntimeException si un conflit empêche l'affectation et que $force est faux
     */
    public function assign(int $bookingId, ?int $teamId, ?int $staffId, ?int $userId, bool $force = false): array
    {
        $b = $this->repo->find($bookingId) ?? throw new \RuntimeException('Réservation introuvable.');
        if (in_array($b['status'], ['completed', 'cancelled'], true)) throw new \RuntimeException('Réservation close : affectation impossible.');
        $staff = null;
        if ($staffId) {
            $staff = Database::one("SELECT * FROM staff WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$staffId]) ?? throw new \RuntimeException('Agent introuvable ou inactif.');
            if (!$staff['team_id']) throw new \RuntimeException("Cet agent n'appartient à aucune équipe.");
            if ($teamId && (int) $staff['team_id'] !== $teamId) throw new \RuntimeException("Cet agent n'appartient pas à l'équipe choisie.");
            $teamId = (int) $staff['team_id'];
        }
        if (!$teamId) throw new \RuntimeException('Choisissez une équipe ou un agent.');

        $warnings = [];
        if ($teamId !== (int) $b['team_id']) {
            $warnings = array_merge($warnings, $this->availability->teamConflicts($teamId, (int) $b['service_id'], (int) $b['neighborhood_id'], $b['scheduled_date'], $b['start_time'], (int) $b['duration_minutes'], $bookingId));
        }
        if (!Database::val('SELECT 1 FROM team_services WHERE team_id = ? AND service_id = ?', [$teamId, $b['service_id']])) $warnings[] = "L'équipe ne prend pas ce service en charge.";
        if ($staff) {
            if (Database::val('SELECT id FROM time_off WHERE staff_id = ? AND deleted_at IS NULL AND start_date <= ? AND end_date >= ?', [$staffId, $b['scheduled_date'], $b['scheduled_date']])) $warnings[] = "L'agent est absent ce jour-là.";
            $clash = Database::all("SELECT b2.reference, b2.start_time, b2.end_time FROM booking_assignments ba JOIN bookings b2 ON b2.id = ba.booking_id
                WHERE ba.active = 1 AND ba.staff_id = ? AND b2.id <> ? AND b2.status <> 'cancelled' AND b2.scheduled_date = ?", [$staffId, $bookingId, $b['scheduled_date']]);
            foreach ($clash as $c) if (hm_to_min($b['start_time']) < hm_to_min($c['end_time']) && hm_to_min($b['end_time']) > hm_to_min($c['start_time'])) $warnings[] = "L'agent est déjà sur la mission {$c['reference']} à ce moment.";
        }
        if ($warnings && !$force) throw new \RuntimeException(implode(' ', $warnings));

        Database::transaction(function () use ($bookingId, $teamId, $staffId, $userId, $b) {
            Database::exec('UPDATE booking_assignments SET active = 0 WHERE booking_id = ?', [$bookingId]);
            Database::insert('booking_assignments', ['booking_id' => $bookingId, 'team_id' => $teamId, 'staff_id' => $staffId, 'assigned_by' => $userId, 'active' => 1, 'created_at' => now()]);
            $upd = ['team_id' => $teamId, 'updated_at' => now()];
            if (in_array($b['status'], ['pending', 'confirmed'], true)) {
                $upd['status'] = 'assigned';
                (new BookingService())->history($bookingId, $b['status'], 'assigned', $userId, 'Affectation');
            }
            Database::update('bookings', $bookingId, $upd);
        });
        Audit::log('booking.assign', 'booking', $bookingId, ['team' => $teamId, 'staff' => $staffId, 'forced_warnings' => $warnings], $userId);
        (new NotificationService())->dispatch('booking.assigned', $bookingId);
        return $warnings;
    }

    /** Affectation automatique : équipe déjà réservée + agent choisi par la stratégie. Désactivée par défaut. */
    public function autoAssign(int $bookingId, ?AssignmentStrategy $strategy = null): bool
    {
        $b = $this->repo->find($bookingId);
        if (!$b || !$b['team_id']) return false;
        $members = Database::all("SELECT s.*, (SELECT COUNT(*) FROM booking_assignments ba JOIN bookings b2 ON b2.id = ba.booking_id
                WHERE ba.active = 1 AND ba.staff_id = s.id AND b2.scheduled_date = ? AND b2.status <> 'cancelled') AS missions_today
            FROM staff s WHERE s.team_id = ? AND s.status = 'active' AND s.deleted_at IS NULL", [$b['scheduled_date'], $b['team_id']]);
        $svc = Database::one('SELECT required_skill_id FROM services WHERE id = ?', [$b['service_id']]);
        $members = array_values(array_filter($members, function ($m) use ($svc, $b) {
            if (!empty($svc['required_skill_id']) && !Database::val('SELECT 1 FROM staff_skills WHERE staff_id = ? AND skill_id = ?', [$m['id'], $svc['required_skill_id']])) return false;
            return !Database::val('SELECT id FROM time_off WHERE staff_id = ? AND deleted_at IS NULL AND start_date <= ? AND end_date >= ?', [$m['id'], $b['scheduled_date'], $b['scheduled_date']]);
        }));
        $staffId = ($strategy ?? new LeastLoadedStrategy())->pick($b, $members);
        try { $this->assign($bookingId, (int) $b['team_id'], $staffId, null); return true; } catch (\Throwable) { return false; }
    }

    /** L'agent connecté a-t-il le droit de voir/traiter cette mission ? (propriété vérifiée côté serveur) */
    public function staffCanAccess(?int $staffId, int $bookingId): bool
    {
        if (!$staffId) return false;
        $me = Database::one('SELECT team_id FROM staff WHERE id = ? AND deleted_at IS NULL', [$staffId]);
        if (!$me) return false;
        $a = Database::one('SELECT team_id, staff_id FROM booking_assignments WHERE booking_id = ? AND active = 1 ORDER BY id DESC LIMIT 1', [$bookingId]);
        if (!$a) return false;
        if ($a['staff_id'] !== null) return (int) $a['staff_id'] === $staffId;
        return $me['team_id'] !== null && (int) $a['team_id'] === (int) $me['team_id'];
    }

    /** Missions visibles par un agent entre deux dates. */
    public function missionsFor(int $staffId, string $from, string $to): array
    {
        $me = Database::one('SELECT team_id FROM staff WHERE id = ?', [$staffId]);
        $ids = Database::all("SELECT b.id FROM bookings b JOIN booking_assignments ba ON ba.booking_id = b.id AND ba.active = 1
            WHERE b.scheduled_date >= ? AND b.scheduled_date <= ? AND b.status IN ('assigned','en_route','in_progress','completed')
              AND (ba.staff_id = ? OR (ba.staff_id IS NULL AND ba.team_id = ?)) ORDER BY b.scheduled_date, b.start_time", [$from, $to, $staffId, $me['team_id'] ?? 0]);
        return array_values(array_filter(array_map(fn($r) => $this->repo->find((int) $r['id']), $ids)));
    }
}
