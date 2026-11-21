<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Database as DB, Env};

/** « Premiers pas » : ce qu'il reste à régler pour que l'entreprise soit réellement prête à recevoir des réservations. */
final class OnboardingService
{
    /** @return list<array{label:string,done:bool,href:string,hint:string}> */
    public static function steps(): array
    {
        $cid = Env::companyId();
        $catalog = new CatalogService();
        $ready = 0;
        foreach (DB::all('SELECT id FROM services WHERE company_id = ? AND active = 1 AND deleted_at IS NULL', [$cid]) as $s) {
            if (!array_filter($catalog->readiness((int) $s['id']), fn($c) => $c[0] === 'bad')) $ready++;
        }
        $mailTest = ChannelConfig::get('email_driver', 'MAIL_DRIVER', 'log') === 'log' && ChannelConfig::get('sms_driver', 'SMS_DRIVER', 'log') === 'log';
        return [
            ['label' => "Renseigner les coordonnées de l'entreprise", 'done' => setting('company_phone') !== '+221 77 000 00 00' && setting('company_email') !== 'contact@cleanup.sn',
                'href' => '/admin/parametres', 'hint' => 'Téléphone, email, WhatsApp et horaires affichés aux clients.'],
            ['label' => 'Avoir au moins un service prêt à être réservé', 'done' => $ready > 0, 'href' => '/admin/crud/services', 'hint' => 'Prix, équipe compétente, zone et horaires : la fiche du service indique ce qui manque.'],
            ['label' => 'Déclarer les zones desservies', 'done' => (int) DB::val('SELECT COUNT(*) FROM neighborhoods WHERE company_id = ? AND deleted_at IS NULL AND active = 1', [$cid]) > 0,
                'href' => '/admin/crud/neighborhoods', 'hint' => 'Les quartiers où vos équipes interviennent.'],
            ['label' => 'Avoir une équipe avec ses horaires de travail', 'done' => (int) DB::val("SELECT COUNT(DISTINCT t.id) FROM teams t JOIN working_hours w ON w.team_id = t.id WHERE t.company_id = ? AND t.deleted_at IS NULL", [$cid]) > 0,
                'href' => '/admin/crud/teams', 'hint' => 'Sans horaires, aucun créneau n\'est proposé aux clients.'],
            ['label' => "Brancher l'envoi des emails ou des SMS", 'done' => !$mailTest, 'href' => '/admin/marketing/canaux', 'hint' => 'Actuellement en mode test : les messages sont écrits dans un journal et rien ne part.'],
            ['label' => 'Supprimer ou sécuriser les comptes de démonstration', 'done' => (int) DB::val("SELECT COUNT(*) FROM users WHERE company_id = ? AND deleted_at IS NULL AND email LIKE '%@cleanup.test'", [$cid]) === 0,
                'href' => '/admin/crud/users', 'hint' => 'Leurs mots de passe sont publics dans la documentation.'],
        ];
    }
}
