<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Audit, Request, Upload};
use App\Services\ThemeService;

/** Apparence : thème, logo, couleurs des boutons, bouton du chatbot. */
final class AppearanceController extends Controller
{
    public function index(): void
    {
        $v = [];
        foreach (ThemeService::DEFAULTS as $k => $_) $v[$k] = ThemeService::get($k);
        $this->admin('admin/appearance', ['title' => 'Apparence', 'v' => $v, 'presets' => ThemeService::PRESETS, 'logo' => ThemeService::logo(),
            'cb_label' => (string) setting('cb_label', ''), 'cb_position' => ThemeService::chatPosition(), 'show_name' => setting('brand_show_name', '1') !== '0']);
    }

    public function save(): void
    {
        if (Request::str('reset') === '1') {
            foreach (array_keys(ThemeService::DEFAULTS) as $k) setting_set($k, '');
            foreach (['cb_label', 'cb_position', 'brand_logo'] as $k) setting_set($k, '');
            setting_set('brand_show_name', '1');
            Audit::log('appearance.reset', 'settings', null, []);
            $this->ok("Apparence d'origine rétablie.");
            redirect('/admin/apparence');
        }
        foreach (ThemeService::DEFAULTS as $k => $def) {
            $raw = strtoupper(trim(Request::str($k)));
            if (!preg_match('/^#[0-9A-F]{6}$/', $raw)) { $this->err("La couleur « {$k} » n'est pas valide (format #RRGGBB)."); redirect('/admin/apparence'); }
            setting_set($k, $raw);
        }
        setting_set('cb_label', mb_substr(trim(Request::str('cb_label')), 0, 30));
        setting_set('cb_position', Request::str('cb_position') === 'right' ? 'right' : 'left');
        setting_set('brand_show_name', Request::str('brand_show_name') === '1' ? '1' : '0');
        if (Request::str('remove_logo') === '1') setting_set('brand_logo', '');
        try { $name = Upload::image($_FILES['logo'] ?? ['error' => UPLOAD_ERR_NO_FILE], BASE_PATH . '/public/uploads/site'); }
        catch (\RuntimeException $e) { $this->err($e->getMessage()); redirect('/admin/apparence'); }
        if ($name) setting_set('brand_logo', '/uploads/site/' . $name);
        Audit::log('appearance.save', 'settings', null, ['logo' => $name ? 'changé' : 'inchangé']);
        $this->ok('Apparence enregistrée. Elle est visible tout de suite sur le site.');
        redirect('/admin/apparence');
    }
}
