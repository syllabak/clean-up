<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Database, Env};

/** Lecture du planning : charge par équipe, détection de conflits, regroupement par zone, tournées. */
final class SchedulingService
{
    /** Charge de chaque équipe sur une journée : fenêtre de travail, minutes occupées/libres, capacité. */
    public function dayLoad(string $date): array
    {
        $dow = (int) date('N', strtotime($date));
        $closed = (bool) Database::val('SELECT id FROM time_off WHERE company_id = ? AND team_id IS NULL AND staff_id IS NULL AND deleted_at IS NULL AND start_date <= ? AND end_date >= ?', [Env::companyId(), $date, $date]);
        $out = [];
        foreach (Database::all("SELECT * FROM teams WHERE company_id = ? AND deleted_at IS NULL AND status = 'active' ORDER BY name", [Env::companyId()]) as $t) {
            $wh = Database::one('SELECT * FROM working_hours WHERE team_id = ? AND day_of_week = ?', [$t['id'], $dow]);
            $off = (bool) Database::val('SELECT id FROM time_off WHERE team_id = ? AND deleted_at IS NULL AND start_date <= ? AND end_date >= ?', [$t['id'], $date, $date]);
            $bk = Database::all("SELECT duration_minutes FROM bookings WHERE team_id = ? AND scheduled_date = ? AND status <> 'cancelled'", [$t['id'], $date]);
            $window = $wh ? hm_to_min($wh['end_time']) - hm_to_min($wh['start_time']) - ($wh['break_start'] ? hm_to_min($wh['break_end']) - hm_to_min($wh['break_start']) : 0) : 0;
            $busy = array_sum(array_column($bk, 'duration_minutes'));
            $out[] = ['team' => $t, 'hours' => $wh, 'off' => $off || $closed, 'count' => count($bk), 'capacity' => (int) $t['daily_capacity'],
                      'window' => $window, 'busy' => $busy, 'free' => max(0, $window - $busy)];
        }
        return $out;
    }

    /**
     * Conflits dans une liste de réservations déjà triée par date/heure :
     * chevauchement strict, ou trajet insuffisant entre deux interventions d'une même équipe.
     * @return array<int,string[]> id de réservation => messages
     */
    public function conflicts(array $bookings): array
    {
        $byTeamDay = [];
        foreach ($bookings as $b) if ($b['team_id'] && $b['status'] !== 'cancelled') $byTeamDay[$b['team_id'] . '|' . $b['scheduled_date']][] = $b;
        $out = [];
        $same = (int) setting('buffer_same_zone', '10');
        foreach ($byTeamDay as $list) {
            usort($list, fn($a, $b) => $a['start_time'] <=> $b['start_time']);
            for ($i = 1; $i < count($list); $i++) {
                $p = $list[$i - 1]; $c = $list[$i];
                $gap = hm_to_min($c['start_time']) - hm_to_min($p['end_time']);
                $need = (int) $p['neighborhood_id'] === (int) $c['neighborhood_id'] ? $same : max((int) $p['avg_travel_minutes'], (int) $c['avg_travel_minutes']);
                if ($gap < 0) { $out[$c['id']][] = "Chevauche {$p['reference']}"; $out[$p['id']][] = "Chevauche {$c['reference']}"; }
                elseif ($gap < $need) { $out[$c['id']][] = "Trajet court après {$p['reference']} ({$gap} min pour {$need} min)"; }
            }
        }
        return $out;
    }

    /** Nombre de changements de quartier dans la journée d'une équipe (indicateur de dispersion). */
    public function zoneJumps(array $teamDayBookings): int
    {
        usort($teamDayBookings, fn($a, $b) => $a['start_time'] <=> $b['start_time']);
        $j = 0;
        for ($i = 1; $i < count($teamDayBookings); $i++) if ((int) $teamDayBookings[$i]['neighborhood_id'] !== (int) $teamDayBookings[$i - 1]['neighborhood_id']) $j++;
        return $j;
    }

    /** Enregistre (ou remplace) la feuille de route d'une équipe pour un jour : arrêts dans l'ordre chronologique. */
    public function buildRoutePlan(int $teamId, string $date): int
    {
        return (int) Database::transaction(function () use ($teamId, $date) {
            foreach (Database::all('SELECT id FROM route_plans WHERE team_id = ? AND plan_date = ?', [$teamId, $date]) as $old) {
                Database::exec('DELETE FROM route_stops WHERE route_plan_id = ?', [$old['id']]);
                Database::exec('DELETE FROM route_plans WHERE id = ?', [$old['id']]);
            }
            $plan = Database::insert('route_plans', ['company_id' => Env::companyId(), 'team_id' => $teamId, 'plan_date' => $date, 'created_at' => now()]);
            $rows = Database::all("SELECT id, start_time FROM bookings WHERE team_id = ? AND scheduled_date = ? AND status <> 'cancelled' ORDER BY start_time", [$teamId, $date]);
            foreach ($rows as $i => $r) Database::insert('route_stops', ['route_plan_id' => $plan, 'booking_id' => $r['id'], 'position' => $i + 1, 'eta' => $r['start_time']]);
            return $plan;
        });
    }
}
