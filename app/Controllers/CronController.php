<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Env, Request};
use App\Services\NotificationService;

/** Déclenche rappels et reprises d'envoi quand on n'a pas accès à cron : /cron/run?token=CRON_TOKEN */
final class CronController extends Controller
{
    public function run(): void
    {
        $token = Env::get('CRON_TOKEN', '');
        if ($token === '' || $token === 'changez-moi' || !hash_equals($token, Request::str('token'))) abort(403, 'Jeton invalide.');
        $n = new NotificationService();
        json_response(['reminders' => $n->sendReminders(), 'retried_ok' => $n->retryDue()]);
    }
}
