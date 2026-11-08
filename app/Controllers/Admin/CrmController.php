<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Auth, Database as DB, Env, Request};
use App\Services\CrmService;

/** CRM : pipeline de prospects, fiche prospect, fiche client à 360°. */
final class CrmController extends Controller
{
    public function pipeline(): void
    {
        $cid = Env::companyId();
        $channel = Request::int('channel_id');
        $where = 'l.company_id = ? AND l.deleted_at IS NULL' . ($channel ? ' AND l.channel_id = ' . $channel : '');
        $rows = DB::all("SELECT l.*, ch.name AS channel_name, s.name AS service_name FROM leads l LEFT JOIN acquisition_channels ch ON ch.id = l.channel_id LEFT JOIN services s ON s.id = l.service_id
            WHERE $where ORDER BY l.next_follow_up IS NULL, l.next_follow_up, l.id DESC", [$cid]);
        $cols = array_fill_keys(array_keys(CrmService::LEAD_STATUSES), []);
        foreach ($rows as $r) $cols[$r['status']][] = $r;
        foreach (['won', 'lost'] as $s) $cols[$s] = array_slice($cols[$s], 0, 10);
        $today = date('Y-m-d');
        $open = array_merge($cols['new'], $cols['contacted'], $cols['quoted']);
        $since = date('Y-m-d H:i:s', strtotime('-30 day'));
        $created30 = (int) DB::val('SELECT COUNT(*) FROM leads WHERE company_id = ? AND deleted_at IS NULL AND created_at >= ?', [$cid, $since]);
        $won30 = (int) DB::val("SELECT COUNT(*) FROM leads WHERE company_id = ? AND deleted_at IS NULL AND status = 'won' AND created_at >= ?", [$cid, $since]);
        $this->admin('admin/crm_pipeline', ['title' => 'Prospects', 'cols' => $cols, 'today' => $today, 'channel' => $channel,
            'channels' => DB::all('SELECT id, name FROM acquisition_channels WHERE company_id = ? AND deleted_at IS NULL ORDER BY sort, name', [$cid]),
            'kpi' => ['open' => count($open), 'value' => array_sum(array_column($open, 'estimated_value')), 'due' => count(array_filter($open, fn($l) => $l['next_follow_up'] && $l['next_follow_up'] <= $today)),
                'rate' => $created30 ? (int) round($won30 / $created30 * 100) : null]]);
    }

    public function lead(string $id): void
    {
        $l = DB::one('SELECT l.*, ch.name AS channel_name, s.name AS service_name, u.name AS user_name FROM leads l LEFT JOIN acquisition_channels ch ON ch.id = l.channel_id
            LEFT JOIN services s ON s.id = l.service_id LEFT JOIN users u ON u.id = l.assigned_user_id WHERE l.id = ? AND l.company_id = ? AND l.deleted_at IS NULL', [(int) $id, Env::companyId()]) ?? abort(404);
        $this->admin('admin/lead', ['title' => 'Prospect ' . $l['name'], 'l' => $l, 'interactions' => $this->interactions('lead_id', (int) $id)]);
    }

    public function leadStatus(string $id): void
    {
        $crm = new CrmService();
        try { $crm->setStatus((int) $this->mustLead($id)['id'], Request::str('status'), Auth::id(), Request::str('lost_reason')); $this->ok('Statut mis à jour.'); }
        catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect('/admin/crm/prospects/' . (int) $id);
    }

    public function leadFollowUp(string $id): void
    {
        $d = Request::str('next_follow_up');
        $ok = $d === '' || (\DateTime::createFromFormat('Y-m-d', $d) && \DateTime::createFromFormat('Y-m-d', $d)->format('Y-m-d') === $d);
        if (!$ok) { $this->err('Date invalide.'); redirect('/admin/crm/prospects/' . (int) $id); }
        DB::update('leads', (int) $this->mustLead($id)['id'], ['next_follow_up' => $d ?: null, 'updated_at' => now()]);
        $this->ok('Prochaine relance enregistrée.');
        redirect('/admin/crm/prospects/' . (int) $id);
    }

    public function leadConvert(string $id): void
    {
        try { $cid = (new CrmService())->convert((int) $this->mustLead($id)['id'], Auth::id()); $this->ok('Client créé ou retrouvé.'); redirect('/admin/clients/' . $cid); }
        catch (\RuntimeException $e) { $this->err($e->getMessage()); redirect('/admin/crm/prospects/' . (int) $id); }
    }

    public function leadInteraction(string $id): void
    {
        $l = $this->mustLead($id);
        (new CrmService())->addInteraction($l['customer_id'] ? (int) $l['customer_id'] : null, (int) $l['id'], Auth::id(), Request::str('type'), Request::str('summary'));
        $this->ok('Échange enregistré.');
        redirect('/admin/crm/prospects/' . (int) $id);
    }

    // ---------------------------------------------------------------- Fiche client
    public function customer(string $id): void
    {
        $cid = Env::companyId();
        $c = DB::one('SELECT c.*, ch.name AS channel_name FROM customers c LEFT JOIN acquisition_channels ch ON ch.id = c.channel_id WHERE c.id = ? AND c.company_id = ? AND c.deleted_at IS NULL', [(int) $id, $cid]) ?? abort(404);
        $bookings = DB::all('SELECT b.id, b.reference, b.scheduled_date, b.start_time, b.status, b.total, b.declared_amount, s.name AS service_name, ch.name AS channel_name FROM bookings b
            JOIN services s ON s.id = b.service_id LEFT JOIN acquisition_channels ch ON ch.id = b.channel_id WHERE b.customer_id = ? ORDER BY b.scheduled_date DESC, b.start_time DESC', [(int) $id]);
        $done = array_filter($bookings, fn($b) => $b['status'] === 'completed');
        $revenue = (int) array_sum(array_map(fn($b) => $b['declared_amount'] ?? $b['total'], $done));
        $this->admin('admin/customer', ['title' => 'Client ' . $c['first_name'] . ' ' . $c['last_name'], 'c' => $c, 'bookings' => $bookings, 'interactions' => $this->customerInteractions((int) $id),
            'addresses' => DB::all('SELECT a.*, n.name AS neighborhood_name FROM customer_addresses a LEFT JOIN neighborhoods n ON n.id = a.neighborhood_id WHERE a.customer_id = ? AND a.deleted_at IS NULL', [(int) $id]),
            'leads' => DB::all('SELECT id, status, created_at FROM leads WHERE customer_id = ? AND deleted_at IS NULL ORDER BY id DESC', [(int) $id]),
            'stats' => ['count' => count($bookings), 'done' => count($done), 'revenue' => $revenue, 'avg' => $done ? (int) round($revenue / count($done)) : 0, 'segment' => CrmService::segment(count($done), $revenue)],
            'channels' => DB::all('SELECT id, name FROM acquisition_channels WHERE company_id = ? AND deleted_at IS NULL ORDER BY sort, name', [$cid])]);
    }

    public function customerInteraction(string $id): void
    {
        $c = $this->mustCustomer($id);
        (new CrmService())->addInteraction((int) $c['id'], null, Auth::id(), Request::str('type'), Request::str('summary'));
        $this->ok('Échange enregistré.');
        redirect('/admin/clients/' . (int) $id);
    }

    public function customerChannel(string $id): void
    {
        $c = $this->mustCustomer($id);
        $ch = (new CrmService())->channelId(Request::int('channel_id'));
        DB::update('customers', (int) $c['id'], \App\Services\ConsentService::stamp(['channel_id' => $ch, 'marketing_consent' => Request::str('marketing_consent') === '1' ? 1 : 0], $c));
        $this->ok('Fiche mise à jour.');
        redirect('/admin/clients/' . (int) $id);
    }

    /** Échanges du client, y compris ceux saisis quand il n'était encore qu'un prospect. */
    private function customerInteractions(int $id): array
    {
        return DB::all('SELECT i.*, u.name AS user_name FROM interactions i LEFT JOIN users u ON u.id = i.user_id WHERE i.company_id = ? AND (i.customer_id = ? OR i.lead_id IN (SELECT id FROM leads WHERE customer_id = ?))
            ORDER BY i.id DESC LIMIT 100', [Env::companyId(), $id, $id]);
    }

    private function interactions(string $col, int $id): array
    {
        return DB::all("SELECT i.*, u.name AS user_name FROM interactions i LEFT JOIN users u ON u.id = i.user_id WHERE i.company_id = ? AND i.$col = ? ORDER BY i.id DESC LIMIT 100", [Env::companyId(), $id]);
    }

    private function mustLead(string $id): array { return (new CrmService())->lead((int) $id) ?? abort(404); }

    private function mustCustomer(string $id): array
    {
        return DB::one('SELECT id FROM customers WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [(int) $id, Env::companyId()]) ?? abort(404);
    }
}
