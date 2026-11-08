<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Audit, Auth, Database as DB, Env, Request, Session, ValidationException};
use App\Services\{ChannelConfig, MarketingService, TrackingService};
use App\Services\Channels\WhatsAppGateway;

/** Marketing : canaux (email, SMS, WhatsApp, Meta, Google), campagnes, liens suivis. */
final class MarketingController extends Controller
{
    private const PRESETS = [
        'facebook' => ['Facebook Ads', 'facebook', 'paid_social'], 'instagram' => ['Instagram Ads', 'instagram', 'paid_social'], 'google' => ['Google Ads', 'google', 'cpc'],
        'tiktok' => ['TikTok Ads', 'tiktok', 'paid_social'], 'whatsapp' => ['Statut / message WhatsApp', 'whatsapp', 'social'], 'sms' => ['SMS', 'sms', 'message'],
        'email' => ['Email', 'email', 'message'], 'flyers' => ['Flyer ou affiche (QR code)', 'flyers', 'offline'],
    ];
    private const PAGES = ['/reserver' => 'Réservation', '/' => "Page d'accueil", '/services' => 'Services', '/contact' => 'Contact'];

    // ------------------------------------------------------------------ Vue d'ensemble
    public function index(): void
    {
        $cid = Env::companyId();
        $wa = ['off' => 'Désactivé', 'link' => 'Sans API (liens)', 'cloud' => 'API Meta'][WhatsAppGateway::mode()];
        $emailDriver = ChannelConfig::get('email_driver', 'MAIL_DRIVER', 'log'); $smsDriver = ChannelConfig::get('sms_driver', 'SMS_DRIVER', 'log');
        $g = TrackingService::google();
        $this->admin('admin/marketing', ['title' => 'Marketing',
            'status' => [
                ['Email', $emailDriver === 'log' ? 'Mode test' : ($emailDriver === 'mail' ? 'mail()' : 'SMTP'), $emailDriver !== 'log'],
                ['SMS', $smsDriver === 'http' ? 'API HTTP' : 'Mode test', $smsDriver === 'http'],
                ['WhatsApp', $wa, WhatsAppGateway::mode() !== 'off'],
                ['Facebook / Instagram Ads', TrackingService::metaPixel() ? 'Pixel actif' . (ChannelConfig::get('meta_capi_token') !== '' ? ' + API Conversions' : '') : 'Inactif', TrackingService::metaPixel() !== null],
                ['Google Ads / Analytics', ($g['ads'] || $g['ga4']) ? 'Balise active' : 'Inactif', (bool) ($g['ads'] || $g['ga4'])],
            ],
            'consenting' => (int) DB::val('SELECT COUNT(*) FROM customers WHERE company_id = ? AND deleted_at IS NULL AND marketing_consent = 1', [$cid]),
            'customers' => (int) DB::val('SELECT COUNT(*) FROM customers WHERE company_id = ? AND deleted_at IS NULL', [$cid]),
            'campaigns' => DB::all('SELECT * FROM marketing_campaigns WHERE company_id = ? ORDER BY id DESC LIMIT 5', [$cid]),
            'events' => DB::all('SELECT * FROM marketing_events WHERE company_id = ? ORDER BY id DESC LIMIT 8', [$cid]),
            'can_settings' => Auth::can('settings.manage'),
        ]);
    }

    // ------------------------------------------------------------------ Canaux (administrateur)
    public function channels(): void
    {
        $this->admin('admin/marketing_channels', ['title' => 'Canaux marketing', 'sections' => ChannelConfig::sections()]);
    }

