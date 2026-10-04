<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Database, Env};

/**
 * Moteur de disponibilités. Un créneau n'est proposé que si au moins une équipe :
 *  - propose ce service, possède la compétence requise et dessert le quartier ;
 *  - travaille ce jour-là (horaires, pause, congés d'équipe, absences des membres, fermeture entreprise) ;
 *  - n'a pas atteint sa capacité quotidienne ;
 *  - n'a aucune intervention qui chevauche le créneau, trajet compris.
 * Le quartier peut en plus limiter les jours et les plages horaires d'intervention.
 */
final class AvailabilityService
{
    /** Créneaux libres d'une journée, un par heure de début (meilleure équipe retenue). */
    public function slots(int $serviceId, int $neighborhoodId, int $duration, string $date, ?int $excludeBookingId = null, ?int $onlyTeamId = null): array
    {
        $n = $this->neighborhood($neighborhoodId);
        if (!$n || !$this->dayAllowed($n, $date)) return [];
        $cid = Env::companyId();
        if ($this->companyClosed($date)) return [];

        $byStart = [];
        foreach ($this->eligibleTeams($serviceId, $neighborhoodId) as $team) {
            if ($onlyTeamId && (int) $team['id'] !== $onlyTeamId) continue;
            foreach ($this->teamSlots($team, $n, $duration, $date, $excludeBookingId) as $s) {
                $cur = $byStart[$s['start']] ?? null;
                if (!$cur || $this->better($s, $cur)) $byStart[$s['start']] = $s;
            }
        }
        ksort($byStart);
        return array_values($byStart);
    }

    /** Nombre de créneaux par jour sur la période réservable : [date => ['count'=>n, 'grouped'=>bool]] */
    public function days(int $serviceId, int $neighborhoodId, int $duration): array
    {
        $max = (int) (setting('max_days_ahead', '30'));
        $out = [];
        for ($i = 0; $i <= $max; $i++) {
            $d = date('Y-m-d', strtotime("+$i day"));
            $slots = $this->slots($serviceId, $neighborhoodId, $duration, $d);
            $out[$d] = ['count' => count($slots), 'grouped' => (bool) array_filter($slots, fn($s) => $s['grouped'])];
        }
        return $out;
    }

    /** Retrouve un créneau précis (ou null). Utilisé à la validation finale côté serveur. */
    public function findSlot(int $serviceId, int $neighborhoodId, int $duration, string $date, string $start, ?int $excludeBookingId = null, ?int $onlyTeamId = null): ?array
    {
        foreach ($this->slots($serviceId, $neighborhoodId, $duration, $date, $excludeBookingId, $onlyTeamId) as $s) {
            if ($s['start'] === $start) return $s;
        }
        return null;
    }

    /**
     * Une équipe peut-elle absorber cette intervention (utilisé pour l'affectation manuelle) ?
     * @return string[] liste de motifs de refus (vide = possible)
     */
    public function teamConflicts(int $teamId, int $serviceId, int $neighborhoodId, string $date, string $start, int $duration, ?int $excludeBookingId = null): array
    {
        $reasons = [];
        $team = Database::one("SELECT * FROM teams WHERE id = ? AND deleted_at IS NULL", [$teamId]);
        if (!$team || $team['status'] !== 'active') return ["Équipe indisponible."];
        $n = $this->neighborhood($neighborhoodId);
        if (!$n) return ['Quartier inconnu.'];
        if ($this->companyClosed($date)) $reasons[] = "L'entreprise est fermée ce jour-là.";
        if ($this->teamOff($teamId, $date)) $reasons[] = "L'équipe est en congé ce jour-là.";
        $wh = $this->hours($teamId, $date);
        $s = hm_to_min($start); $e = $s + $duration;
        if (!$wh) $reasons[] = "L'équipe ne travaille pas ce jour-là.";
        elseif ($s < hm_to_min($wh['start_time']) || $e > hm_to_min($wh['end_time'])) $reasons[] = "Hors des horaires de l'équipe ({$wh['start_time']}–{$wh['end_time']}).";
        $busy = $this->busyIntervals($teamId, $date, $n, $excludeBookingId);
        foreach ($busy as [$bs, $be]) if ($s < $be && $e > $bs) { $reasons[] = 'Chevauche une autre intervention (trajet compris).'; break; }
        if (count($busy) >= (int) $team['daily_capacity']) $reasons[] = 'Capacité quotidienne atteinte.';
        return array_values(array_unique($reasons));
    }

    // ---------------------------------------------------------------- internes

    public function neighborhood(int $id): ?array
    {
        return Database::one('SELECT * FROM neighborhoods WHERE id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL', [$id, Env::companyId()]);
    }

    private function dayAllowed(array $n, string $date): bool
    {
        $dow = (int) date('N', strtotime($date));
        if ($n['intervention_days'] !== '' && !in_array((string) $dow, explode(',', $n['intervention_days']), true)) return false;
        $today = date('Y-m-d');
        return $date >= $today && $date <= date('Y-m-d', strtotime('+' . (int) setting('max_days_ahead', '30') . ' day'));
    }

    private function companyClosed(string $date): bool
    {
        return (bool) Database::val('SELECT id FROM time_off WHERE company_id = ? AND team_id IS NULL AND staff_id IS NULL AND deleted_at IS NULL AND start_date <= ? AND end_date >= ?', [Env::companyId(), $date, $date]);
    }

