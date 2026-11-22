<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Database as DB, Env, Migrator, Request};
use App\Services\NotificationService;

/** Déclenche rappels et reprises d'envoi quand on n'a pas accès à cron : /cron/run?token=CRON_TOKEN */
final class CronController extends Controller
{
    /** Jeton fourni : en-tête « Authorization: Bearer … » ou « X-Cron-Token » (préféré : n'apparaît pas dans les journaux), sinon paramètre ?token=. */
    private function provided(): string
    {
        $h = $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
        if ($h === '' && preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) $h = trim($m[1]);
        return $h !== '' ? $h : Request::str('token');
    }

    public function run(): void
    {
        $this->checkToken();
        $n = new NotificationService();
        json_response(['reminders' => $n->sendReminders(), 'retried_ok' => $n->retryDue(), 'campaign_messages' => (new \App\Services\MarketingService())->processQueue(), 'subscriptions' => (new \App\Services\SubscriptionService())->generateAll()]);
    }

    /** Aide au dépannage (réponse protégée par le jeton) : tables attendues par les migrations mais absentes, et fin du journal d'erreurs. */
    private static function diagnostic(): array
    {
        $files = glob(BASE_PATH . '/database/migrations/*.php') ?: []; sort($files);
        $missing = [];
        foreach ($files as $f) {
            if (preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?[`"]?([a-z_0-9]+)[`"]?/i', (string) file_get_contents($f), $m)) {
                foreach ($m[1] as $t) if ($t !== 'migrations' && !DB::tableExists($t)) $missing[$t] = basename($f);
            }
        }
        $log = BASE_PATH . '/storage/logs/app.log'; $tail = [];
        if (is_file($log)) $tail = array_map(fn($l) => mb_substr($l, 0, 300), array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -8));
        return ['fichiers_de_migration' => count($files), 'derniere_migration_presente' => basename((string) end($files)), 'tables_manquantes' => $missing, 'moteur' => DB::driver(), 'fin_du_journal_erreurs' => $tail];
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
        if ((int) DB::val('SELECT COUNT(*) FROM users') > 0) json_response(['error' => 'Installation déjà effectuée : route désactivée.', 'migrations_appliquees' => $n, 'journal' => $log, 'en_attente' => array_map('basename', \App\Core\Migrator::pending())] + self::diagnostic(), 410);
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
        if ($token === '' || $token === 'changez-moi' || !hash_equals($token, $this->provided())) abort(403, 'Jeton invalide.');
    }
}
