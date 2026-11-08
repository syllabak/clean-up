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
        if (can('crm.manage')) {
            $stats['leads_new'] = (int) DB::val("SELECT COUNT(*) FROM leads WHERE company_id = ? AND deleted_at IS NULL AND status = 'new'", [$cid]);
            $stats['leads_due'] = (int) DB::val("SELECT COUNT(*) FROM leads WHERE company_id = ? AND deleted_at IS NULL AND status IN ('new','contacted','quoted') AND next_follow_up <= ?", [$cid, $today]);
        }
        $byStatus = array_column(DB::all("SELECT status, COUNT(*) AS n FROM bookings WHERE company_id = ? AND scheduled_date >= ? GROUP BY status", [$cid, $monthStart]), 'n', 'status');
        $repo = new \App\Repositories\BookingRepository();
        $unassigned = array_values(array_filter($repo->between($today, date('Y-m-d', strtotime('+3 day'))), fn($b) => in_array($b['status'], ['pending', 'confirmed'], true) && !DB::val('SELECT 1 FROM booking_assignments WHERE booking_id = ? AND active = 1', [(int) $b['id']])));
        $todo = [];
        $add = function (int $n, string $perm, string $singular, string $plural, string $href, string $cta) use (&$todo) { if ($n > 0 && can($perm)) $todo[] = [$n, $n > 1 ? $plural : $singular, $href, $cta]; };
        $add($stats['pending'], 'bookings.view', 'réservation à confirmer', 'réservations à confirmer', '/admin/reservations?status=pending', 'Confirmer');
        $add(count($unassigned), 'bookings.manage', 'intervention à affecter (3 jours)', 'interventions à affecter (3 jours)', '#affecter', 'Affecter');
        $add($stats['leads_new'] ?? 0, 'crm.manage', 'nouveau prospect à contacter', 'nouveaux prospects à contacter', '/admin/crm', 'Contacter');
        $add($stats['leads_due'] ?? 0, 'crm.manage', 'prospect à relancer', 'prospects à relancer', '/admin/crm', 'Relancer');
        $add($stats['messages'], 'cms.manage', 'message du site à traiter', 'messages du site à traiter', '/admin/crud/contact_messages', 'Lire');
        $add($stats['failed_notifs'], 'notifications.manage', 'envoi en échec', 'envois en échec', '/admin/notifications?status=failed', 'Relancer');
        $steps = can('settings.manage') ? \App\Services\OnboardingService::steps() : [];
        $todayList = array_values(array_filter($repo->between($today, $today), fn($b) => $b['status'] !== 'cancelled'));
        $this->admin('admin/dashboard', ['title' => 'Tableau de bord', 'todo' => $todo, 'steps' => $steps, 'today_list' => $todayList, 'stats' => $stats, 'by_status' => $byStatus, 'unassigned' => $unassigned,
            'load' => (new SchedulingService())->dayLoad($today), 'today' => $today, 'revenue_on' => setting('revenue_tracking_enabled', '1') === '1']);
    }
}
