<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Audit, Request, Upload};
use App\Services\NotificationService;

final class SettingsController extends Controller
{
    public function index(): void
    {
        $this->admin('admin/settings', ['title' => 'Paramètres', 'groups' => require BASE_PATH . '/config/settings.php', 'events' => NotificationService::EVENTS]);
    }

    public function save(): void
    {
        $groups = require BASE_PATH . '/config/settings.php';
        $changed = [];
        foreach ($groups as $fields) {
            foreach ($fields as $key => $def) {
                [, $type] = $def;
                if ($type === 'image') {
                    try { $name = Upload::image($_FILES[$key] ?? ['error' => UPLOAD_ERR_NO_FILE], BASE_PATH . '/public/uploads/site'); }
                    catch (\RuntimeException $e) { $this->err($e->getMessage()); redirect('/admin/parametres'); }
                    if ($name) { setting_set($key, '/uploads/site/' . $name); $changed[] = $key; }
                    continue;
                }
                $v = $type === 'bool' ? (Request::str($key) === '1' ? '1' : '0') : mb_substr(Request::str($key), 0, 2000);
                if ($type === 'number' && !preg_match('/^\d{1,5}$/', $v)) { $this->err("« {$def[0]} » doit être un nombre entier."); redirect('/admin/parametres'); }
                if ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $this->err("« {$def[0]} » n'est pas un email valide."); redirect('/admin/parametres'); }
                if (setting($key) !== $v) $changed[] = $key;
                setting_set($key, $v);
            }
        }
        foreach (array_keys(NotificationService::EVENTS) as $ev) {
            foreach (['email', 'sms'] as $ch) {
                $k = NotificationService::toggleKey($ev, $ch);
                $v = Request::str($k) === '1' ? '1' : '0';
                if (setting($k, '1') !== $v) $changed[] = $k;
                setting_set($k, $v);
            }
        }
        Audit::log('settings.save', 'settings', null, ['changed' => $changed]);
        $this->ok('Paramètres enregistrés.');
        redirect('/admin/parametres');
    }
}
