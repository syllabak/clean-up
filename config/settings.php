<?php
// Paramètres éditables dans l'administration : groupe => [clé => [libellé, type, aide]]
return [
    'Entreprise' => [
        'company_name' => ['Nom de l\'entreprise', 'text'], 'company_phone' => ['Téléphone affiché', 'text'], 'company_whatsapp' => ['WhatsApp (chiffres, avec indicatif)', 'text', 'Ex. 221770000000. Laisser vide pour masquer le bouton.'],
        'company_email' => ['Email de contact', 'email'], 'company_address' => ['Adresse', 'text'], 'opening_text' => ["Horaires d'ouverture (texte)", 'text'], 'currency' => ['Devise affichée', 'text'],
    ],
    'Page d\'accueil' => [
        'hero_title' => ['Titre principal', 'text'], 'hero_subtitle' => ['Sous-titre', 'textarea'], 'hero_image' => ['Image d\'accueil', 'image', 'JPEG, PNG ou WebP, 5 Mo maximum.'],
    ],
    'Réservation' => [
        'slot_step_minutes' => ['Pas des créneaux (minutes)', 'number', 'Intervalle entre deux heures de début proposées.'], 'min_lead_hours' => ['Délai minimum avant intervention (heures)', 'number'],
        'max_days_ahead' => ['Réservation possible jusqu\'à (jours)', 'number'], 'buffer_same_zone' => ['Battement dans un même quartier (minutes)', 'number'],
        'cancel_hours_before' => ['Annulation client possible jusqu\'à (heures avant)', 'number'],
        'auto_assign' => ['Affectation automatique à la création', 'bool', 'Choisit l\'agent le moins chargé de l\'équipe réservée. Désactivée par défaut.'],
        'revenue_tracking_enabled' => ['Suivi du chiffre d\'affaires saisi à la main', 'bool', 'Aucun paiement en ligne : le montant encaissé se saisit sur la réservation.'],
    ],
    'Notifications' => [
        'admin_notification_email' => ['Email qui reçoit les alertes', 'email'], 'admin_notification_phone' => ['Téléphone qui reçoit les SMS d\'alerte', 'text'],
        'reminder_hours_before' => ['Rappel client (heures avant l\'intervention)', 'number'], 'notif_max_attempts' => ['Tentatives maximum par envoi', 'number'], 'notif_retry_minutes' => ['Délai entre tentatives (minutes, x n° de tentative)', 'number'],
    ],
];
