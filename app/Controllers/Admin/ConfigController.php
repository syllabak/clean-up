<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;

/** Page « Configuration » : tout ce qui se règle rarement, rangé à un seul endroit au lieu de vingt entrées de menu. */
final class ConfigController extends Controller
{
    /** Droits dont un seul suffit pour voir l'entrée « Configuration » dans le menu. */
    public const PERMS = ['catalog.manage', 'geo.manage', 'staff.manage', 'notifications.manage', 'cms.manage', 'users.manage', 'settings.manage'];

    public function index(): void
    {
        $groups = [
            'Ce que vous vendez' => [
                ['Services et tarifs', 'Prix, formules, options, photos. Un service est « prêt » quand rien ne manque.', '/admin/crud/services', 'catalog.manage'],
                ['Catégories', 'Regrouper les services sur le site.', '/admin/crud/service_categories', 'catalog.manage'],
            ],
            'Où et avec qui' => [
                ['Quartiers desservis', 'Zones, frais de déplacement et jours de passage.', '/admin/crud/neighborhoods', 'geo.manage'],
                ['Régions', 'Régions, villes et communes.', '/admin/crud/regions', 'geo.manage'],
                ['Équipes', 'Équipes, capacité par jour et horaires.', '/admin/crud/teams', 'staff.manage'],
                ['Agents', 'Les personnes qui interviennent.', '/admin/crud/staff', 'staff.manage'],
                ['Compétences', 'Ce que chaque agent sait faire.', '/admin/crud/skills', 'staff.manage'],
                ['Congés et fermetures', 'Jours où personne ne travaille.', '/admin/crud/time_off', 'staff.manage'],
            ],
            'Messages aux clients' => [
                ['Modèles de messages', 'Le texte des SMS, emails et WhatsApp automatiques.', '/admin/crud/notifications', 'notifications.manage'],
                ['Journal des envois', 'Ce qui est parti, ce qui a échoué, relancer.', '/admin/notifications', 'notifications.manage'],
                ['Canaux d\'envoi (email, SMS, WhatsApp)', 'Brancher votre fournisseur, tester un envoi.', '/admin/marketing/canaux', 'settings.manage'],
            ],
            'Site web' => [
                ['Messages reçus', 'Ce que les visiteurs vous écrivent depuis le site.', '/admin/crud/contact_messages', 'cms.manage'],
                ['Pages', 'À propos, conditions générales, confidentialité.', '/admin/crud/pages', 'cms.manage'],
                ['Questions fréquentes', 'La FAQ du site.', '/admin/crud/faq', 'cms.manage'],
                ['Témoignages', 'Les avis affichés sur l\'accueil.', '/admin/crud/testimonials', 'cms.manage'],
            ],
            'Entreprise et accès' => [
                ['Paramètres', 'Nom, téléphone, horaires, règles de réservation, notifications.', '/admin/parametres', 'settings.manage'],
                ['Utilisateurs', 'Qui peut se connecter, avec quel rôle.', '/admin/crud/users', 'users.manage'],
                ['Journal d\'audit', 'Qui a fait quoi, et quand.', '/admin/audit', 'users.manage'],
                ['Canaux d\'acquisition', 'D\'où viennent vos clients (site, Facebook, bouche à oreille…).', '/admin/crud/acquisition_channels', 'crm.manage'],
            ],
        ];
        foreach ($groups as $title => $cards) { $groups[$title] = array_values(array_filter($cards, fn($c) => can($c[3]))); if (!$groups[$title]) unset($groups[$title]); }
        $this->admin('admin/configuration', ['title' => 'Configuration', 'groups' => $groups]);
    }
}
