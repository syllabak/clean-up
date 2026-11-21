<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Audit, Request};
use App\Services\{ChannelConfig, MarketingService, PaymentService};

/** Configuration → Messagerie et paiement : email, SMS, PayTech, PayDunya. Réservé à l'administrateur (les clés sont sensibles). */
final class IntegrationsController extends Controller
{
    private const BASE = '/admin/messagerie-paiement';

    public function index(): void
    {
        $this->admin('admin/integrations', ['title' => 'Messagerie et paiement', 'sections' => ChannelConfig::integrations(), 'base' => self::BASE, 'status' => (new PaymentService())->status()]);
    }

    public function save(string $section): void
    {
        $def = ChannelConfig::integrations()[$section] ?? abort(404);
        $changed = [];
        foreach ($def['fields'] as $key => $f) {
            [$label, $type] = $f; $options = $f[3] ?? null;
            $current = (string) setting('mk_' . $key, '');
            if ($type === 'secret') {
                $new = Request::str($key);
                if (Request::str('clear_' . $key) === '1') $new = '';
                elseif ($new === '') continue;   // champ laissé vide : on garde le secret enregistré
            } elseif ($type === 'bool') {
                $new = Request::str($key) === '1' ? '1' : '0';
            } else {
                $new = mb_substr(Request::str($key), 0, 2000);
            }
            if ($error = ChannelConfig::invalid($key, $type, $new, $options)) { $this->err("« $label » : $error"); redirect(self::BASE . '#' . $section); }
            if ($new !== $current) $changed[] = $key;
            setting_set('mk_' . $key, $new);
        }
        Audit::log('integrations.save', 'settings', null, ['section' => $section, 'changed' => $changed]);   // jamais les valeurs
        $this->ok($def['title'] . ' : réglages enregistrés.');
        redirect(self::BASE . '#' . $section);
    }

    public function test(string $section): void
    {
        ChannelConfig::integrations()[$section] ?? abort(404);
        if (in_array($section, ['paytech', 'paydunya'], true)) {
            try { $msg = (new PaymentService())->testConnection($section); $this->ok($msg); }
            catch (\Throwable $e) { $this->err('Échec du test : ' . $e->getMessage()); }
            redirect(self::BASE . '#' . $section);
        }
        if (!in_array($section, ['email', 'sms'], true)) abort(404);
        $to = Request::str('to');
        $name = (string) setting('company_name', 'Clean Up');
        try {
            (new MarketingService())->sendDirect($section, $to, "Test d'envoi $name : si vous lisez ce message, le canal fonctionne.", "Test d'envoi $name", ['{client}' => 'Test', '{entreprise}' => $name]);
        } catch (\Throwable $e) { $this->err('Échec du test : ' . $e->getMessage()); redirect(self::BASE . '#' . $section); }
        $driver = $section === 'email' ? ChannelConfig::get('email_driver', 'MAIL_DRIVER', 'log') : ChannelConfig::get('sms_driver', 'SMS_DRIVER', 'log');
        $this->ok('Test envoyé à ' . $to . '.' . ($driver === 'log' ? " Le mode est « test » : le message est écrit dans storage/logs et rien n'est réellement parti." : ''));
        redirect(self::BASE . '#' . $section);
    }
}
