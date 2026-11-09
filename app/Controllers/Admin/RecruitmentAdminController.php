<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\{Controller, RecruitmentController};
use App\Core\{Audit, Auth, Database as DB, Env, Request};

/** Candidatures reçues : une liste, un contact en un geste, un statut en un clic. */
final class RecruitmentAdminController extends Controller
{
    public const STATUSES = ['new' => ['Nouvelle', 'warning'], 'contacted' => ['Contactée', 'primary'], 'test' => ['À l\'essai', 'dark'], 'accepted' => ['Acceptée', 'success'], 'declined' => ['Refusée', 'danger']];
    private const PER_PAGE = 25;

    public function index(): void
    {
        $cid = Env::companyId();
        $status = array_key_exists(Request::str('status'), self::STATUSES) ? Request::str('status') : '';
        $q = Request::str('q');
        $where = 'a.company_id = ?'; $args = [$cid];
        if ($status !== '') { $where .= ' AND a.status = ?'; $args[] = $status; }
        if ($q !== '') { $where .= ' AND (a.name LIKE ? OR a.phone LIKE ?)'; $args[] = "%$q%"; $args[] = '%' . preg_replace('/\D/', '', $q) . '%'; }
        $page = max(1, Request::int('page', 1));
        $total = (int) DB::val("SELECT COUNT(*) FROM job_applications a WHERE $where", $args);
        $rows = DB::all("SELECT a.*, n.name AS hood FROM job_applications a LEFT JOIN neighborhoods n ON n.id = a.neighborhood_id WHERE $where ORDER BY a.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $args);
        $counts = array_column(DB::all('SELECT status, COUNT(*) AS n FROM job_applications WHERE company_id = ? GROUP BY status', [$cid]), 'n', 'status');
        $this->admin('admin/recruitment', ['title' => 'Recrutement', 'rows' => $rows, 'status' => $status, 'q' => $q, 'counts' => $counts, 'total' => $total,
            'page' => $page, 'pages' => max(1, (int) ceil($total / self::PER_PAGE)), 'link' => url('/recrutement'),
            'skills' => RecruitmentController::SKILLS, 'experience' => RecruitmentController::EXPERIENCE, 'availability' => RecruitmentController::AVAILABILITY]);
    }

    public function status(string $id): void
    {
        $to = Request::str('to');
        $a = DB::one('SELECT id, status FROM job_applications WHERE id = ? AND company_id = ?', [(int) $id, Env::companyId()]) ?? abort(404);
        if (!isset(self::STATUSES[$to])) { $this->err('Statut inconnu.'); $this->back('/admin/recrutement'); }
        DB::update('job_applications', (int) $id, ['status' => $to, 'updated_at' => now()]);
        Audit::log('recruitment.status', 'job_application', (int) $id, ['from' => $a['status'], 'to' => $to], Auth::id());
        $this->ok('Candidature : ' . self::STATUSES[$to][0] . '.');
        $this->back('/admin/recrutement');
    }

    public function note(string $id): void
    {
        $a = DB::one('SELECT id, notes FROM job_applications WHERE id = ? AND company_id = ?', [(int) $id, Env::companyId()]) ?? abort(404);
        $line = trim(Request::str('note'));
        if ($line !== '') {
            $who = Auth::user()['name'] ?? 'Équipe';
            DB::update('job_applications', (int) $id, ['notes' => trim(($a['notes'] ?? '') . "\n" . date('d/m H:i') . ' · ' . $who . ' : ' . mb_substr($line, 0, 500)), 'updated_at' => now()]);
            $this->ok('Note ajoutée.');
        }
        $this->back('/admin/recrutement');
    }
}
