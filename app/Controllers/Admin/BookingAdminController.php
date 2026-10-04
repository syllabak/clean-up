<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Audit, Auth, Database as DB, Env, Request, ValidationException};
use App\Repositories\BookingRepository;
use App\Services\{AssignmentService, AvailabilityService, BookingService, SlotUnavailableException};

final class BookingAdminController extends Controller
{
    private const PER_PAGE = 25;

    public function index(): void
    {
        $repo = new BookingRepository();
        $f = array_filter([
            'status' => Request::str('status'), 'team_id' => Request::int('team_id'), 'neighborhood_id' => Request::int('neighborhood_id'), 'service_id' => Request::int('service_id'),
            'from' => Request::str('from'), 'to' => Request::str('to'), 'q' => Request::str('q'),
        ]);
        $page = max(1, Request::int('page', 1));
        $total = $repo->count($f);
        $cid = Env::companyId();
        $this->admin('admin/bookings', [
            'title' => 'Réservations', 'rows' => $repo->search($f, self::PER_PAGE, ($page - 1) * self::PER_PAGE), 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / self::PER_PAGE)), 'f' => $f,
            'teams' => DB::all('SELECT id, name FROM teams WHERE company_id = ? AND deleted_at IS NULL ORDER BY name', [$cid]),
            'services' => DB::all('SELECT id, name FROM services WHERE company_id = ? AND deleted_at IS NULL ORDER BY name', [$cid]),
            'hoods' => DB::all('SELECT id, name FROM neighborhoods WHERE company_id = ? AND deleted_at IS NULL ORDER BY name', [$cid]),
        ]);
    }

    public function show(string $id): void
    {
        $repo = new BookingRepository();
        $b = $repo->find((int) $id) ?? abort(404);
        $cid = Env::companyId();
        $this->admin('admin/booking', [
            'title' => 'Réservation ' . $b['reference'], 'page_js' => 'admin-booking.js', 'b' => $b, 'items' => $repo->items((int) $id), 'history' => $repo->history((int) $id), 'notes' => $repo->notes((int) $id), 'photos' => $repo->photos((int) $id),
            'next' => BookingService::TRANSITIONS[$b['status']] ?? [],
            'teams' => DB::all("SELECT id, name FROM teams WHERE company_id = ? AND deleted_at IS NULL AND status = 'active' ORDER BY name", [$cid]),
            'staff' => DB::all("SELECT s.id, s.name, s.team_id, t.name AS team_name FROM staff s LEFT JOIN teams t ON t.id = s.team_id WHERE s.company_id = ? AND s.deleted_at IS NULL AND s.status = 'active' ORDER BY t.name, s.name", [$cid]),
            'logs' => DB::all('SELECT * FROM notification_logs WHERE booking_id = ? ORDER BY id DESC', [(int) $id]),
            'revenue_on' => setting('revenue_tracking_enabled', '1') === '1',
        ]);
    }

    public function status(string $id): void
    {
        try {
            (new BookingService())->changeStatus((int) $id, Request::str('to'), Auth::id(), Request::str('note') ?: null);
            $this->ok('Statut mis à jour.');
        } catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect('/admin/reservations/' . (int) $id);
    }

    public function assign(string $id): void
    {
        try {
            $w = (new AssignmentService())->assign((int) $id, Request::int('team_id') ?: null, Request::int('staff_id') ?: null, Auth::id(), Request::str('force') === '1');
            $this->ok('Affectation enregistrée.' . ($w ? ' Avertissements ignorés : ' . implode(' ', $w) : ''));
        } catch (\RuntimeException $e) { $this->err($e->getMessage() . ' (cochez « Forcer » pour passer outre.)'); }
        redirect('/admin/reservations/' . (int) $id);
    }

    public function reschedule(string $id): void
    {
        try {
            (new BookingService())->reschedule((int) $id, Request::str('date'), Request::str('start'), Auth::id());
            $this->ok('Réservation déplacée. Le client est prévenu.');
        } catch (SlotUnavailableException | ValidationException | \RuntimeException $e) { $this->err($e->getMessage()); }
        redirect('/admin/reservations/' . (int) $id);
    }

    public function note(string $id): void
    {
        (new BookingService())->addNote((int) $id, Auth::id(), Request::str('note'));
        $this->ok('Note ajoutée.');
        redirect('/admin/reservations/' . (int) $id);
    }

    /** Chiffre d'affaires saisi à la main : aucun paiement n'est géré par l'application. */
    public function amount(string $id): void
    {
        $raw = Request::str('declared_amount');
        if ($raw !== '' && !preg_match('/^\d{1,9}$/', $raw)) { $this->err('Montant invalide.'); redirect('/admin/reservations/' . (int) $id); }
        DB::update('bookings', (int) $id, ['declared_amount' => $raw === '' ? null : (int) $raw, 'payment_note' => mb_substr(Request::str('payment_note'), 0, 255) ?: null, 'updated_at' => now()]);
        Audit::log('booking.amount', 'booking', (int) $id, ['amount' => $raw]);
        $this->ok('Montant enregistré.');
        redirect('/admin/reservations/' . (int) $id);
    }

    /** Créneaux disponibles pour déplacer une réservation (JSON). */
    public function slots(): void
    {
        $b = (new BookingRepository())->find(Request::int('booking_id')) ?? abort(404);
        $date = Request::str('date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_response(['slots' => []]);
        $slots = (new AvailabilityService())->slots((int) $b['service_id'], (int) $b['neighborhood_id'], (int) $b['duration_minutes'], $date, (int) $b['id']);
        json_response(['slots' => array_map(fn($s) => ['start' => $s['start'], 'end' => $s['end'], 'recommended' => $s['grouped']], $slots)]);
    }
}
