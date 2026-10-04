<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Database as DB, Env, Request};

final class AuditController extends Controller
{
    public function index(): void
    {
        $page = max(1, Request::int('page', 1));
        $total = (int) DB::val('SELECT COUNT(*) FROM audit_logs WHERE company_id = ?', [Env::companyId()]);
        $rows = DB::all('SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.company_id = ? ORDER BY a.id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50), [Env::companyId()]);
        $this->admin('admin/audit', ['title' => "Journal d'audit", 'rows' => $rows, 'page' => $page, 'pages' => max(1, (int) ceil($total / 50))]);
    }
}