    public function channelSave(string $section): void
    {
        $sections = ChannelConfig::sections();
        $def = $sections[$section] ?? abort(404);
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
            if ($error = $this->invalid($key, $type, $new, $options)) { $this->err("« $label » : $error"); redirect('/admin/marketing/canaux#' . $section); }
            if ($new !== $current) $changed[] = $key;
            setting_set('mk_' . $key, $new);
        }
        Audit::log('marketing.channel.save', 'settings', null, ['section' => $section, 'changed' => $changed]);   // jamais les valeurs
        $this->ok($def['title'] . ' : réglages enregistrés.');
        redirect('/admin/marketing/canaux#' . $section);
    }

    public function channelTest(string $section): void
    {
        if (!in_array($section, ['email', 'sms', 'whatsapp'], true)) abort(404);
        $to = Request::str('to');
        $name = (string) setting('company_name', 'Clean Up');
        try {
            $link = (new MarketingService())->sendDirect($section, $to, "Test d'envoi $name : si vous lisez ce message, le canal fonctionne.", "Test d'envoi $name", ['{client}' => 'Test', '{entreprise}' => $name]);
        } catch (\Throwable $e) {
            $this->err('Échec du test : ' . $e->getMessage()); redirect('/admin/marketing/canaux#' . $section);
        }
        if ($link) redirect($link);   // WhatsApp sans API : on ouvre la conversation avec le message prêt
        $driver = match ($section) { 'email' => ChannelConfig::get('email_driver', 'MAIL_DRIVER', 'log'), 'sms' => ChannelConfig::get('sms_driver', 'SMS_DRIVER', 'log'), default => '' };
        $this->ok('Test envoyé à ' . $to . '.' . ($driver === 'log' ? " Le mode est « test » : le message est écrit dans storage/logs et rien n'est réellement parti." : ''));
        redirect('/admin/marketing/canaux#' . $section);
    }

    private function invalid(string $key, string $type, string $v, ?array $options): ?string
    {
        if ($type === 'select' && !array_key_exists($v, $options ?? [])) return 'valeur non autorisée.';
        if ($v === '' || $type === 'bool' || $type === 'select' || $type === 'secret' || $type === 'textarea') return null;
        if ($type === 'number' && !preg_match('/^\d{1,5}$/', $v)) return 'nombre entier attendu.';
        $rules = ['meta_pixel_id' => ['/^\d{5,20}$/', 'chiffres uniquement.'], 'google_ads_id' => ['/^AW-\d{5,15}$/', 'format AW-123456789.'], 'google_ga4_id' => ['/^G-[A-Z0-9]{4,15}$/', 'format G-XXXXXXXXXX.'],
            'google_label_booking' => ['/^[A-Za-z0-9_\-]{4,60}$/', 'lettres, chiffres, - et _.'], 'google_label_lead' => ['/^[A-Za-z0-9_\-]{4,60}$/', 'lettres, chiffres, - et _.'],
            'wa_phone_id' => ['/^\d{5,20}$/', 'chiffres uniquement.'], 'wa_version' => ['/^v\d+\.\d+$/', 'format v21.0.'], 'wa_template' => ['/^[a-z0-9_]{1,512}$/', 'minuscules, chiffres et _ (nom donné par Meta).'],
            'wa_template_lang' => ['/^[a-z]{2}(_[A-Z]{2})?$/', 'ex. fr ou fr_FR.'], 'sms_api_url' => ['#^https?://[^\s]+$#i', 'adresse commençant par https://.'], 'email_port' => ['/^\d{2,5}$/', 'numéro de port.'],
            'meta_test_code' => ['/^[A-Za-z0-9]{1,30}$/', 'lettres et chiffres.']];
        if (isset($rules[$key]) && !preg_match($rules[$key][0], $v)) return $rules[$key][1];
        if ($key === 'email_from_address' && !filter_var($v, FILTER_VALIDATE_EMAIL)) return "email invalide.";
        return null;
    }

    // ------------------------------------------------------------------ Campagnes
    public function campaigns(): void
    {
        $rows = DB::all('SELECT c.*, (SELECT COUNT(*) FROM marketing_messages m WHERE m.campaign_id = c.id) AS total,
            (SELECT COUNT(*) FROM marketing_messages m WHERE m.campaign_id = c.id AND m.status = \'sent\') AS sent
            FROM marketing_campaigns c WHERE c.company_id = ? ORDER BY c.id DESC LIMIT 100', [Env::companyId()]);
        $this->admin('admin/marketing_campaigns', ['title' => 'Campagnes', 'rows' => $rows]);
    }

    public function campaignNew(): void
    {
        $this->admin('admin/marketing_campaign_form', ['title' => 'Nouvelle campagne', 'channels' => DB::all('SELECT id, name FROM acquisition_channels WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY sort, name', [Env::companyId()])]);
    }

    public function campaignCreate(): void
    {
        try {
            $id = (new MarketingService())->create(Request::all(), Auth::id());
        } catch (ValidationException $e) {
            $this->withOld(); Session::flash('errors', $e->errors); redirect('/admin/marketing/campagnes/nouveau');
        }
        $this->ok('Campagne créée en brouillon. Envoyez-vous un test avant de la lancer.');
        redirect('/admin/marketing/campagnes/' . $id);
    }

    public function campaign(string $id): void
    {
        $svc = new MarketingService();
        $c = $svc->find((int) $id) ?? abort(404);
        $preview = null;
        if ($c['status'] === 'draft') {
            $aud = $svc->audience($c['channel'], $c['audience'], $c['audience_channel_id'] ? (int) $c['audience_channel_id'] : null);
            $preview = ['count' => count($aud), 'sample' => $aud ? $svc->render($c, $aud[0]) : null];
        }
        $this->admin('admin/marketing_campaign', ['title' => $c['name'], 'c' => $c, 'counts' => $svc->counts((int) $id), 'preview' => $preview, 'results' => $svc->bookingsFrom($c),
            'messages' => DB::all('SELECT m.*, cu.first_name, cu.last_name FROM marketing_messages m JOIN customers cu ON cu.id = m.customer_id WHERE m.campaign_id = ? ORDER BY m.id LIMIT 200', [(int) $id]),
            'wa_mode' => WhatsAppGateway::mode(), 'problem' => MarketingService::channelProblem($c['channel'])]);
    }

    public function campaignLaunch(string $id): void
    {
        $svc = new MarketingService();
        try {
            $n = $svc->launch((int) $id, Auth::id());
            $c = $svc->find((int) $id);
            if ($c['channel'] === 'whatsapp' && WhatsAppGateway::mode() === 'link') { $this->ok("$n messages WhatsApp prêts : ouvrez-les un par un ci-dessous."); redirect("/admin/marketing/campagnes/$id/whatsapp"); }
            $this->ok("Campagne lancée : $n destinataires. Les envois partent par lots à chaque passage de la tâche planifiée (ou avec « Envoyer un lot maintenant »).");
        } catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect("/admin/marketing/campagnes/$id");
    }

    public function campaignBatch(string $id): void
    {
        try { $n = (new MarketingService())->processOne((int) $id, 50); $this->ok("$n message(s) envoyé(s)."); } catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect("/admin/marketing/campagnes/$id");
    }

    public function campaignCancel(string $id): void
    {
        try { (new MarketingService())->cancel((int) $id, Auth::id()); $this->ok('Campagne annulée : les messages non envoyés ne partiront pas.'); } catch (\RuntimeException $e) { $this->err($e->getMessage()); }
        redirect("/admin/marketing/campagnes/$id");
    }

    public function campaignTest(string $id): void
    {
        $svc = new MarketingService();
        $c = $svc->find((int) $id) ?? abort(404);
        try {
            if ($problem = MarketingService::channelProblem($c['channel'])) throw new \RuntimeException($problem);
            $link = $svc->sendTest($c, Request::str('to'));
        } catch (\Throwable $e) { $this->err('Échec du test : ' . $e->getMessage()); redirect("/admin/marketing/campagnes/$id"); }
        if ($link) redirect($link);
        $this->ok('Message de test envoyé à ' . Request::str('to') . '.');
        redirect("/admin/marketing/campagnes/$id");
    }

    // ------------------------------------------------------------------ WhatsApp sans API
    public function whatsapp(string $id): void
    {
        $c = (new MarketingService())->find((int) $id) ?? abort(404);
        if ($c['channel'] !== 'whatsapp') abort(404);
        $rows = DB::all("SELECT m.*, cu.first_name, cu.last_name FROM marketing_messages m JOIN customers cu ON cu.id = m.customer_id WHERE m.campaign_id = ? AND m.status = 'manual' ORDER BY m.id LIMIT 200", [(int) $id]);
        foreach ($rows as &$r) $r['link'] = WhatsAppGateway::link($r['recipient'], $r['content']);
        unset($r);
        $this->admin('admin/marketing_whatsapp', ['title' => 'WhatsApp : ' . $c['name'], 'c' => $c, 'rows' => $rows, 'counts' => (new MarketingService())->counts((int) $id)]);
    }

    public function whatsappMark(string $id, string $mid): void
    {
        (new MarketingService())->markManual((int) $mid, Request::str('result') === 'sent');
        redirect("/admin/marketing/campagnes/$id/whatsapp");
    }

    // ------------------------------------------------------------------ Résultats publicitaires
    public function ads(): void
    {
        [$from, $to] = \App\Services\BusinessReportService::range(Request::str('from') ?: date('Y-m-d', strtotime('-29 days')), Request::str('to'));
        $this->admin('admin/marketing_ads', ['title' => 'Résultats publicitaires', 'from' => $from, 'to' => $to] + (new MarketingService())->adReport($from, $to));
    }

    // ------------------------------------------------------------------ Liens suivis
    public function links(): void
    {
        $page = isset(self::PAGES[Request::str('page')]) ? Request::str('page') : '/reserver';
        $preset = self::PRESETS[Request::str('preset')] ?? null;
        $source = Request::str('source') !== '' ? Request::str('source') : ($preset[1] ?? '');
        $medium = Request::str('medium') !== '' ? Request::str('medium') : ($preset[2] ?? '');
        $campaign = Request::str('campaign');
        $url = ($source !== '' && $medium !== '') ? TrackingService::link($page, slugify($source), slugify($medium), $campaign) : null;
        $this->admin('admin/marketing_links', ['title' => 'Liens suivis', 'presets' => self::PRESETS, 'pages' => self::PAGES, 'page' => $page, 'source' => $source, 'medium' => $medium, 'campaign' => $campaign, 'url' => $url]);
    }
}
