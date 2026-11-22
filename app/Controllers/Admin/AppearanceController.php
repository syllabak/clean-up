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
            'diag' => self::diagnostics(), 'cb_label' => (string) setting('cb_label', ''), 'cb_position' => ThemeService::chatPosition(), 'show_name' => setting('brand_show_name', '1') !== '0']);
    }

    /** Contrôles qui expliquent un téléversement qui « ne s'enregistre pas ». @return list<array{0:string,1:bool,2:string}> */
    public static function diagnostics(): array
    {
        $out = [];
        foreach (['public/uploads/site' => 'Logo et images du site', 'storage/uploads/missions' => 'Photos des réservations', 'public/uploads/services' => 'Images des services'] as $rel => $label) {
            $dir = BASE_PATH . '/' . $rel;
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $ok = is_dir($dir) && is_writable($dir);
            $out[] = [$label, $ok, $ok ? 'dossier ' . $rel . ' accessible en écriture' : 'dossier ' . $rel . ' NON accessible en écriture : donnez les droits d\'écriture au serveur web (chmod 775, propriétaire du serveur)'];
        }
        $toBytes = function (string $v): int { $n = (int) $v; return match (strtoupper(substr(trim($v), -1))) { 'G' => $n * 1073741824, 'M' => $n * 1048576, 'K' => $n * 1024, default => $n }; };
        $up = (string) ini_get('upload_max_filesize'); $post = (string) ini_get('post_max_size');
        $out[] = ['Taille maximale d\'un fichier', $toBytes($up) >= 5 * 1048576, 'PHP accepte ' . $up . ' par fichier' . ($toBytes($up) >= 5 * 1048576 ? '' : ' : trop peu pour un logo ou une photo non réduite. Augmentez upload_max_filesize (fichier public/.user.ini fourni, ou php.ini de l\'hébergeur)')];
        $out[] = ['Taille maximale d\'un envoi', $toBytes($post) >= 8 * 1048576, 'PHP accepte ' . $post . ' par envoi' . ($toBytes($post) >= 8 * 1048576 ? '' : ' : augmentez post_max_size')];
        $free = @disk_free_space(BASE_PATH);
        $out[] = ['Espace disque', $free === false || $free > 100 * 1048576, $free === false ? 'non mesurable' : round($free / 1048576) . ' Mo libres'];
        $out[] = ['Conservation des fichiers', true, 'Les images sont enregistrées dans les dossiers ci-dessus. Sur un hébergement dont le disque est remis à zéro à chaque mise à jour (conteneur, Render, Heroku…), elles disparaissent : utilisez un disque persistant ou un hébergement classique.'];
        return $out;
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
