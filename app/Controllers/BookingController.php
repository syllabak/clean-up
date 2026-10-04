<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Audit, Database, Env, Request, Session, ValidationException};
use App\Repositories\BookingRepository;
use App\Services\{AvailabilityService, BookingService, PricingService, SlotUnavailableException};

final class BookingController extends Controller
{
    // ------------------------------------------------------------ Parcours de réservation (page unique, JS)
    public function wizard(): void
    {
        $services = Database::all("SELECT id, name, slug, short_description, billing_unit FROM services WHERE company_id = ? AND active = 1 AND deleted_at IS NULL AND availability_status = 'available' ORDER BY sort", [Env::companyId()]);
        foreach ($services as &$s) $s['from_price'] = ServiceController::fromPrice(Database::one('SELECT * FROM services WHERE id = ?', [$s['id']]));
        $this->view('public/booking', ['title' => 'Réserver une prestation', 'services' => $services, 'preselect' => Request::str('service'), 'currency' => setting('currency', 'FCFA'), 'noindex' => true, 'page_js' => 'booking.js']);
    }

    public function formDef(string $id): void
    {
        $s = Database::one("SELECT * FROM services WHERE id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL", [(int) $id, Env::companyId()]) ?? abort(404);
        $fields = Database::all('SELECT * FROM service_fields WHERE service_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, id', [$s['id']]);
        $out = [];
        foreach ($fields as $f) {
            $out[] = ['key' => $f['field_key'], 'label' => $f['label'], 'type' => $f['type'], 'role' => $f['role'], 'required' => (bool) $f['required'], 'min' => $f['min_value'], 'max' => $f['max_value'], 'help' => $f['help'],
                'choices' => $f['type'] === 'select' ? array_map(fn($c) => ['id' => (int) $c['id'], 'label' => $c['label']],
                    Database::all('SELECT id, label FROM service_field_choices WHERE field_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, id', [$f['id']])) : []];
        }
        json_response([
            'service' => ['id' => (int) $s['id'], 'name' => $s['name'], 'description' => $s['description'], 'conditions' => $s['conditions'], 'billing_unit' => $s['billing_unit']],
            'formulas' => array_map(fn($f) => ['id' => (int) $f['id'], 'name' => $f['name'], 'description' => $f['description'], 'amount' => (int) $f['amount']],
                Database::all('SELECT * FROM service_prices WHERE service_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, id', [$s['id']])),
            'fields' => $out,
            'options' => array_map(fn($o) => ['id' => (int) $o['id'], 'name' => $o['name'], 'description' => $o['description'], 'price' => (int) $o['price']],
                Database::all('SELECT * FROM service_options WHERE service_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, id', [$s['id']])),
        ]);
    }

    public function geo(string $type, string $id): void
    {
        $cid = Env::companyId(); $id = (int) $id;
        $rows = match ($type) {
            'regions' => Database::all('SELECT id, name FROM regions WHERE company_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name', [$cid]),
            'cities' => Database::all('SELECT id, name FROM cities WHERE region_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name', [$id]),
            'communes' => Database::all('SELECT id, name FROM communes WHERE city_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name', [$id]),
            'neighborhoods' => array_map(function ($n) {
                $n['days'] = $n['intervention_days'] === '' ? '' : implode(', ', array_map(fn($d) => mb_strtolower(DAYS_FR[(int) $d] ?? ''), explode(',', $n['intervention_days'])));
                unset($n['intervention_days']);
                return $n;
            }, Database::all('SELECT id, name, travel_fee, intervention_days, conditions FROM neighborhoods WHERE commune_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, name', [$id])),
            default => abort(404),
        };
        json_response($rows);
    }

    public function quote(): void
    {
        $in = Request::json();
        $q = (new PricingService())->quote((int) ($in['service_id'] ?? 0), $in, (int) ($in['neighborhood_id'] ?? 0) ?: null);
        json_response($this->quoteOut($q));
    }

    public function days(): void
    {
        $in = Request::json();
        [$q, $nid] = $this->quoteForSlots($in);
        json_response(['duration' => $q['duration'], 'days' => (new AvailabilityService())->days((int) $q['service']['id'], $nid, $q['duration'])]);
    }

