<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Audit, Migrator};

/** Mise à jour de la base sans terminal : applique les migrations en attente (avec sauvegarde SQLite préalable). */
final class DatabaseUpdateController extends Controller
{
    public function run(): void
    {
        $pending = count(Migrator::pending());
        if ($pending === 0) { $this->ok('La base de données est déjà à jour.'); redirect('/admin/configuration'); }
        try {
            $backup = Migrator::backup();
            $log = [];
            $n = Migrator::run(function ($m) use (&$log) { $log[] = $m; });
            Audit::log('database.update', 'database', null, ['applied' => $n, 'backup' => $backup ? basename($backup) : null]);
            $this->ok("Base de données mise à jour ({$n} évolution" . ($n > 1 ? 's' : '') . ' appliquée' . ($n > 1 ? 's' : '') . ').' . ($backup ? ' Une sauvegarde a été faite avant.' : ''));
        } catch (\Throwable $e) {
            @file_put_contents(BASE_PATH . '/storage/logs/app.log', sprintf("[%s] mise à jour base : %s\n", date('c'), $e->getMessage()), FILE_APPEND | LOCK_EX);
            $this->err('La mise à jour a échoué : ' . $e->getMessage());
        }
        redirect('/admin/configuration');
    }
}
