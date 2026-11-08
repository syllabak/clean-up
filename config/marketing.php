<?php
// Canaux de communication et de publicité configurables dans l'administration.
// section => [title, intro, fields]. fields : clé => [libellé, type, aide, options?, validation?]
// types : text, secret (jamais réaffiché), select, bool, number, textarea. Valeurs enregistrées sous « mk_<clé> ».
return [
    'general' => [
        'title' => 'Règles générales', 'intro' => "Valables pour toutes les campagnes. Les campagnes ne partent que vers les clients qui ont accepté de recevoir des offres.",
        'fields' => [
            'optout_text' => ['Mention de désinscription ajoutée à chaque message', 'text', 'Ex. « Répondez STOP pour ne plus recevoir nos offres. »'],
            'batch_size' => ['Messages envoyés par passage de la tâche planifiée', 'number', 'Évite de saturer l\'API ou d\'être bloqué : 50 par défaut, toutes les 5 minutes.'],
        ],
    ],
    'email' => [
        'title' => 'Email', 'intro' => "Messages automatiques et campagnes. Laissez « Défaut du serveur » pour utiliser le fichier .env.",
        'fields' => [
            'email_driver' => ['Mode d\'envoi', 'select', '', ['' => 'Défaut du serveur (.env)', 'log' => 'Test : écrit dans un journal, n\'envoie rien', 'mail' => 'Fonction mail() de l\'hébergeur', 'smtp' => 'Serveur SMTP']],
            'email_host' => ['Serveur SMTP', 'text'], 'email_port' => ['Port', 'number', 'Souvent 587 (TLS) ou 465 (SSL).'],
            'email_encryption' => ['Chiffrement', 'select', '', ['' => 'Défaut du serveur', 'tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL']],
            'email_username' => ['Identifiant SMTP', 'text'], 'email_password' => ['Mot de passe SMTP', 'secret'],
            'email_from_address' => ['Adresse expéditeur', 'email'], 'email_from_name' => ['Nom expéditeur', 'text'],
        ],
        'test' => 'email',
    ],
    'sms' => [
        'title' => 'SMS', 'intro' => "Tout fournisseur qui propose une API HTTP (envoi par POST JSON) : Orange, Twilio, Infobip, Africa's Talking, etc.",
        'fields' => [
            'sms_driver' => ['Mode d\'envoi', 'select', '', ['' => 'Défaut du serveur (.env)', 'log' => 'Test : écrit dans un journal, n\'envoie rien', 'http' => 'API HTTP du fournisseur']],
            'sms_api_url' => ['Adresse de l\'API (https://…)', 'text'], 'sms_token' => ['Jeton / clé d\'API', 'secret', 'Envoyé en « Authorization: Bearer ».'],
            'sms_sender' => ['Nom d\'expéditeur', 'text', '11 caractères maximum chez la plupart des opérateurs.'],
            'sms_body_template' => ['Corps JSON envoyé', 'textarea', 'Variables : {to} {message} {sender}. Ex. {"to":"{to}","from":"{sender}","text":"{message}"}'],
        ],
        'test' => 'sms',
    ],
    'whatsapp' => [
        'title' => 'WhatsApp', 'intro' => "Deux façons de l'utiliser. Sans API : l'application prépare chaque message et vous l'envoyez d'un clic depuis WhatsApp (gratuit, manuel). Avec l'API officielle de Meta : envoi automatique, mais les messages lancés par l'entreprise doivent passer par un modèle approuvé par Meta.",
        'fields' => [
            'wa_mode' => ['Mode', 'select', '', ['off' => 'Désactivé', 'link' => 'Sans API : liens WhatsApp, envoi manuel', 'cloud' => 'Avec API : WhatsApp Business Cloud API (Meta)']],
            'wa_phone_id' => ['Identifiant du numéro (Phone number ID)', 'text', 'Meta for Developers → WhatsApp → Configuration de l\'API. Chiffres uniquement.'],
            'wa_token' => ['Jeton d\'accès permanent', 'secret'], 'wa_version' => ['Version de l\'API', 'text', 'Ex. v21.0'],
            'wa_template' => ['Nom du modèle de message approuvé', 'text', 'Obligatoire pour écrire à un client qui ne vous a pas écrit dans les dernières 24 h. Laissez vide pour envoyer du texte libre.'],
            'wa_template_lang' => ['Langue du modèle', 'text', 'Ex. fr'],
            'wa_template_params' => ['Variables du modèle, dans l\'ordre', 'text', 'Séparées par des virgules. Ex. {client},{message}. Disponibles : {client} {entreprise} {message} {reference} {date} {heure}.'],
        ],
        'test' => 'whatsapp',
    ],
    'meta' => [
        'title' => 'Facebook et Instagram Ads (Meta)', 'intro' => "Installe le pixel Meta sur le site (après accord du visiteur) et remonte chaque réservation à Meta côté serveur (API Conversions) pour optimiser vos publicités. La création des annonces se fait dans Meta Ads Manager.",
        'fields' => [
            'meta_enabled' => ['Activer le suivi Meta', 'bool'], 'meta_pixel_id' => ['Identifiant du pixel', 'text', 'Chiffres uniquement (Gestionnaire d\'événements).'],
            'meta_capi_token' => ['Jeton de l\'API Conversions', 'secret', 'Optionnel : active l\'envoi côté serveur.'], 'meta_test_code' => ['Code d\'événement de test', 'text', 'Ex. TEST12345, le temps de vérifier. À vider ensuite.'],
        ],
    ],
    'google' => [
        'title' => 'Google Ads et Analytics', 'intro' => "Installe la balise Google (après accord du visiteur) et enregistre une conversion à chaque réservation. La création des annonces se fait dans Google Ads.",
        'fields' => [
            'google_enabled' => ['Activer le suivi Google', 'bool'], 'google_ads_id' => ['Identifiant Google Ads', 'text', 'Format AW-123456789.'],
            'google_label_booking' => ['Libellé de conversion : réservation', 'text', 'Fourni par Google Ads (ex. AbC-dEfGh1).'], 'google_label_lead' => ['Libellé de conversion : demande de contact', 'text'],
            'google_ga4_id' => ['Identifiant Google Analytics 4', 'text', 'Format G-XXXXXXXXXX. Optionnel.'],
        ],
    ],
];