    private function teamOff(int $teamId, string $date): bool
    {
        return (bool) Database::val('SELECT id FROM time_off WHERE team_id = ? AND deleted_at IS NULL AND start_date <= ? AND end_date >= ?', [$teamId, $date, $date]);
    }

    /** Une équipe qui compte des membres doit en avoir au moins un présent. */
    private function membersAvailable(int $teamId, string $date): bool
    {
        $members = Database::all("SELECT id FROM staff WHERE team_id = ? AND status = 'active' AND deleted_at IS NULL", [$teamId]);
        if (!$members) return true;
        foreach ($members as $m) {
            if (!Database::val('SELECT id FROM time_off WHERE staff_id = ? AND deleted_at IS NULL AND start_date <= ? AND end_date >= ?', [$m['id'], $date, $date])) return true;
        }
        return false;
    }

    private function hours(int $teamId, string $date): ?array
    {
        return Database::one('SELECT * FROM working_hours WHERE team_id = ? AND day_of_week = ? ORDER BY id LIMIT 1', [$teamId, (int) date('N', strtotime($date))]);
    }

    public function eligibleTeams(int $serviceId, int $neighborhoodId): array
    {
        $svc = Database::one('SELECT required_skill_id FROM services WHERE id = ?', [$serviceId]);
        $sql = "SELECT t.* FROM teams t JOIN team_services ts ON ts.team_id = t.id AND ts.service_id = ?
                WHERE t.company_id = ? AND t.status = 'active' AND t.deleted_at IS NULL";
        $params = [$serviceId, Env::companyId()];
        if (!empty($svc['required_skill_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM staff s JOIN staff_skills ss ON ss.staff_id = s.id
                      WHERE s.team_id = t.id AND s.status = 'active' AND s.deleted_at IS NULL AND ss.skill_id = ?)";
            $params[] = (int) $svc['required_skill_id'];
        }
        $teams = Database::all($sql . ' ORDER BY t.id', $params);
        // Si des équipes sont affectées au quartier, seules celles-ci interviennent.
        $mapped = array_column(Database::all('SELECT team_id FROM neighborhood_teams WHERE neighborhood_id = ?', [$neighborhoodId]), 'team_id');
        if ($mapped) $teams = array_values(array_filter($teams, fn($t) => in_array($t['id'], $mapped)));
        return $teams;
    }

    /** Intervalles occupés (minutes) d'une équipe, élargis du temps de trajet vers le quartier cible. */
    private function busyIntervals(int $teamId, string $date, array $target, ?int $excludeId): array
    {
        $rows = Database::all("SELECT b.start_time, b.end_time, b.neighborhood_id, n.avg_travel_minutes
                               FROM bookings b JOIN neighborhoods n ON n.id = b.neighborhood_id
                               WHERE b.team_id = ? AND b.scheduled_date = ? AND b.status <> 'cancelled' AND b.id <> ?",
                              [$teamId, $date, $excludeId ?? 0]);
        $same = (int) setting('buffer_same_zone', '10');
        $out = [];
        foreach ($rows as $r) {
            $buf = (int) $r['neighborhood_id'] === (int) $target['id'] ? $same : max((int) $r['avg_travel_minutes'], (int) $target['avg_travel_minutes']);
            $out[] = [hm_to_min($r['start_time']) - $buf, hm_to_min($r['end_time']) + $buf, (int) $r['neighborhood_id']];
        }
        return $out;
    }

    private function teamSlots(array $team, array $n, int $duration, string $date, ?int $excludeId): array
    {
        $tid = (int) $team['id'];
        if ($this->teamOff($tid, $date) || !$this->membersAvailable($tid, $date)) return [];
        $wh = $this->hours($tid, $date);
        if (!$wh) return [];
        $busy = $this->busyIntervals($tid, $date, $n, $excludeId);
        if (count($busy) >= (int) $team['daily_capacity']) return [];

        $from = hm_to_min($wh['start_time']);
        $to = hm_to_min($wh['end_time']);
        if ($n['slot_start'] !== '') $from = max($from, hm_to_min($n['slot_start']));
        if ($n['slot_end'] !== '') $to = min($to, hm_to_min($n['slot_end']));
        $bs = $wh['break_start'] ? hm_to_min($wh['break_start']) : null;
        $be = $wh['break_end'] ? hm_to_min($wh['break_end']) : null;
        $step = max(5, (int) setting('slot_step_minutes', '30'));
        $earliest = time() + (int) setting('min_lead_hours', '4') * 3600;
        $grouped = (bool) array_filter($busy, fn($b) => $b[2] === (int) $n['id']);

        $slots = [];
        for ($t = $from; $t + $duration <= $to; $t += $step) {
            $e = $t + $duration;
            if (strtotime($date . ' ' . min_to_hm($t)) < $earliest) continue;
            if ($bs !== null && $be !== null && $t < $be && $e > $bs) continue;
            foreach ($busy as [$xs, $xe]) if ($t < $xe && $e > $xs) continue 2;
            $slots[] = ['start' => min_to_hm($t), 'end' => min_to_hm($e), 'team_id' => $tid, 'grouped' => $grouped, 'load' => count($busy)];
        }
        return $slots;
    }

    /** Préférence : regrouper dans une zone déjà visitée, puis équipe la moins chargée, puis plus petit identifiant. */
    private function better(array $a, array $b): bool
    {
        if ($a['grouped'] !== $b['grouped']) return $a['grouped'];
        if ($a['load'] !== $b['load']) return $a['load'] < $b['load'];
        return $a['team_id'] < $b['team_id'];
    }
}
