<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Database as DB, Env, Request};
use App\Repositories\BookingRepository;

/** Recherche unique : une référence, un nom ou un numéro suffit pour retrouver réservation, client ou prospect. */
final class SearchController extends Controller
{
    public function index(): void
    {
        $q = mb_substr(Request::str('q'), 0, 80);
        $out = ['bookings' => [], 'customers' => [], 'leads' => []];
        if (mb_strlen($q) >= 2) {
            $cid = Env::companyId();
            $digits = preg_replace('/\D/', '', $q) ?? '';
            $like = '%' . $q . '%';
            if (can('bookings.view')) $out['bookings'] = (new BookingRepository())->search(['q' => $q], 10, 0);
            if (can('customers.manage')) {
                $w = '(first_name LIKE ? OR last_name LIKE ? OR (first_name || \' \' || last_name) LIKE ? OR email LIKE ?' . (strlen($digits) >= 3 ? ' OR phone LIKE ?' : '') . ')';
                $w = DB::isSqlite() ? $w : str_replace("(first_name || ' ' || last_name)", "CONCAT(first_name, ' ', last_name)", $w);
                $p = [$cid, $like, $like, $like, $like] + (strlen($digits) >= 3 ? [5 => '%' . $digits . '%'] : []);
                $out['customers'] = DB::all("SELECT id, first_name, last_name, phone, email FROM customers WHERE company_id = ? AND deleted_at IS NULL AND $w ORDER BY id DESC LIMIT 10", array_values($p));
            }
            if (can('crm.manage')) {
                $w = '(name LIKE ? OR email LIKE ?' . (strlen($digits) >= 3 ? ' OR phone LIKE ?' : '') . ')';
                $p = [$cid, $like, $like] + (strlen($digits) >= 3 ? [3 => '%' . $digits . '%'] : []);
                $out['leads'] = DB::all("SELECT id, name, phone, email, status FROM leads WHERE company_id = ? AND deleted_at IS NULL AND $w ORDER BY id DESC LIMIT 10", array_values($p));
            }
            // Une seule réservation trouvée par sa référence exacte : on y va directement.
            if (count($out['bookings']) === 1 && !$out['customers'] && !$out['leads'] && strcasecmp($out['bookings'][0]['reference'], $q) === 0) redirect('/admin/reservations/' . (int) $out['bookings'][0]['id']);
        }
        $this->admin('admin/search', ['title' => 'Recherche', 'q' => $q] + $out);
    }
}
