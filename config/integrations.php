<?php
// Messagerie et paiement, réglés dans l'administration (Configuration → Messagerie et paiement).
// Même format que config/marketing.php ; les valeurs sont enregistrées sous « mk_<clé> » et les secrets ne sont jamais réaffichés.
$m = require __DIR__ . '/marketing.php';
return [
    'email' => $m['email'],
    'sms' => $m['sms'],
    'payment' => [
        'title' => 'Paiement en ligne', 'intro' => "Par défaut, le client règle l'équipe après l'intervention. Vous pouvez en plus lui proposer de payer en ligne (Wave, Orange Money, carte…) dès la réservation : activez ci-dessous au moins un prestataire.",
        'fields' => [
            'pay_mode' => ['Règlement', 'select', 'Un client peut toujours choisir de payer à l\'équipe : le paiement en ligne n\'est jamais obligatoire.', ['after' => "Aucun paiement en ligne : le client règle à l'équipe après l'intervention", 'optional' => 'Paiement en ligne proposé après la réservation (en plus du règlement à l\'équipe)']],
        ],
    ],
    'paytech' => [
        'title' => 'PayTech', 'intro' => "PayTech Sénégal (paytech.sn) : Orange Money, Wave, Free Money, carte bancaire. Vos clés se trouvent dans le tableau de bord PayTech, Paramètres → API. Commencez en mode Test : tant que votre compte n'est pas activé en production par PayTech, seul le mode Test fonctionne et il ne débite que 100 à 150 FCFA.",
        'fields' => [
            'paytech_enabled' => ['Proposer PayTech', 'bool'],
            'paytech_env' => ['Environnement', 'select', '', ['test' => 'Test (sandbox)', 'prod' => 'Production (compte activé par PayTech)']],
            'paytech_api_key' => ['Clé API (API_KEY)', 'secret'], 'paytech_api_secret' => ['Clé secrète (API_SECRET)', 'secret', 'Sert aussi à vérifier que les notifications de paiement viennent bien de PayTech.'],
            'paytech_methods' => ['Méthodes proposées', 'text', 'Ex. Orange Money, Wave, Free Money. Laissez vide pour proposer toutes les méthodes de votre compte.'],
        ],
        'test' => 'connexion', 'webhook' => '/webhooks/paytech', 'webhook_label' => "Notification de paiement (IPN) : à saisir comme adresse de notification si PayTech la demande. L'application l'envoie déjà avec chaque paiement",
    ],
    'paydunya' => [
        'title' => 'PayDunya', 'intro' => "PayDunya : Wave, Orange Money, Free Money, Expresso, carte bancaire. Dans votre compte PayDunya Business : « Intégrez notre API », créez une application et copiez les trois clés. Commencez en mode Test (clés de test), puis passez en production avec les clés de production.",
        'fields' => [
            'paydunya_enabled' => ['Proposer PayDunya', 'bool'],
            'paydunya_mode' => ['Mode', 'select', '', ['test' => 'Test (clés de test)', 'live' => 'Production (clés de production)']],
            'paydunya_master_key' => ['Clé principale (Master Key)', 'secret', 'Sert aussi à vérifier que les notifications viennent bien de PayDunya.'], 'paydunya_private_key' => ['Clé privée (Private Key)', 'secret'], 'paydunya_token' => ['Jeton (Token)', 'secret'],
            'paydunya_channels' => ['Moyens de paiement proposés', 'text', 'Ex. wave-senegal, orange-money-senegal, free-money-senegal, card. Laissez vide pour proposer tous ceux de votre compte.'],
        ],
        'test' => 'connexion', 'webhook' => '/webhooks/paydunya', 'webhook_label' => "Notification de paiement (IPN) : l'application l'envoie déjà avec chaque paiement ; à renseigner chez PayDunya seulement si vous voulez la fixer aussi dans leur tableau de bord",
    ],
];
