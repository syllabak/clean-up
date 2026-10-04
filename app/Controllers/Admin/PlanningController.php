<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Database as DB, Env, Request};
use App\Repositories\BookingRepository;
use App\Services\SchedulingService;

final class PlanningController extends Controller
{
    public function index(): void
    {
        $view = Request::str('view') === 'week' ? 'week' : 'day';
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', Request::str('date')) ? Request::str('date') : date('Y-m-d');
        $group = in_array(Request::str('group'), ['team', 'staff', 'neighborhood', 'status'], true) ? Request::str('group') : 'team';
        if ($view === 'week') { $from = date('Y-m-d', strtotime('monday this week', strtotime($date))); $to = date('Y-m-d', strtotime($from . ' +6 day')); }
        else { $from = $to = $date; }

        $f = array_filter(['team_id' => Request::int('team_id'), 'staff_id' => Request::int('staff_id'), 'neighborhood_id' => Request::int('neighborhood_id'), 'status' => Request::str('status')]);
        $bookings = array_values(array_filter((new BookingRepository())->between($from, $to, $f), fn($b) => Request::str('status') !== '' || $b['status'] !== 'cancelled'));
        $sched = new SchedulingService();
        $conflicts = $sched->conflicts($bookings);

        $groups = [];
        foreach ($bookings as $b) {
            [$k, $label] = match ($group) {
                'staff' => [$b['staff_id'] ?: 0, $b['staff_name'] ?: 'Sans agent'],
                'neighborhood' => [$b['neighborhood_id'], $b['neighborhood_name'] . ' (' . $b['commune_name'] . ')'],
                'status' => [$b['status'], status_label($b['status'])],
                default => [$b['team_id'] ?: 0, $b['team_name'] ?: 'Sans équipe'],
            };
            $groups[$k]['label'] = $label;
            $groups[$k]['items'][] = $b;
        }
        uasort($groups, fn($a, $b) => strcmp($a['label'], $b['label']));

        $cid = Env::companyId();
        $this->admin('admin/planning', [
            'title' => 'Planning', 'view' => $view, 'date' => $date, 'from' => $from, 'to' => $to, 'group' => $group, 'groups' => $groups, 'conflicts' => $conflicts,
            'load' => $view === 'day' ? $sched->dayLoad($date) : [], 'f' => $f, 'total' => count($bookings),
            'teams' => DB::all('SELECT id, name FROM teams WHERE company_id = ? AND deleted_at IS NULL ORDER BY name', [$cid]),
            'staff' => DB::all('SELECT id, name FROM staff WHERE company_id = ? AND deleted_at IS NULL ORDER BY name', [$cid]),
            'hoods' => DB::all('SELECT id, name FROM neighborhoods WHERE company_id = ? AND deleted_at IS NULL ORDER BY name', [$cid]),
        ]);
    }
}
