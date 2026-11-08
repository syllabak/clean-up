<?php
// Marketing : campagnes (email, SMS, WhatsApp), suivi des publicités (UTM, Facebook, Google), WhatsApp pour les notifications.
use App\Core\{Database, Migrator};

return function (): void {
    $x = fn(string $sql) => Database::pdo()->exec(Migrator::translate($sql));

    $x("CREATE TABLE marketing_campaigns (id {{PK}}, company_id INT NOT NULL DEFAULT 1, name VARCHAR(150) NOT NULL, channel VARCHAR(20) NOT NULL, audience VARCHAR(30) NOT NULL DEFAULT 'consent',
        audience_channel_id INT NULL, subject VARCHAR(190) NULL, body TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', scheduled_at DATETIME NULL, created_by INT NULL,
        created_at DATETIME NOT NULL, started_at DATETIME NULL, finished_at DATETIME NULL) {{ENGINE}}");
    $x("CREATE TABLE marketing_messages (id {{PK}}, campaign_id INT NOT NULL, customer_id INT NOT NULL, recipient VARCHAR(190) NOT NULL, content TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending', error VARCHAR(500) NULL, attempts INT NOT NULL DEFAULT 0, sent_at DATETIME NULL, created_at DATETIME NOT NULL) {{ENGINE}}");
    $x('CREATE UNIQUE INDEX uq_mm_campaign_customer ON marketing_messages (campaign_id, customer_id)');
    $x('CREATE INDEX idx_mm_status ON marketing_messages (campaign_id, status)');
    $x("CREATE TABLE marketing_events (id {{PK}}, company_id INT NOT NULL DEFAULT 1, provider VARCHAR(20) NOT NULL, event VARCHAR(40) NOT NULL, ref VARCHAR(60) NULL,
        status VARCHAR(10) NOT NULL, error VARCHAR(500) NULL, created_at DATETIME NOT NULL) {{ENGINE}}");

    // Origine publicitaire de chaque réservation et de chaque prospect.
    foreach (['bookings', 'leads'] as $t) {
        foreach (['utm_source VARCHAR(80)', 'utm_medium VARCHAR(80)', 'utm_campaign VARCHAR(120)', 'fbclid VARCHAR(255)', 'gclid VARCHAR(255)'] as $col) $x("ALTER TABLE $t ADD COLUMN $col NULL");
    }

    // Permission et canaux d'acquisition liés au marketing.
    $pid = Database::insert('permissions', ['name' => 'marketing.manage', 'label' => 'Gérer le marketing (campagnes, publicités)']);
    foreach (['admin', 'manager'] as $role) {
        $rid = Database::val('SELECT id FROM roles WHERE name = ?', [$role]);
        if ($rid) Database::insert('role_permissions', ['role_id' => $rid, 'permission_id' => $pid]);
    }
    $sort = (int) Database::val('SELECT COALESCE(MAX(sort), 0) FROM acquisition_channels') + 1;
    foreach ([['google', 'Google (Ads, recherche)', 'payant'], ['email', 'Campagne email', 'direct'], ['sms', 'Campagne SMS', 'direct']] as [$code, $name, $kind]) {
        if (!Database::val('SELECT id FROM acquisition_channels WHERE code = ?', [$code])) {
            Database::insert('acquisition_channels', ['company_id' => 1, 'code' => $code, 'name' => $name, 'kind' => $kind, 'public' => 0, 'active' => 1, 'sort' => $sort++]);
        }
    }

    // Modèles WhatsApp pour les notifications de réservation (actifs seulement si WhatsApp est en mode « API »).
    $tpl = [
        ['booking.created', "Bonjour {client}, votre réservation {reference} est enregistrée : {service}, le {date} à {heure}. Nous vous confirmons très vite. {entreprise}"],
        ['booking.confirmed', "Bonjour {client}, votre réservation {reference} est CONFIRMÉE : {service}, le {date} à {heure}. À bientôt ! {entreprise}"],
        ['booking.reminder', "Bonjour {client}, rappel : notre équipe passe le {date} à {heure} ({service}). Réf. {reference}. {entreprise}"],
    ];
    foreach ($tpl as [$ev, $body]) {
        Database::insert('notifications', ['company_id' => 1, 'event' => $ev, 'audience' => 'client', 'channel' => 'whatsapp', 'subject' => null, 'body' => $body, 'active' => 1]);
    }
};
