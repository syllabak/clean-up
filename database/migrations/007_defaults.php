<?php
// Données indispensables en production : entreprise, rôles, permissions, paramètres, modèles de messages, pages légales.
use App\Core\Database;

return function (): void {
    Database::insert('companies', ['id' => 1, 'name' => "Net'Express", 'slug' => 'net-express', 'active' => 1, 'created_at' => now()]);

    $perms = [
        'dashboard.view' => 'Voir le tableau de bord', 'bookings.view' => 'Voir les réservations', 'bookings.manage' => 'Gérer les réservations',
        'planning.view' => 'Voir le planning', 'catalog.manage' => 'Gérer le catalogue', 'geo.manage' => 'Gérer les zones',
        'staff.manage' => 'Gérer agents et équipes', 'customers.manage' => 'Gérer les clients', 'notifications.manage' => 'Gérer les notifications',
        'cms.manage' => 'Gérer les contenus du site', 'settings.manage' => 'Gérer les paramètres', 'users.manage' => 'Gérer les utilisateurs',
        'agent.missions' => 'Interface agent (missions)',
    ];
    $pid = [];
    foreach ($perms as $n => $l) $pid[$n] = Database::insert('permissions', ['name' => $n, 'label' => $l]);

    $roles = [
        'admin' => ['Administrateur', array_keys($perms)],
        'manager' => ['Gestionnaire', array_values(array_diff(array_keys($perms), ['settings.manage', 'users.manage', 'agent.missions']))],
        'agent' => ['Agent de terrain', ['agent.missions']],
    ];
    foreach ($roles as $name => [$label, $list]) {
        $rid = Database::insert('roles', ['name' => $name, 'label' => $label]);
        foreach ($list as $p) Database::insert('role_permissions', ['role_id' => $rid, 'permission_id' => $pid[$p]]);
    }

    $settings = [
        'company_name' => "Net'Express", 'company_phone' => '+221 77 000 00 00', 'company_whatsapp' => '221770000000', 'company_email' => 'contact@netexpress.sn',
        'company_address' => 'Dakar, Sénégal', 'opening_text' => 'Lundi – Samedi : 8h – 18h', 'currency' => 'FCFA',
        'hero_title' => 'Votre intérieur et votre voiture, comme neufs. Chez vous.', 'hero_subtitle' => 'Des équipes mobiles se déplacent à domicile ou au bureau. Réservez en deux minutes, choisissez votre créneau.',
        'hero_image' => '', 'slot_step_minutes' => '30', 'min_lead_hours' => '4', 'max_days_ahead' => '30', 'buffer_same_zone' => '10', 'default_travel_minutes' => '20',
        'revenue_tracking_enabled' => '1', 'reminder_hours_before' => '24', 'notif_max_attempts' => '4', 'notif_retry_minutes' => '10',
        'admin_notification_email' => 'contact@netexpress.sn', 'admin_notification_phone' => '',
    ];
    foreach ($settings as $k => $v) Database::insert('settings', ['company_id' => 1, 'skey' => $k, 'svalue' => $v]);

    // Modèles de messages. Variables : {client} {reference} {service} {date} {heure} {adresse} {total} {equipe} {statut} {entreprise} {telephone}
    $tpl = [
        ['booking.created', 'client', 'email', 'Réservation enregistrée — {reference}', "Bonjour {client},\n\nNous avons bien enregistré votre réservation {reference} : {service}, le {date} à {heure}.\nAdresse : {adresse}\nMontant estimé : {total}\n\nNous vous contacterons pour la confirmer. Pour la suivre : réf. {reference} + votre numéro de téléphone.\n\n{entreprise} — {telephone}"],
        ['booking.created', 'client', 'sms', null, "{entreprise}: réservation {reference} enregistrée pour le {date} à {heure}. Montant estimé {total}. Nous vous confirmons très vite."],
        ['booking.created', 'admin', 'email', 'Nouvelle réservation {reference}', "Nouvelle réservation {reference}\nClient : {client} ({telephone_client})\nService : {service}\nQuand : {date} à {heure}\nAdresse : {adresse}\nTotal estimé : {total}"],
        ['booking.confirmed', 'client', 'email', 'Réservation confirmée — {reference}', "Bonjour {client},\n\nVotre réservation {reference} est confirmée : {service}, {date} à {heure}.\nNotre équipe se présentera à : {adresse}.\n\n{entreprise} — {telephone}"],
        ['booking.confirmed', 'client', 'sms', null, "{entreprise}: réservation {reference} CONFIRMÉE le {date} à {heure}. À bientôt !"],
        ['booking.rescheduled', 'client', 'email', 'Nouveau créneau pour votre réservation {reference}', "Bonjour {client},\n\nVotre intervention {reference} est déplacée au {date} à {heure}.\n\n{entreprise} — {telephone}"],
        ['booking.rescheduled', 'client', 'sms', null, "{entreprise}: votre réservation {reference} est déplacée au {date} à {heure}."],
        ['booking.assigned', 'client', 'sms', null, "{entreprise}: l'équipe {equipe} interviendra pour votre réservation {reference} le {date} à {heure}."],
        ['booking.assigned', 'client', 'email', 'Équipe affectée — {reference}', "Bonjour {client},\n\nL'équipe {equipe} est affectée à votre réservation {reference} ({date} à {heure}).\n\n{entreprise} — {telephone}"],
        ['booking.reminder', 'client', 'sms', null, "{entreprise}: rappel, intervention {service} demain {date} à {heure} à votre adresse. Réf. {reference}."],
        ['booking.reminder', 'client', 'email', 'Rappel : intervention le {date} à {heure}', "Bonjour {client},\n\nPetit rappel : notre équipe passe le {date} à {heure} ({service}).\nAdresse : {adresse}\n\n{entreprise} — {telephone}"],
        ['booking.cancelled', 'client', 'email', 'Réservation annulée — {reference}', "Bonjour {client},\n\nVotre réservation {reference} a été annulée. Vous pouvez en créer une nouvelle à tout moment.\n\n{entreprise} — {telephone}"],
        ['booking.cancelled', 'client', 'sms', null, "{entreprise}: la réservation {reference} est annulée."],
        ['booking.cancelled', 'admin', 'email', 'Réservation annulée {reference}', "La réservation {reference} de {client} a été annulée ({date} à {heure})."],
        ['booking.status_changed', 'client', 'sms', null, "{entreprise}: réservation {reference} — statut : {statut}."],
        ['booking.completed', 'client', 'email', 'Intervention terminée — {reference}', "Bonjour {client},\n\nNotre intervention ({service}) est terminée. Merci de votre confiance !\n\n{entreprise} — {telephone}"],
        ['booking.completed', 'client', 'sms', null, "{entreprise}: intervention terminée, merci pour votre confiance !"],
    ];
    foreach ($tpl as [$ev, $aud, $ch, $subj, $body]) {
        Database::insert('notifications', ['company_id' => 1, 'event' => $ev, 'audience' => $aud, 'channel' => $ch, 'subject' => $subj, 'body' => $body, 'active' => 1]);
    }

    $pages = [
        ['a-propos', 'À propos', "Net'Express est une entreprise de nettoyage mobile : nous venons à vous pour laver votre véhicule, vos canapés, matelas, tapis, ainsi que votre maison ou vos bureaux.\n\nNos équipes sont formées, équipées et assurent un service soigné, ponctuel et transparent sur les prix."],
        ['cgv', 'Conditions générales', "1. Objet. Les présentes conditions encadrent les réservations de prestations de nettoyage effectuées sur ce site.\n\n2. Réservation. Une réservation est enregistrée « en attente » puis confirmée par l'entreprise. Le prix affiché est une estimation susceptible d'ajustement après constat sur place.\n\n3. Annulation. Le client peut annuler gratuitement jusqu'à 12 heures avant l'intervention.\n\n4. Règlement. Le mode de règlement est convenu avec l'entreprise ; aucun paiement n'est effectué en ligne.\n\n5. Responsabilité. L'entreprise est responsable des dommages directement causés par ses équipes, sous réserve de déclaration le jour de l'intervention.\n\n(Texte indicatif à faire valider par votre conseil juridique.)"],
        ['confidentialite', 'Politique de confidentialité', "Nous collectons les informations nécessaires à la réalisation de votre prestation : nom, téléphone, email éventuel et adresse d'intervention. Elles servent uniquement à gérer votre réservation et à vous contacter. Elles ne sont ni vendues ni cédées à des tiers.\n\nVous pouvez demander l'accès, la rectification ou la suppression de vos données en nous écrivant à l'adresse indiquée sur la page Contact.\n\n(Texte indicatif à adapter à la réglementation applicable, notamment la loi sénégalaise sur la protection des données personnelles.)"],
    ];
    foreach ($pages as [$slug, $title, $content]) Database::insert('pages', ['company_id' => 1, 'slug' => $slug, 'title' => $title, 'content' => $content, 'active' => 1, 'updated_at' => now()]);

    $faq = [
        ['Comment réserver ?', "Choisissez un service, précisez vos besoins, indiquez votre quartier, puis sélectionnez un créneau disponible. Vous recevez un numéro de référence immédiatement."],
        ['Dois-je payer en ligne ?', "Non. Aucun paiement n'est demandé sur le site. Le règlement se fait directement avec notre équipe, selon ce qui est convenu."],
        ['Pourquoi certains jours ne sont pas proposés dans mon quartier ?', "Nous regroupons nos interventions par zone, certains jours précis, pour réduire les trajets et garder des prix justes."],
        ['Puis-je modifier ou annuler ma réservation ?', "Oui : depuis la page « Suivre ma réservation » avec votre référence et votre téléphone, ou en nous appelant."],
        ['Combien de temps dure une intervention ?', "La durée estimée est affichée avant validation ; elle dépend de la formule, de la taille et des options choisies."],
    ];
    foreach ($faq as $i => [$q, $a]) Database::insert('faq', ['company_id' => 1, 'question' => $q, 'answer' => $a, 'sort' => $i, 'active' => 1]);
};
