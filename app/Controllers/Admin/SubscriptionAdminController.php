<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Auth, Database as DB, Env, Request};
use App\Services\SubscriptionService;

/** Abonnements : les demandes à traiter, les abonnements en cours et leurs passages. */
final class SubscriptionAdminController extends Controller
{
    private const PER_PAGE = 25;

    public function index(): void
    {
        $cid = Env::companyId();
        $status = array_key_exists(Request::str('status'), SubscriptionService::STATUSES) ? Request::str('status') : '';
        $q = Request::str('q');
        $where = 's.company_id = ?'; $args = [$cid];
        if ($status !== '') { $where .= ' AND s.status = ?'; $args[] = $status; }
        if ($q !== '') { $where .= ' AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ? OR s.business_name LIKE ?)'; array_push($args, "%$q%", "%$q%", '%' . preg_replace('/\D/', '', $q) . '%', "%$q%"); }
        $page = max(1, Request::int('page', 1));
        $total = (int) DB::val("SELECT COUNT(*) FROM subscriptions s JOIN customers c ON c.id = s.customer_id WHERE $where", $args);
        $rows = DB::all("SELECT s.*, p.name AS plan_name, c.first_name, c.last_name, c.phone, n.name AS hood,
            (SELECT MIN(v.visit_date) FROM subscription_visits v JOIN bookings b ON b.id = v.booking_id WHERE v.subscription_id = s.id AND v.visit_date >= ? AND b.status IN ('pending','confirmed')) AS next_visit,
            (SELECT COUNT(*) FROM subscription_visits v WHERE v.subscription_id = s.id AND v.status = 'unavailable') AS to_plan
            FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id JOIN customers c ON c.id = s.customer_id JOIN neighborhoods n ON n.id = s.neighborhood_id
            WHERE $where ORDER BY (s.status = 'requested') DESC, s.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), array_merge([date('Y-m-d')], $args));
        $counts = array_column(DB::all('SELECT status, COUNT(*) AS n FROM subscriptions WHERE company_id = ? GROUP BY status', [$cid]), 'n', 'status');
        $this->admin('admin/subscriptions', ['title' => 'Abonnements', 'rows' => $rows, 'status' => $status, 'q' => $q, 'counts' => $counts, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / self::PER_PAGE))]);
    }

    public function show(string $id): void
    {
        $svc = new SubscriptionService();
        $s = $svc->find((int) $id) ?? abort(404);
        try { $est = $svc->estimate($s); } catch (\Throwable $e) { $est = null; }
        $visits = DB::all("SELECT v.*, b.id AS bid, b.reference, b.status AS bstatus, b.start_time, b.total FROM subscription_visits v LEFT JOIN bookings b ON b.id = v.booking_id WHERE v.subscription_id = ? ORDER BY v.visit_date DESC LIMIT 60", [(int) $s['id']]);
        $this->admin('admin/subscription', ['title' => 'Abonnement', 's' => $s, 'est' => $est, 'visits' => $visits, 'anchor' => SubscriptionService::anchor($s)]);
    }

    public function activate(string $id): void
    {
        try {
            $r = (new SubscriptionService())->activate((int) $id, Request::all(), Auth::id());
            $this->ok("Abonnement activé : {$r['booked']} passage(s) planifié(s)" . ($r['unavailable'] ? ", {$r['unavailable']} à replanifier (aucun créneau libre)." : '.'));
        } catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect('/admin/abonnements/' . (int) $id);
    }

    public function pause(string $id): void
    {
        $until = Request::str('until') ?: null;
        try { (new SubscriptionService())->pause((int) $id, $until, 'admin', Auth::id()); $this->ok('Abonnement mis en pause : les passages concernés sont annulés.'); }
        catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect('/admin/abonnements/' . (int) $id);
    }

    public function resume(string $id): void
    {
        try { $r = (new SubscriptionService())->resume((int) $id, Auth::id()); $this->ok("Abonnement repris : {$r['booked']} passage(s) planifié(s)."); }
        catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect('/admin/abonnements/' . (int) $id);
    }

    public function cancel(string $id): void
    {
        try { (new SubscriptionService())->cancel((int) $id, Request::str('reason'), Auth::id()); $this->ok('Abonnement résilié : les passages à venir sont annulés.'); }
        catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect('/admin/abonnements/' . (int) $id);
    }

    public function generate(string $id): void
    {
        $r = (new SubscriptionService())->generate((int) $id);
        $this->ok("{$r['booked']} passage(s) ajouté(s)" . ($r['unavailable'] ? ", {$r['unavailable']} sans créneau libre." : '.'));
        redirect('/admin/abonnements/' . (int) $id);
    }

    public function note(string $id): void
    {
        $s = DB::one('SELECT id, admin_notes FROM subscriptions WHERE id = ? AND company_id = ?', [(int) $id, Env::companyId()]) ?? abort(404);
        $line = trim(Request::str('note'));
        if ($line !== '') {
            DB::update('subscriptions', (int) $id, ['admin_notes' => trim(($s['admin_notes'] ?? '') . "\n" . date('d/m H:i') . ' · ' . (Auth::user()['name'] ?? 'Équipe') . ' : ' . mb_substr($line, 0, 500)), 'updated_at' => now()]);
            $this->ok('Note ajoutée.');
        }
        redirect('/admin/abonnements/' . (int) $id);
    }
}
