<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Database as DB, Env};
use App\Services\SchedulingService;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $cid = Env::companyId();
        $today = date('Y-m-d'); $monthStart = date('Y-m-01');
        $c = fn(string $where, array $p = []) => (int) DB::val("SELECT COUNT(*) FROM bookings WHERE company_id = ? AND $where", array_merge([$cid], $p));
        $stats = [
            'today' => $c("scheduled_date = ? AND status <> 'cancelled'", [$today]),
            'pending' => $c("status = 'pending'"),
            'week' => $c("scheduled_date >= ? AND scheduled_date <= ? AND status <> 'cancelled'", [$today, date('Y-m-d', strtotime('+6 day'))]),
            'declared_month' => (int) DB::val("SELECT COALESCE(SUM(declared_amount),0) FROM bookings WHERE company_id = ? AND status = 'completed' AND scheduled_date >= ?", [$cid, $monthStart]),
            'estimated_month' => (int) DB::val("SELECT COALESCE(SUM(total),0) FROM bookings WHERE company_id = ? AND status <> 'cancelled' AND scheduled_date >= ? AND scheduled_date < ?", [$cid, $monthStart, date('Y-m-01', strtotime('+1 month'))]),
            'failed_notifs' => (int) DB::val("SELECT COUNT(*) FROM notification_logs WHERE company_id = ? AND status = 'failed'", [$cid]),
            'messages' => (int) DB::val('SELECT COUNT(*) FROM contact_messages WHERE company_id = ? AND handled = 0', [$cid]),
        ];
        $byStatus = array_column(DB::all("SELECT status, COUNT(*) AS n FROM bookings WHERE company_id = ? AND scheduled_date >= ? GROUP BY status", [$cid, $monthStart]), 'n', 'status');
        $repo = new \App\Repositories\BookingRepository();
        $unassigned = array_values(array_filter($repo->between($today, date('Y-m-d', strtotime('+3 day'))), fn($b) => in_array($b['status'], ['pending', 'confirmed'], true)));
        $this->admin('admin/dashboard', ['title' => 'Tableau de bord', 'stats' => $stats, 'by_status' => $byStatus, 'unassigned' => $unassigned,
            'load' => (new SchedulingService())->dayLoad($today), 'today' => $today, 'revenue_on' => setting('revenue_tracking_enabled', '1') === '1']);
    }
}
