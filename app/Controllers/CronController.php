<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Database as DB, Env, Migrator, Request};
use App\Services\NotificationService;

/** Déclenche rappels et reprises d'envoi quand on n'a pas accès à cron : /cron/run?token=CRON_TOKEN */
final class CronController extends Controller
{
    public function run(): void
    {
        $this->checkToken();
        $n = new NotificationService();
        json_response(['reminders' => $n->sendReminders(), 'retried_ok' => $n->retryDue()]);
    }

    /**
     * Installation à usage unique, sans terminal : /cron/install?token=CRON_TOKEN&email=...&name=...
     * Applique les migrations puis crée l'administrateur (mot de passe généré, affiché une seule fois).
     * Dès qu'un utilisateur existe, la route répond 410 : supprimez-la ensuite de routes/web.php.
     */
    public function install(): void
    {
        $this->checkToken();
        $log = [];
        $n = Migrator::run(function ($m) use (&$log) { $log[] = $m; });
        if ((int) DB::val('SELECT COUNT(*) FROM users') > 0) json_response(['error' => 'Installation déjà effectuée : route désactivée.'], 410);
        $out = ['migrations' => $n, 'log' => $log];
        $email = mb_strtolower(Request::str('email'));
        $name = Request::str('name');
        if ($email === '' && $name === '') json_response($out + ['admin' => 'non créé : ajoutez &email=...&name=...']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') json_response($out + ['admin' => 'email ou nom invalide'], 422);
        $role = (int) DB::val("SELECT id FROM roles WHERE name = 'admin'");
        if (!$role) json_response($out + ['admin' => 'rôles absents'], 500);
        $pwd = substr(strtr(base64_encode(random_bytes(16)), '+/=', 'xyz'), 0, 16);
        DB::insert('users', ['company_id' => Env::companyId(), 'role_id' => $role, 'name' => $name, 'email' => $email, 'password_hash' => password_hash($pwd, PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now()]);
        json_response($out + ['admin' => ['email' => $email, 'password' => $pwd, 'note' => 'Notez ce mot de passe : il ne sera plus affiché.']], 201);
    }

    private function checkToken(): void
    {
        $token = Env::get('CRON_TOKEN', '');
        if ($token === '' || $token === 'changez-moi' || !hash_equals($token, Request::str('token'))) abort(403, 'Jeton invalide.');
    }
}
