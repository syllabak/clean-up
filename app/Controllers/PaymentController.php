<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Request, Session};
use App\Repositories\BookingRepository;
use App\Services\PaymentService;

/** Paiement en ligne : départ vers PayTech ou PayDunya, pages de retour, notifications des prestataires (sans session, authentifiées par signature). */
final class PaymentController extends Controller
{
    public function pay(string $ref): void
    {
        $ref = strtoupper($ref);
        $b = (new BookingRepository())->findByReference($ref) ?? abort(404);
        $mine = Session::get('last_booking') === $ref || !empty(Session::get('track_ok', [])[$ref]);
        if (!$mine) redirect('/suivi');
        try { redirect_away((new PaymentService())->start($b, Request::str('provider'))); }
        catch (\RuntimeException $e) { $this->err($e->getMessage()); redirect('/suivi/' . $ref . '/' . $b['track_token']); }
    }

    /** Retour du client depuis la page du prestataire. Le statut affiché vient de notre base, jamais de l'adresse de retour. */
    public function returned(string $ref): void
    {
        $ref = strtoupper($ref);
        $b = (new BookingRepository())->findByReference($ref) ?? abort(404);
        $svc = new PaymentService();
        $p = $svc->latest((int) $b['id']);
        $token = Request::str('token');   // PayDunya ajoute ?token=… : on interroge son API, ce qui ne dépend pas de l'adresse
        if ($p && $p['provider'] === 'paydunya' && $p['status'] === 'pending' && $token !== '' && hash_equals((string) $p['token'], $token)) { $svc->reconcile($p); $p = $svc->latest((int) $b['id']); }
        $b = (new BookingRepository())->findByReference($ref);
        $mine = Session::get('last_booking') === $ref || !empty(Session::get('track_ok', [])[$ref]);
        $this->view('public/payment_result', ['title' => 'Paiement', 'noindex' => true, 'b' => $b, 'p' => $p, 'cancelled' => false, 'link' => $mine ? '/suivi/' . $ref . '/' . $b['track_token'] : '/suivi',
            'waiting' => $p && $p['status'] === 'pending' && empty($b['paid_at']) && time() - strtotime($p['created_at'] . ' UTC') < 900]);
    }

    public function cancelled(string $ref): void
    {
        $ref = strtoupper($ref);
        $b = (new BookingRepository())->findByReference($ref) ?? abort(404);
        $mine = Session::get('last_booking') === $ref || !empty(Session::get('track_ok', [])[$ref]);
        $this->view('public/payment_result', ['title' => 'Paiement annulé', 'noindex' => true, 'b' => $b, 'p' => (new PaymentService())->latest((int) $b['id']), 'cancelled' => true, 'waiting' => false, 'link' => $mine ? '/suivi/' . $ref . '/' . $b['track_token'] : '/suivi']);
    }

    // ------------------------------------------------------------------ notifications des prestataires
    public function paytechIpn(): void
    {
        try { $r = (new PaymentService())->paytechIpn(Request::all()); }
        catch (\RuntimeException $e) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'IPN KO'; exit; }
        header('Content-Type: text/plain; charset=utf-8'); echo 'IPN OK'; exit;
    }

    public function paydunyaCallback(): void
    {
        try { $r = (new PaymentService())->paydunyaCallback($_POST ?: Request::all()); }
        catch (\RuntimeException $e) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'KO'; exit; }
        if ($r === 'pending') { http_response_code(503); header('Content-Type: text/plain; charset=utf-8'); echo 'RETRY'; exit; }   // confirmation impossible pour l'instant : le prestataire réessaiera
        header('Content-Type: text/plain; charset=utf-8'); echo 'OK'; exit;
    }
}
