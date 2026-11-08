<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Auth, Database as DB, Env, Request, ValidationException};
use App\Services\{CatalogService, PricingService};

/** Fiche service : tout le tarif d'un service sur une seule page, simulateur de prix, duplication. */
final class ServiceAdminController extends Controller
{
    public function show(string $id): void
    {
        $s = $this->service($id);
        $sid = (int) $s['id'];
        $fields = DB::all('SELECT * FROM service_fields WHERE service_id = ? AND deleted_at IS NULL ORDER BY sort, id', [$sid]);
        foreach ($fields as &$f) $f['choices'] = DB::all('SELECT * FROM service_field_choices WHERE field_id = ? AND deleted_at IS NULL ORDER BY sort, id', [(int) $f['id']]);
        unset($f);
        $this->admin('admin/service', [
            'title' => $s['name'], 's' => $s, 'checks' => (new CatalogService())->readiness($sid), 'fields' => $fields,
            'formulas' => DB::all('SELECT * FROM service_prices WHERE service_id = ? AND deleted_at IS NULL ORDER BY sort, id', [$sid]),
            'options' => DB::all('SELECT * FROM service_options WHERE service_id = ? AND deleted_at IS NULL ORDER BY sort, id', [$sid]),
            'images' => DB::all('SELECT * FROM service_images WHERE service_id = ? ORDER BY sort, id', [$sid]),
            'category' => $s['category_id'] ? DB::val('SELECT name FROM service_categories WHERE id = ?', [$s['category_id']]) : null,
            'hoods' => DB::all('SELECT id, name, travel_fee FROM neighborhoods WHERE company_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name', [Env::companyId()]),
            'page_js' => 'admin-service.js',
        ]);
    }

    public function duplicate(string $id): void
    {
        $s = $this->service($id);
        try { $new = (new CatalogService())->duplicate((int) $s['id'], Auth::id()); }
        catch (\Throwable $e) { error_log('Duplication : ' . $e->getMessage()); $this->err('La duplication a échoué.'); redirect('/admin/services/' . (int) $s['id']); }
        $this->ok('Service dupliqué. La copie est inactive : relisez-la puis activez-la.');
        redirect('/admin/services/' . $new);
    }

    /** Simulateur : le même calcul que le site public, sur un service même inactif. */
    public function simulate(string $id): void
    {
        $s = $this->service($id);
        $in = Request::json();
        try {
            $q = (new PricingService())->quote((int) $s['id'], $in, ((int) ($in['neighborhood_id'] ?? 0)) ?: null, true);
        } catch (ValidationException $e) {
            json_response(['error' => implode(' ', $e->errors), 'errors' => $e->errors], 422);
        }
        json_response(['details' => $q['details'], 'subtotal' => $q['subtotal'], 'travel_fee' => $q['travel_fee'], 'total' => $q['total'], 'duration' => $q['duration'], 'quantity' => $q['quantity'], 'unit_price' => $q['unit_price'], 'billing_unit' => $q['billing_unit']]);
    }

    private function service(string $id): array
    {
        return DB::one('SELECT * FROM services WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [(int) $id, Env::companyId()]) ?? abort(404);
    }
}
