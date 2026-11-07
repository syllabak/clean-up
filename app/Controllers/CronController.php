<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Env, Request};
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
        $token = Env::get('CRON_TOKEN', '');
        if ($token === '' || $token === 'changez-moi' || !hash_equals($token, $this->provided())) abort(403, 'Jeton invalide.');
        $n = new NotificationService();
        json_response(['reminders' => $n->sendReminders(), 'retried_ok' => $n->retryDue()]);
    }
}
