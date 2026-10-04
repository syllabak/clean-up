<?php
declare(strict_types=1);
/**
 * Ressources administrables par le CRUD générique (/admin/crud/{ressource}).
 *
 * Options d'une ressource : title, table, permission, company (filtrer par company_id), soft_delete (colonne deleted_at),
 * order, search (colonnes de recherche), filters (clés de requête qui filtrent et pré-remplissent), list, fields, links,
 * can_create (défaut true), back (ressource parente pour le bouton retour).
 * Types de champ : text, textarea, number, decimal, select, bool, time, date, email, tel, password, image, weekdays, pivot.
 */
$active = ['label' => 'Actif', 'type' => 'bool', 'default' => 1];
$sort = ['label' => 'Ordre', 'type' => 'number', 'default' => 0, 'help' => 'Plus petit = affiché en premier.'];
$days = array_map(fn($d) => $d, DAYS_FR);

return [
    // ------------------------------------------------------------------ Catalogue
    'services' => [
        'title' => 'Services', 'table' => 'services', 'permission' => 'catalog.manage', 'company' => true, 'soft_delete' => true, 'order' => 'sort, name', 'search' => ['name'],
        'list' => ['name' => 'Nom', 'category_id' => ['Catégorie', 'ref', 'service_categories', 'name'], 'base_price' => ['Prix de base', 'money'], 'availability_status' => ['Statut', 'text'], 'active' => ['Actif', 'bool']],
        'links' => [['Formules', '/admin/crud/service_prices?service_id={id}'], ['Options', '/admin/crud/service_options?service_id={id}'], ['Champs', '/admin/crud/service_fields?service_id={id}']],
        'fields' => [
            'name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'slug' => ['label' => 'Adresse (slug)', 'type' => 'text', 'auto_slug' => 'name', 'help' => 'Générée à partir du nom si vide.'],
            'category_id' => ['label' => 'Catégorie', 'type' => 'select', 'ref' => ['service_categories', 'name', 'deleted_at IS NULL'], 'nullable' => true],
            'required_skill_id' => ['label' => 'Compétence requise', 'type' => 'select', 'ref' => ['skills', 'name', 'deleted_at IS NULL'], 'nullable' => true, 'help' => 'Seules les équipes comptant un agent compétent peuvent recevoir ce service.'],
            'short_description' => ['label' => 'Résumé', 'type' => 'text'], 'description' => ['label' => 'Description', 'type' => 'textarea'], 'image' => ['label' => 'Image', 'type' => 'image', 'dir' => 'services'],
            'base_price' => ['label' => 'Prix de base', 'type' => 'number', 'default' => 0, 'help' => "Utilisé s'il n'y a aucune formule."],
            'billing_unit' => ['label' => 'Unité de facturation (affichage)', 'type' => 'select', 'options' => ['fixed' => 'Forfait', 'per_item' => "À l'unité", 'per_sqm' => 'Au m²'], 'default' => 'fixed'],
            'base_duration' => ['label' => 'Durée de base (min)', 'type' => 'number', 'default' => 30], 'duration_per_unit' => ['label' => 'Minutes par m² supplémentaire', 'type' => 'number', 'default' => 0],
            'conditions' => ['label' => "Conditions d'intervention", 'type' => 'textarea'], 'featured' => ['label' => "Mis en avant sur l'accueil", 'type' => 'bool'],
            'availability_status' => ['label' => 'Disponibilité', 'type' => 'select', 'options' => ['available' => 'Réservable', 'unavailable' => 'Indisponible'], 'default' => 'available'], 'sort' => $sort, 'active' => $active,
        ],
    ],
    'service_categories' => [
        'title' => 'Catégories de services', 'table' => 'service_categories', 'permission' => 'catalog.manage', 'company' => true, 'soft_delete' => true, 'order' => 'sort, name',
        'list' => ['name' => 'Nom', 'sort' => 'Ordre', 'active' => ['Actif', 'bool']],
        'fields' => ['name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'slug' => ['label' => 'Slug', 'type' => 'text', 'auto_slug' => 'name'], 'sort' => $sort, 'active' => $active],
    ],
    'service_prices' => [
        'title' => 'Formules et tarifs', 'table' => 'service_prices', 'permission' => 'catalog.manage', 'soft_delete' => true, 'order' => 'sort, id', 'filters' => ['service_id'], 'back' => 'services',
        'list' => ['name' => 'Formule', 'amount' => ['Prix', 'money'], 'duration_minutes' => ['Durée (min)', 'text'], 'active' => ['Actif', 'bool']],
        'fields' => [
            'service_id' => ['label' => 'Service', 'type' => 'select', 'ref' => ['services', 'name', 'deleted_at IS NULL'], 'required' => true],
            'name' => ['label' => 'Nom de la formule', 'type' => 'text', 'required' => true], 'description' => ['label' => 'Description', 'type' => 'text'],
            'amount' => ['label' => 'Prix (par unité pour les services au m² ou à l\'unité)', 'type' => 'number', 'required' => true], 'duration_minutes' => ['label' => 'Durée ajoutée (min)', 'type' => 'number', 'default' => 0], 'sort' => $sort, 'active' => $active,
        ],
    ],
    'service_options' => [
        'title' => 'Options supplémentaires', 'table' => 'service_options', 'permission' => 'catalog.manage', 'soft_delete' => true, 'order' => 'sort, id', 'filters' => ['service_id'], 'back' => 'services',
        'list' => ['name' => 'Option', 'price' => ['Prix', 'money'], 'duration_minutes' => ['Durée (min)', 'text'], 'active' => ['Actif', 'bool']],
        'fields' => [
            'service_id' => ['label' => 'Service', 'type' => 'select', 'ref' => ['services', 'name', 'deleted_at IS NULL'], 'required' => true],
            'name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'description' => ['label' => 'Description', 'type' => 'text'],
            'price' => ['label' => 'Prix', 'type' => 'number', 'default' => 0], 'duration_minutes' => ['label' => 'Durée ajoutée (min)', 'type' => 'number', 'default' => 0], 'sort' => $sort, 'active' => $active,
        ],
    ],
    'service_fields' => [
        'title' => 'Champs du formulaire de réservation', 'table' => 'service_fields', 'permission' => 'catalog.manage', 'soft_delete' => true, 'order' => 'sort, id', 'filters' => ['service_id'], 'back' => 'services',
        'list' => ['label' => 'Libellé', 'type' => ['Type', 'text'], 'role' => ['Rôle', 'text'], 'required' => ['Obligatoire', 'bool'], 'active' => ['Actif', 'bool']],
        'links' => [['Choix', '/admin/crud/service_field_choices?field_id={id}']],
        'fields' => [
            'service_id' => ['label' => 'Service', 'type' => 'select', 'ref' => ['services', 'name', 'deleted_at IS NULL'], 'required' => true],
            'field_key' => ['label' => 'Identifiant technique', 'type' => 'text', 'required' => true, 'help' => 'Lettres minuscules et chiffres, sans espace (ex. vehicle).'],
            'label' => ['label' => 'Libellé affiché', 'type' => 'text', 'required' => true],
            'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['select' => 'Liste de choix', 'number' => 'Nombre', 'text' => 'Texte libre'], 'default' => 'select'],
            'role' => ['label' => 'Rôle dans le prix', 'type' => 'select', 'options' => ['none' => 'Aucun', 'quantity' => 'Quantité (multiplie le prix)', 'area' => 'Surface en m² (multiplie le prix)'], 'default' => 'none'],
            'required' => ['label' => 'Obligatoire', 'type' => 'bool', 'default' => 1], 'min_value' => ['label' => 'Minimum (nombre)', 'type' => 'number', 'nullable' => true], 'max_value' => ['label' => 'Maximum (nombre)', 'type' => 'number', 'nullable' => true],
            'help' => ['label' => 'Aide affichée au client', 'type' => 'text'], 'sort' => $sort, 'active' => $active,
        ],
    ],
    'service_field_choices' => [
        'title' => 'Choix d\'un champ', 'table' => 'service_field_choices', 'permission' => 'catalog.manage', 'soft_delete' => true, 'order' => 'sort, id', 'filters' => ['field_id'], 'back' => 'service_fields',
        'list' => ['label' => 'Choix', 'price_delta' => ['Supplément', 'money'], 'multiplier' => ['Coefficient', 'text'], 'duration_delta' => ['Durée (min)', 'text'], 'active' => ['Actif', 'bool']],
        'fields' => [
            'field_id' => ['label' => 'Champ', 'type' => 'select', 'ref' => ['service_fields', 'label', 'deleted_at IS NULL'], 'required' => true],
            'label' => ['label' => 'Libellé', 'type' => 'text', 'required' => true], 'price_delta' => ['label' => 'Supplément de prix', 'type' => 'number', 'default' => 0],
            'multiplier' => ['label' => 'Coefficient multiplicateur', 'type' => 'decimal', 'default' => '1', 'help' => 'Ex. 1,4 pour +40 %.'], 'duration_delta' => ['label' => 'Durée ajoutée (min)', 'type' => 'number', 'default' => 0], 'sort' => $sort, 'active' => $active,
        ],
    ],
    'skills' => [
        'title' => 'Compétences', 'table' => 'skills', 'permission' => 'staff.manage', 'company' => true, 'soft_delete' => true, 'order' => 'name', 'list' => ['name' => 'Compétence'],
        'fields' => ['name' => ['label' => 'Nom', 'type' => 'text', 'required' => true]],
    ],

    // ------------------------------------------------------------------ Zones
    'regions' => [
        'title' => 'Régions', 'table' => 'regions', 'permission' => 'geo.manage', 'company' => true, 'soft_delete' => true, 'order' => 'name', 'list' => ['name' => 'Région', 'active' => ['Actif', 'bool']],
        'links' => [['Villes', '/admin/crud/cities?region_id={id}']], 'fields' => ['name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'active' => $active],
    ],
    'cities' => [
        'title' => 'Villes', 'table' => 'cities', 'permission' => 'geo.manage', 'company' => true, 'soft_delete' => true, 'order' => 'name', 'filters' => ['region_id'],
        'list' => ['name' => 'Ville', 'region_id' => ['Région', 'ref', 'regions', 'name'], 'active' => ['Actif', 'bool']], 'links' => [['Communes', '/admin/crud/communes?city_id={id}']],
        'fields' => ['region_id' => ['label' => 'Région', 'type' => 'select', 'ref' => ['regions', 'name', 'deleted_at IS NULL'], 'required' => true], 'name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'active' => $active],
    ],
    'communes' => [
        'title' => 'Communes', 'table' => 'communes', 'permission' => 'geo.manage', 'company' => true, 'soft_delete' => true, 'order' => 'name', 'filters' => ['city_id'],
        'list' => ['name' => 'Commune', 'city_id' => ['Ville', 'ref', 'cities', 'name'], 'active' => ['Actif', 'bool']], 'links' => [['Quartiers', '/admin/crud/neighborhoods?commune_id={id}']],
        'fields' => ['city_id' => ['label' => 'Ville', 'type' => 'select', 'ref' => ['cities', 'name', 'deleted_at IS NULL'], 'required' => true], 'name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'active' => $active],
    ],
    'neighborhoods' => [
        'title' => 'Quartiers et disponibilités par zone', 'table' => 'neighborhoods', 'permission' => 'geo.manage', 'company' => true, 'soft_delete' => true, 'order' => 'name', 'filters' => ['commune_id'], 'search' => ['name'],
        'list' => ['name' => 'Quartier', 'commune_id' => ['Commune', 'ref', 'communes', 'name'], 'travel_fee' => ['Déplacement', 'money'], 'intervention_days' => ['Jours', 'weekdays'], 'active' => ['Actif', 'bool']],
        'fields' => [
            'commune_id' => ['label' => 'Commune', 'type' => 'select', 'ref' => ['communes', 'name', 'deleted_at IS NULL'], 'required' => true], 'name' => ['label' => 'Nom du quartier', 'type' => 'text', 'required' => true],
            'travel_fee' => ['label' => 'Frais de déplacement', 'type' => 'number', 'default' => 0], 'avg_travel_minutes' => ['label' => 'Temps de trajet moyen (min)', 'type' => 'number', 'default' => 20, 'help' => 'Sert à espacer les interventions entre quartiers.'],
            'intervention_days' => ['label' => "Jours d'intervention", 'type' => 'weekdays', 'help' => 'Aucun jour coché = tous les jours.'],
            'slot_start' => ['label' => 'Plage horaire : début', 'type' => 'time', 'empty_string' => true, 'help' => 'Optionnel : limite les créneaux dans ce quartier.'], 'slot_end' => ['label' => 'Plage horaire : fin', 'type' => 'time', 'empty_string' => true],
            'conditions' => ['label' => "Conditions d'accès", 'type' => 'textarea'], 'sort' => $sort, 'active' => $active,
            'teams' => ['label' => 'Équipes desservant ce quartier', 'type' => 'pivot', 'table' => 'neighborhood_teams', 'key' => 'neighborhood_id', 'other' => 'team_id', 'ref' => ['teams', 'name', 'deleted_at IS NULL'], 'help' => 'Aucune équipe cochée = toutes les équipes compétentes.'],
        ],
    ],

    // ------------------------------------------------------------------ Équipes
    'teams' => [
        'title' => 'Équipes', 'table' => 'teams', 'permission' => 'staff.manage', 'company' => true, 'soft_delete' => true, 'order' => 'name',
        'list' => ['name' => 'Équipe', 'vehicle' => 'Véhicule', 'daily_capacity' => ['Capacité / jour', 'text'], 'status' => ['Statut', 'text']],
        'links' => [['Horaires', '/admin/crud/working_hours?team_id={id}'], ['Agents', '/admin/crud/staff?team_id={id}']],
        'fields' => [
            'name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'vehicle' => ['label' => 'Véhicule', 'type' => 'text'],
            'main_commune_id' => ['label' => 'Zone principale', 'type' => 'select', 'ref' => ['communes', 'name', 'deleted_at IS NULL'], 'nullable' => true],
            'daily_capacity' => ['label' => "Interventions maximum par jour", 'type' => 'number', 'default' => 6, 'required' => true],
            'status' => ['label' => 'Statut', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'default' => 'active'], 'notes' => ['label' => 'Notes', 'type' => 'textarea'],
            'services' => ['label' => 'Services pris en charge', 'type' => 'pivot', 'table' => 'team_services', 'key' => 'team_id', 'other' => 'service_id', 'ref' => ['services', 'name', 'deleted_at IS NULL']],
        ],
    ],
    'staff' => [
        'title' => 'Agents', 'table' => 'staff', 'permission' => 'staff.manage', 'company' => true, 'soft_delete' => true, 'order' => 'name', 'filters' => ['team_id'], 'search' => ['name', 'phone'],
        'list' => ['name' => 'Agent', 'team_id' => ['Équipe', 'ref', 'teams', 'name'], 'phone' => 'Téléphone', 'status' => ['Statut', 'text']],
        'fields' => [
            'name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'phone' => ['label' => 'Téléphone', 'type' => 'tel'], 'email' => ['label' => 'Email', 'type' => 'email'],
            'team_id' => ['label' => 'Équipe', 'type' => 'select', 'ref' => ['teams', 'name', 'deleted_at IS NULL'], 'nullable' => true],
            'status' => ['label' => 'Statut', 'type' => 'select', 'options' => ['active' => 'Actif', 'inactive' => 'Inactif'], 'default' => 'active'],
            'skills' => ['label' => 'Compétences', 'type' => 'pivot', 'table' => 'staff_skills', 'key' => 'staff_id', 'other' => 'skill_id', 'ref' => ['skills', 'name', 'deleted_at IS NULL']],
        ],
    ],
    'working_hours' => [
        'title' => 'Horaires de travail', 'table' => 'working_hours', 'permission' => 'staff.manage', 'company' => true, 'order' => 'team_id, day_of_week', 'filters' => ['team_id'], 'back' => 'teams',
        'list' => ['team_id' => ['Équipe', 'ref', 'teams', 'name'], 'day_of_week' => ['Jour', 'day'], 'start_time' => 'Début', 'end_time' => 'Fin', 'break_start' => 'Pause de', 'break_end' => 'à'],
        'fields' => [
            'team_id' => ['label' => 'Équipe', 'type' => 'select', 'ref' => ['teams', 'name', 'deleted_at IS NULL'], 'required' => true], 'day_of_week' => ['label' => 'Jour', 'type' => 'select', 'options' => $days, 'required' => true],
            'start_time' => ['label' => 'Début', 'type' => 'time', 'required' => true, 'default' => '08:00'], 'end_time' => ['label' => 'Fin', 'type' => 'time', 'required' => true, 'default' => '18:00'],
            'break_start' => ['label' => 'Début de pause', 'type' => 'time'], 'break_end' => ['label' => 'Fin de pause', 'type' => 'time'],
        ],
    ],
    'time_off' => [
        'title' => 'Congés et fermetures', 'table' => 'time_off', 'permission' => 'staff.manage', 'company' => true, 'soft_delete' => true, 'order' => 'start_date DESC',
        'list' => ['start_date' => 'Du', 'end_date' => 'Au', 'team_id' => ['Équipe', 'ref', 'teams', 'name'], 'staff_id' => ['Agent', 'ref', 'staff', 'name'], 'reason' => 'Motif'],
        'fields' => [
            'team_id' => ['label' => 'Équipe', 'type' => 'select', 'ref' => ['teams', 'name', 'deleted_at IS NULL'], 'nullable' => true, 'help' => "Équipe ET agent vides = fermeture de toute l'entreprise (jour férié)."],
            'staff_id' => ['label' => 'Agent', 'type' => 'select', 'ref' => ['staff', 'name', 'deleted_at IS NULL'], 'nullable' => true],
            'start_date' => ['label' => 'Du', 'type' => 'date', 'required' => true], 'end_date' => ['label' => 'Au (inclus)', 'type' => 'date', 'required' => true], 'reason' => ['label' => 'Motif', 'type' => 'text'],
        ],
    ],

    // ------------------------------------------------------------------ Clients
    'customers' => [
        'title' => 'Clients', 'table' => 'customers', 'permission' => 'customers.manage', 'company' => true, 'soft_delete' => true, 'order' => 'id DESC', 'search' => ['first_name', 'last_name', 'phone', 'email'],
        'list' => ['last_name' => 'Nom', 'first_name' => 'Prénom', 'phone' => 'Téléphone', 'email' => 'Email'], 'links' => [['Adresses', '/admin/crud/customer_addresses?customer_id={id}'], ['Réservations', '/admin/reservations?q={phone}']],
        'fields' => ['first_name' => ['label' => 'Prénom', 'type' => 'text', 'required' => true], 'last_name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'phone' => ['label' => 'Téléphone', 'type' => 'tel', 'required' => true, 'normalize' => 'phone'],
            'email' => ['label' => 'Email', 'type' => 'email'], 'notes' => ['label' => 'Notes internes', 'type' => 'textarea']],
    ],
    'customer_addresses' => [
        'title' => 'Adresses de clients', 'table' => 'customer_addresses', 'permission' => 'customers.manage', 'soft_delete' => true, 'order' => 'id DESC', 'filters' => ['customer_id'], 'back' => 'customers',
        'list' => ['customer_id' => ['Client', 'ref', 'customers', 'last_name'], 'neighborhood_id' => ['Quartier', 'ref', 'neighborhoods', 'name'], 'address_text' => 'Adresse'],
        'fields' => ['customer_id' => ['label' => 'Client', 'type' => 'select', 'ref' => ['customers', 'last_name', 'deleted_at IS NULL'], 'required' => true],
            'neighborhood_id' => ['label' => 'Quartier', 'type' => 'select', 'ref' => ['neighborhoods', 'name', 'deleted_at IS NULL'], 'nullable' => true],
            'address_text' => ['label' => 'Adresse', 'type' => 'textarea', 'required' => true], 'instructions' => ['label' => "Instructions d'accès", 'type' => 'textarea']],
    ],

    // ------------------------------------------------------------------ Messages et contenus
    'notifications' => [
        'title' => 'Modèles de notifications', 'table' => 'notifications', 'permission' => 'notifications.manage', 'company' => true, 'order' => 'event, audience, channel',
        'list' => ['event' => ['Événement', 'event'], 'audience' => ['Destinataire', 'text'], 'channel' => ['Canal', 'text'], 'active' => ['Actif', 'bool']],
        'fields' => [
            'event' => ['label' => 'Événement', 'type' => 'select', 'options' => \App\Services\NotificationService::EVENTS, 'required' => true],
            'audience' => ['label' => 'Destinataire', 'type' => 'select', 'options' => ['client' => 'Client', 'admin' => "Administration"], 'default' => 'client'],
            'channel' => ['label' => 'Canal', 'type' => 'select', 'options' => ['email' => 'Email', 'sms' => 'SMS'], 'required' => true],
            'subject' => ['label' => 'Objet (email)', 'type' => 'text'],
            'body' => ['label' => 'Message', 'type' => 'textarea', 'required' => true, 'help' => 'Variables : {client} {reference} {service} {date} {heure} {adresse} {total} {equipe} {statut} {entreprise} {telephone} {telephone_client}'],
            'active' => $active,
        ],
    ],
    'faq' => [
        'title' => 'Questions fréquentes', 'table' => 'faq', 'permission' => 'cms.manage', 'company' => true, 'order' => 'sort, id', 'list' => ['question' => 'Question', 'sort' => 'Ordre', 'active' => ['Actif', 'bool']],
        'fields' => ['question' => ['label' => 'Question', 'type' => 'text', 'required' => true], 'answer' => ['label' => 'Réponse', 'type' => 'textarea', 'required' => true], 'sort' => $sort, 'active' => $active],
    ],
    'testimonials' => [
        'title' => 'Témoignages', 'table' => 'testimonials', 'permission' => 'cms.manage', 'company' => true, 'order' => 'sort, id', 'list' => ['name' => 'Nom', 'rating' => ['Note', 'text'], 'active' => ['Actif', 'bool']],
        'fields' => ['name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'content' => ['label' => 'Témoignage', 'type' => 'textarea', 'required' => true], 'rating' => ['label' => 'Note (1 à 5)', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 5], 'sort' => $sort, 'active' => $active],
    ],
    'pages' => [
        'title' => 'Pages (à propos, conditions…)', 'table' => 'pages', 'permission' => 'cms.manage', 'company' => true, 'order' => 'title', 'list' => ['title' => 'Titre', 'slug' => 'Adresse', 'active' => ['Actif', 'bool']], 'can_create' => false,
        'fields' => ['title' => ['label' => 'Titre', 'type' => 'text', 'required' => true], 'content' => ['label' => 'Contenu (les sauts de ligne sont conservés)', 'type' => 'textarea', 'required' => true, 'rows' => 14], 'active' => $active],
    ],
    'contact_messages' => [
        'title' => 'Messages reçus', 'table' => 'contact_messages', 'permission' => 'cms.manage', 'company' => true, 'order' => 'id DESC', 'can_create' => false,
        'list' => ['created_at' => ['Reçu le', 'datetime'], 'name' => 'Nom', 'phone' => 'Téléphone', 'email' => 'Email', 'message' => ['Message', 'excerpt'], 'handled' => ['Traité', 'bool']],
        'fields' => ['name' => ['label' => 'Nom', 'type' => 'text', 'readonly' => true], 'email' => ['label' => 'Email', 'type' => 'text', 'readonly' => true], 'phone' => ['label' => 'Téléphone', 'type' => 'text', 'readonly' => true],
            'message' => ['label' => 'Message', 'type' => 'textarea', 'readonly' => true], 'handled' => ['label' => 'Marqué comme traité', 'type' => 'bool']],
    ],
    'users' => [
        'title' => 'Utilisateurs', 'table' => 'users', 'permission' => 'users.manage', 'company' => true, 'soft_delete' => true, 'order' => 'name', 'search' => ['name', 'email'],
        'list' => ['name' => 'Nom', 'email' => 'Email', 'role_id' => ['Rôle', 'ref', 'roles', 'label'], 'last_login_at' => ['Dernière connexion', 'datetime'], 'active' => ['Actif', 'bool']],
        'fields' => [
            'name' => ['label' => 'Nom', 'type' => 'text', 'required' => true], 'email' => ['label' => 'Email (identifiant)', 'type' => 'email', 'required' => true, 'lower' => true],
            'role_id' => ['label' => 'Rôle', 'type' => 'select', 'ref' => ['roles', 'label', ''], 'required' => true],
            'staff_id' => ['label' => 'Agent associé (pour les comptes agent)', 'type' => 'select', 'ref' => ['staff', 'name', 'deleted_at IS NULL'], 'nullable' => true, 'help' => "Obligatoire pour qu'un agent voie ses missions."],
            'phone' => ['label' => 'Téléphone', 'type' => 'tel'],
            'password' => ['label' => 'Mot de passe', 'type' => 'password', 'column' => 'password_hash', 'minlen' => 10, 'help' => 'Au moins 10 caractères. Laisser vide pour ne pas le changer.'], 'active' => $active,
        ],
    ],
];
