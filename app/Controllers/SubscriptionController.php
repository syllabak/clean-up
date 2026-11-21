<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Database, Env, Request, Session, ValidationException};
use App\Services\SubscriptionService;

/** Abonnements côté client : offres, demande, puis espace personnel (lien secret) pour suivre, mettre en pause ou résilier. */
final class SubscriptionController extends Controller
{
    public function index(): void
    {
        $plans = Database::all("SELECT p.*, s.name AS service_name, s.slug AS service_slug FROM subscription_plans p JOIN services s ON s.id = p.service_id
            WHERE p.company_id = ? AND p.active = 1 AND p.deleted_at IS NULL AND s.active = 1 AND s.deleted_at IS NULL ORDER BY p.sort, p.id", [Env::companyId()]);
        $this->view('public/subscriptions', ['title' => 'Abonnements', 'plans' => $plans]);
    }

    public function form(string $id): void
    {
        $plan = $this->plan((int) $id);
        $sid = (int) $plan['service_id'];
        $fields = Database::all('SELECT * FROM service_fields WHERE service_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, id', [$sid]);
        foreach ($fields as &$f) $f['choices'] = $f['type'] === 'select' ? Database::all('SELECT id, label FROM service_field_choices WHERE field_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, id', [$f['id']]) : [];
        unset($f);
        $this->view('public/subscription_form', ['title' => 'Demande d\'abonnement', 'plan' => $plan, 'page_js' => 'subscription.js', 'csrf' => \App\Core\Csrf::token(),
            'formulas' => Database::all('SELECT * FROM service_prices WHERE service_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, id', [$sid]), 'fields' => $fields,
            'options' => Database::all('SELECT * FROM service_options WHERE service_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, id', [$sid]),
            'hoods' => Database::all('SELECT id, name FROM neighborhoods WHERE company_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name', [Env::companyId()])]);
    }

    public function store(string $id): void
    {
        if (Request::str('website') !== '') redirect('/abonnements');   // piège à robots
        $plan = $this->plan((int) $id);
        $in = Request::all() + [];
        $in['plan_id'] = (int) $plan['id'];
        try {
            $sub = (new SubscriptionService())->request($in);
        } catch (ValidationException $e) {
            $this->withOld(); Session::flash('errors', $e->errors); redirect('/abonnements/' . (int) $plan['id']);
        }
        $this->ok('Votre demande est bien reçue. Nous vous rappelons très vite pour la confirmer.');
        redirect('/abonnement/' . $sub['track_token']);
    }

    public function manage(string $token): void
    {
        $svc = new SubscriptionService();
        $s = $svc->findByToken($token) ?? abort(404);
        $visits = Database::all("SELECT v.visit_date, v.status, v.note, b.status AS booking_status, b.start_time, b.total, b.reference FROM subscription_visits v LEFT JOIN bookings b ON b.id = v.booking_id
            WHERE v.subscription_id = ? AND v.visit_date >= ? ORDER BY v.visit_date", [(int) $s['id'], date('Y-m-d')]);
        try { $est = $s['status'] === 'cancelled' ? null : $svc->estimate($s); } catch (\Throwable $e) { $est = null; }
        $this->view('public/subscription_manage', ['title' => 'Mon abonnement', 'noindex' => true, 's' => $s, 'visits' => $visits, 'est' => $est]);
    }

    public function pause(string $token): void
    {
        $s = (new SubscriptionService())->findByToken($token) ?? abort(404);
        $until = match (Request::str('for')) { '2w' => date('Y-m-d', strtotime('+14 day')), '1m' => date('Y-m-d', strtotime('+28 day')), '3m' => date('Y-m-d', strtotime('+91 day')), default => null };
        try { (new SubscriptionService())->pause((int) $s['id'], $until, 'client', null); $this->ok($until ? 'Abonnement en pause jusqu\'au ' . fmt_date($until) . '. Il reprend tout seul ensuite.' : 'Abonnement en pause. Reprenez-le quand vous voulez.'); }
        catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect('/abonnement/' . $token);
    }

    public function resume(string $token): void
    {
        $s = (new SubscriptionService())->findByToken($token) ?? abort(404);
        try { (new SubscriptionService())->resume((int) $s['id'], null); $this->ok('Votre abonnement a repris : les prochains passages sont planifiés.'); }
        catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect('/abonnement/' . $token);
    }

    public function cancel(string $token): void
    {
        $s = (new SubscriptionService())->findByToken($token) ?? abort(404);
        (new SubscriptionService())->cancel((int) $s['id'], Request::str('reason'), null);
        $this->ok('Votre abonnement est résilié. Les passages à venir sont annulés. Merci pour votre confiance.');
        redirect('/abonnement/' . $token);
    }

    private function plan(int $id): array
    {
        return Database::one('SELECT p.*, s.name AS service_name, s.description AS service_description, s.conditions AS service_conditions FROM subscription_plans p JOIN services s ON s.id = p.service_id
            WHERE p.id = ? AND p.company_id = ? AND p.active = 1 AND p.deleted_at IS NULL AND s.active = 1 AND s.deleted_at IS NULL', [$id, Env::companyId()]) ?? abort(404);
    }
}
