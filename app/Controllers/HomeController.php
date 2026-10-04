<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Database, Env, Request, Validator, Audit};
use App\Services\NotificationService;

final class HomeController extends Controller
{
    public function index(): void
    {
        $cid = Env::companyId();
        $this->view('public/home', [
            'title' => setting('company_name') . ' — nettoyage à domicile et au bureau',
            'featured' => Database::all("SELECT * FROM services WHERE company_id = ? AND active = 1 AND deleted_at IS NULL AND featured = 1 ORDER BY sort LIMIT 6", [$cid]),
            'services' => Database::all("SELECT * FROM services WHERE company_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort", [$cid]),
            'testimonials' => Database::all('SELECT * FROM testimonials WHERE company_id = ? AND active = 1 ORDER BY sort LIMIT 6', [$cid]),
            'faq' => Database::all('SELECT * FROM faq WHERE company_id = ? AND active = 1 ORDER BY sort LIMIT 4', [$cid]),
        ]);
    }

    /** Pages éditoriales (À propos, CGV, confidentialité) gérées depuis l'administration. */
    public function page(): void
    {
        $slug = ['/a-propos' => 'a-propos', '/conditions-generales' => 'cgv', '/confidentialite' => 'confidentialite'][Request::path()] ?? '';
        $page = Database::one('SELECT * FROM pages WHERE company_id = ? AND slug = ? AND active = 1', [Env::companyId(), $slug]);
        if (!$page) abort(404);
        $this->view('public/page', ['title' => $page['title'], 'page' => $page]);
    }

    public function faq(): void
    {
        $this->view('public/faq', ['title' => 'Questions fréquentes', 'faq' => Database::all('SELECT * FROM faq WHERE company_id = ? AND active = 1 ORDER BY sort, id', [Env::companyId()])]);
    }

    public function contact(): void { $this->view('public/contact', ['title' => 'Contact']); }

    public function contactSend(): void
    {
        if (Request::str('website') !== '') redirect('/contact');   // piège à robots (champ caché)
        $errors = Validator::check(Request::all(), ['name' => 'required|maxlen:120', 'message' => 'required|minlen:10|maxlen:3000', 'email' => 'email|maxlen:190', 'phone' => 'phone'],
            ['name' => 'Le nom', 'message' => 'Le message', 'email' => "L'email", 'phone' => 'Le téléphone']);
        if (Request::str('email') === '' && Request::str('phone') === '') $errors['email'] = 'Indiquez un email ou un téléphone pour que nous puissions vous répondre.';
        if ($errors) { $this->withOld(); \App\Core\Session::flash('errors', $errors); redirect('/contact'); }
        $id = Database::insert('contact_messages', ['company_id' => Env::companyId(), 'name' => Request::str('name'), 'email' => Request::str('email') ?: null,
            'phone' => Request::str('phone') ?: null, 'message' => Request::str('message'), 'created_at' => now()]);
        Audit::log('contact.message', 'contact_message', $id, [], null);
        try {   // simple copie email vers l'entreprise ; aucune incidence si ça échoue
            $to = setting('admin_notification_email', '');
            if ($to) \App\Services\Channels\Mailer::send($to, 'Message du site — ' . Request::str('name'), Request::str('message') . "\n\n" . Request::str('email') . ' ' . Request::str('phone'));
        } catch (\Throwable $e) { error_log('Contact mail: ' . $e->getMessage()); }
        $this->ok('Merci, votre message a bien été envoyé. Nous vous répondons rapidement.');
        redirect('/contact');
    }

    public function offline(): void { $this->view('public/offline', ['title' => 'Hors ligne']); }
}
