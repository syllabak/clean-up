<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Services\BusinessReportService;

/** Tableau de bord de gestion : acquisition par canal, résultat, fidélité. */
final class BusinessController extends Controller
{
    public function index(): void
    {
        [$from, $to] = BusinessReportService::range(Request::str('from'), Request::str('to'));
        $r = new BusinessReportService();
        $this->admin('admin/business', ['title' => 'Gestion', 'from' => $from, 'to' => $to, 'acq' => $r->acquisition($from, $to), 'profit' => $r->profit($from, $to),
            'months' => $r->monthly(6), 'cust' => $r->customers($from, $to)]);
    }
}
