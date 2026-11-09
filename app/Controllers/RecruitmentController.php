<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Audit, Database, Env, Request, Session, Validator};

/** Espace de recrutement public : une page, un formulaire court, une page de remerciement. */
final class RecruitmentController extends Controller
{
    public const SKILLS = ['auto' => 'Lavage automobile', 'textile' => 'Canapés, matelas, tapis', 'maison' => 'Ménage à domicile', 'bureaux' => 'Bureaux et entreprises', 'vitres' => 'Vitres', 'repassage' => 'Repassage'];
    public const EXPERIENCE = ['none' => 'Débutant', 'lt1' => 'Moins d\'un an', '1to3' => '1 à 3 ans', 'gt3' => 'Plus de 3 ans'];
    public const AVAILABILITY = ['full' => 'Temps plein', 'part' => 'Quelques jours par semaine', 'weekend' => 'Week-ends', 'ad_hoc' => 'Ponctuellement'];

    public function form(): void
    {
        $this->view('public/recruitment', [
            'title' => 'Rejoindre l\'équipe', 'skills' => self::SKILLS, 'experience' => self::EXPERIENCE, 'availability' => self::AVAILABILITY,
            'hoods' => Database::all('SELECT id, name FROM neighborhoods WHERE company_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name', [Env::companyId()]),
        ]);
    }

    public function send(): void
    {
        if (Request::str('website') !== '') redirect('/recrutement/merci');   // piège à robots
        $in = Request::all();
        $skills = array_values(array_intersect(array_keys(self::SKILLS), is_array($in['skills'] ?? null) ? array_map('strval', $in['skills']) : []));
        $errors = Validator::check($in, ['name' => 'required|maxlen:120', 'phone' => 'required|phone', 'email' => 'email|maxlen:190', 'message' => 'maxlen:1500',
            'experience' => 'required|in:' . implode(',', array_keys(self::EXPERIENCE)), 'availability' => 'required|in:' . implode(',', array_keys(self::AVAILABILITY))],
            ['name' => 'Le nom', 'phone' => 'Le téléphone', 'email' => "L'email", 'message' => 'Le message', 'experience' => "L'expérience", 'availability' => 'La disponibilité']);
        if (!$skills) $errors['skills'] = 'Cochez au moins un type de travail que vous savez faire.';
        if (Request::str('adult') !== '1') $errors['adult'] = 'Il faut avoir 18 ans ou plus pour postuler.';
        if (Request::str('consent') !== '1') $errors['consent'] = 'Acceptez que nous vous contactions au sujet de votre candidature.';
        if ($errors) { $this->withOld(); Session::flash('errors', $errors); redirect('/recrutement'); }

        $phone = normalize_phone(Request::str('phone'));
        $cid = Env::companyId();
        $hood = Request::int('neighborhood_id');
        if ($hood && !Database::val('SELECT id FROM neighborhoods WHERE id = ? AND company_id = ?', [$hood, $cid])) $hood = 0;
        // Même numéro dans les dernières 24 h : on n'enregistre pas deux fois (double clic, réseau lent).
        $dup = Database::val("SELECT id FROM job_applications WHERE company_id = ? AND phone = ? AND created_at > ?", [$cid, $phone, gmdate('Y-m-d H:i:s', time() - 86400)]);
        if (!$dup) {
            $id = Database::insert('job_applications', ['company_id' => $cid, 'name' => Request::str('name'), 'phone' => $phone, 'email' => Request::str('email') ?: null,
                'neighborhood_id' => $hood ?: null, 'skills' => implode(',', $skills), 'experience' => Request::str('experience'), 'availability' => Request::str('availability'),
                'has_whatsapp' => Request::str('has_whatsapp') === '0' ? 0 : 1, 'message' => Request::str('message') ?: null, 'status' => 'new', 'source' => Request::str('src') ?: 'site', 'created_at' => now()]);
            Audit::log('recruitment.apply', 'job_application', (int) $id, [], null);
            try {   // simple copie email vers l'entreprise ; aucune incidence si ça échoue
                $to = setting('admin_notification_email', '');
                if ($to) \App\Services\Channels\Mailer::send($to, 'Nouvelle candidature — ' . Request::str('name'), Request::str('name') . ' · ' . $phone . "\nCompétences : " . implode(', ', array_map(fn($k) => self::SKILLS[$k], $skills)));
            } catch (\Throwable $e) { error_log('Recrutement mail: ' . $e->getMessage()); }
        }
        redirect('/recrutement/merci');
    }

    public function thanks(): void { $this->view('public/recruitment_thanks', ['title' => 'Candidature reçue']); }
}
