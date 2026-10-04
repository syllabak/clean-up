<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Database as DB, Env, Request};
use App\Services\NotificationService;

final class NotificationAdminController extends Controller
{
    public function index(): void
    {
        $status = in_array(Request::str('status'), ['sent', 'failed', 'pending'], true) ? Request::str('status') : '';
        $page = max(1, Request::int('page', 1));
        $w = 'l.company_id = ?'; $p = [Env::companyId()];
        if ($status) { $w .= ' AND l.status = ?'; $p[] = $status; }
        $total = (int) DB::val("SELECT COUNT(*) FROM notification_logs l WHERE $w", $p);
        $rows = DB::all("SELECT l.*, b.reference FROM notification_logs l LEFT JOIN bookings b ON b.id = l.booking_id WHERE $w ORDER BY l.id DESC LIMIT 40 OFFSET " . (($page - 1) * 40), $p);
        $this->admin('admin/notifications', ['title' => 'Journal des notifications', 'rows' => $rows, 'status' => $status, 'page' => $page, 'pages' => max(1, (int) ceil($total / 40)), 'max' => (int) setting('notif_max_attempts', '4')]);
    }

    public function retry(string $id): void
    {
        $ok = (new NotificationService())->retryNow((int) $id, true);
        $ok ? $this->ok('Envoi réussi.') : $this->err("L'envoi a échoué à nouveau (voir le détail de l'erreur).");
        $this->back('/admin/notifications');
    }

    public function retryAll(): void
    {
        $svc = new NotificationService(); $ok = 0; $ko = 0;
        foreach (DB::all("SELECT id FROM notification_logs WHERE company_id = ? AND status = 'failed' ORDER BY id LIMIT 100", [Env::companyId()]) as $r) $svc->retryNow((int) $r['id'], true) ? $ok++ : $ko++;
        $this->ok("$ok envoi(s) réussi(s), $ko encore en échec.");
        $this->back('/admin/notifications');
    }
}