    public function slots(): void
    {
        $in = Request::json();
        [$q, $nid] = $this->quoteForSlots($in);
        $date = (string) ($in['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new ValidationException(['date' => 'Date invalide.']);
        $slots = (new AvailabilityService())->slots((int) $q['service']['id'], $nid, $q['duration'], $date);
        json_response(['duration' => $q['duration'], 'slots' => array_map(fn($s) => ['start' => $s['start'], 'end' => $s['end'], 'recommended' => $s['grouped']], $slots)]);
    }

    public function store(): void
    {
        $in = Request::json();
        if (!empty($in['website'])) json_response(['error' => 'Requête refusée.'], 400);     // piège à robots
        $in['source'] = 'web';
        try {
            $b = (new BookingService())->create($in);
        } catch (SlotUnavailableException $e) {
            json_response(['error' => $e->getMessage(), 'code' => 'slot_taken'], 409);
        }
        Session::set('last_booking', $b['reference']);
        Session::set('track_ok', array_merge(Session::get('track_ok', []), [$b['reference'] => true]));
        json_response(['reference' => $b['reference'], 'redirect' => with_base('/reservation/confirmation/' . $b['reference'])], 201);
    }

    public function confirmation(string $ref): void
    {
        $b = (new BookingRepository())->findByReference($ref);
        if (!$b || Session::get('last_booking') !== $ref) redirect('/suivi');
        $this->view('public/confirmation', ['title' => 'Réservation enregistrée', 'b' => $b, 'items' => (new BookingRepository())->items((int) $b['id']), 'noindex' => true]);
    }

    // ------------------------------------------------------------ Suivi par le client (référence + téléphone)
    public function trackForm(): void { $this->view('public/track', ['title' => 'Suivre ma réservation', 'noindex' => true]); }

    public function track(): void
    {
        $ref = strtoupper(Request::str('reference'));
        $b = (new BookingRepository())->findByReference($ref);
        // Message identique que la référence ou le numéro soit faux : on ne révèle pas quelles références existent.
        if (!$b || !hash_equals(normalize_phone($b['customer_phone']), normalize_phone(Request::str('phone')))) {
            Audit::log('track_failed', 'booking', null, ['ref' => mb_substr($ref, 0, 30)], null);
            $this->err('Référence ou numéro de téléphone incorrect.');
            $_SESSION['_old'] = ['reference' => $ref];
            redirect('/suivi');
        }
        Session::set('track_ok', array_merge(Session::get('track_ok', []), [$ref => true]));
        redirect('/suivi/' . $ref);
    }

    public function trackShow(string $ref): void
    {
        $b = $this->owned($ref);
        $repo = new BookingRepository();
        $this->view('public/track_show', ['title' => 'Réservation ' . $ref, 'b' => $b, 'items' => $repo->items((int) $b['id']), 'history' => $repo->history((int) $b['id']),
            'can_cancel' => $this->canCancel($b), 'noindex' => true]);
    }

    public function trackCancel(string $ref): void
    {
        $b = $this->owned($ref);
        if (!$this->canCancel($b)) { $this->err("Cette réservation ne peut plus être annulée en ligne. Appelez-nous : " . setting('company_phone')); redirect('/suivi/' . $ref); }
        (new BookingService())->cancel((int) $b['id'], null, 'Annulée par le client');
        $this->ok('Votre réservation a été annulée.');
        redirect('/suivi/' . $ref);
    }

    // ------------------------------------------------------------ internes
    private function owned(string $ref): array
    {
        if (empty(Session::get('track_ok', [])[$ref])) redirect('/suivi');
        return (new BookingRepository())->findByReference($ref) ?? abort(404);
    }

    private function canCancel(array $b): bool
    {
        if (!in_array($b['status'], ['pending', 'confirmed'], true)) return false;
        return strtotime($b['scheduled_date'] . ' ' . $b['start_time']) - time() >= (int) setting('cancel_hours_before', '12') * 3600;
    }

    private function quoteForSlots(array $in): array
    {
        $nid = (int) ($in['neighborhood_id'] ?? 0);
        if (!$nid) throw new ValidationException(['neighborhood_id' => 'Choisissez votre quartier.']);
        return [(new PricingService())->quote((int) ($in['service_id'] ?? 0), $in, $nid), $nid];
    }

    private function quoteOut(array $q): array
    {
        return ['subtotal' => $q['subtotal'], 'travel_fee' => $q['travel_fee'], 'total' => $q['total'], 'duration' => $q['duration'], 'details' => $q['details'],
            'quantity' => $q['quantity'], 'unit_price' => $q['unit_price'], 'formula' => $q['formula']['name'] ?? null, 'service' => $q['service']['name'], 'billing_unit' => $q['billing_unit']];
    }
}
