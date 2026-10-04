<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Audit, Auth, Database as DB, Request, Upload};
use App\Repositories\BookingRepository;
use App\Services\{AssignmentService, BookingService};

/** Interface mobile des agents. Chaque accès à une mission est vérifié côté serveur (propriété de la mission). */
final class AgentController extends Controller
{
    /** Étapes que l'agent peut déclencher lui-même. */
    private const AGENT_FLOW = ['assigned' => 'en_route', 'en_route' => 'in_progress', 'in_progress' => 'completed'];
    private const FLOW_LABEL = ['en_route' => 'Je suis en route', 'in_progress' => "Je commence l'intervention", 'completed' => "J'ai terminé"];

    public function index(): void
    {
        $sid = $this->staffId();
        $as = new AssignmentService();
        $today = date('Y-m-d');
        $this->view('agent/index', [
            'title' => 'Mes missions', 'today' => $today,
            'todays' => $as->missionsFor($sid, $today, $today),
            'upcoming' => $as->missionsFor($sid, date('Y-m-d', strtotime('+1 day')), date('Y-m-d', strtotime('+14 day'))),
            'staff' => DB::one('SELECT s.*, t.name AS team_name FROM staff s LEFT JOIN teams t ON t.id = s.team_id WHERE s.id = ?', [$sid]),
        ], 'layouts/agent');
    }

    public function show(string $id): void
    {
        $b = $this->mission((int) $id);
        $repo = new BookingRepository();
        $next = self::AGENT_FLOW[$b['status']] ?? null;
        $this->view('agent/show', [
            'title' => $b['reference'], 'b' => $b, 'items' => $repo->items((int) $id), 'notes' => $repo->notes((int) $id), 'photos' => $repo->photos((int) $id), 'history' => $repo->history((int) $id),
            'next' => $next, 'next_label' => $next ? self::FLOW_LABEL[$next] : null,
            'maps' => 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($b['address_text'] . ', ' . $b['neighborhood_name'] . ', ' . $b['city_name'] . ', Sénégal'),
        ], 'layouts/agent');
    }

    public function status(string $id): void
    {
        $b = $this->mission((int) $id);
        $to = self::AGENT_FLOW[$b['status']] ?? null;
        if ($to === null || Request::str('to') !== $to) { $this->err("Cette action n'est plus possible : l'état de la mission a changé."); redirect('/agent/mission/' . (int) $id); }
        try { (new BookingService())->changeStatus((int) $id, $to, Auth::id(), Request::str('note') ?: null); $this->ok('Statut mis à jour.'); }
        catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect('/agent/mission/' . (int) $id);
    }

    public function note(string $id): void
    {
        $this->mission((int) $id);
        (new BookingService())->addNote((int) $id, Auth::id(), Request::str('note'));
        $this->ok('Note ajoutée.');
        redirect('/agent/mission/' . (int) $id);
    }

    public function photo(string $id): void
    {
        $b = $this->mission((int) $id);
        if (in_array($b['status'], ['cancelled'], true)) { $this->err('Mission annulée.'); redirect('/agent/mission/' . (int) $id); }
        $kind = Request::str('kind') === 'after' ? 'after' : 'before';
        try {
            // Photos hors du dossier public : servies uniquement par /photo/{id} après contrôle d'accès.
            $name = Upload::image($_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], BASE_PATH . '/storage/uploads/missions');
        } catch (\RuntimeException $e) { $this->err($e->getMessage()); redirect('/agent/mission/' . (int) $id); }
        if (!$name) { $this->err('Choisissez une photo.'); redirect('/agent/mission/' . (int) $id); }
        if ((int) DB::val('SELECT COUNT(*) FROM booking_photos WHERE booking_id = ?', [(int) $id]) >= 20) { @unlink(BASE_PATH . '/storage/uploads/missions/' . $name); $this->err('Maximum 20 photos par mission.'); redirect('/agent/mission/' . (int) $id); }
        DB::insert('booking_photos', ['booking_id' => (int) $id, 'user_id' => Auth::id(), 'kind' => $kind, 'path' => $name, 'created_at' => now()]);
        Audit::log('booking.photo', 'booking', (int) $id, ['kind' => $kind]);
        $this->ok('Photo ajoutée.');
        redirect('/agent/mission/' . (int) $id);
    }

    /** Sert une photo : admin avec droit de lecture, ou agent propriétaire de la mission. */
    public function servePhoto(string $id): void
    {
        $ph = DB::one('SELECT * FROM booking_photos WHERE id = ?', [(int) $id]) ?? abort(404);
        $allowed = Auth::can('bookings.view') || (Auth::can('agent.missions') && (new AssignmentService())->staffCanAccess(Auth::staffId(), (int) $ph['booking_id']));
        if (!$allowed) abort(403);
        $file = BASE_PATH . '/storage/uploads/missions/' . basename($ph['path']);
        if (!is_file($file)) abort(404);
        $info = getimagesize($file);
        header('Content-Type: ' . ($info['mime'] ?? 'application/octet-stream'));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    private function staffId(): int
    {
        $sid = Auth::staffId();
        if (!$sid) abort(403, "Ce compte n'est relié à aucun agent. Contactez l'administration.");
        return $sid;
    }

    private function mission(int $id): array
    {
        $sid = $this->staffId();
        if (!(new AssignmentService())->staffCanAccess($sid, $id)) { Audit::log('access_denied', 'booking', $id, ['agent' => true]); abort(404); }   // 404 : n'indique pas que la mission existe
        return (new BookingRepository())->find($id) ?? abort(404);
    }
}
