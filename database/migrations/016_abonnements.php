<?php
// Abonnements : offres (formules d'abonnement), souscriptions des clients et des entreprises, et passages générés dans le planning.
use App\Core\{Database, Migrator};

return function (): void {
    $x = fn(string $sql) => Database::pdo()->exec(Migrator::translate($sql));

    $x("CREATE TABLE subscription_plans (id {{PK}}, company_id INT NOT NULL DEFAULT 1, service_id INT NOT NULL, name VARCHAR(150) NOT NULL, audience VARCHAR(10) NOT NULL DEFAULT 'all',
        frequency VARCHAR(12) NOT NULL DEFAULT 'weekly', discount_percent INT NOT NULL DEFAULT 0, description TEXT NULL, active INT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0,
        created_at DATETIME NULL, deleted_at DATETIME NULL) {{ENGINE}}");

    $x("CREATE TABLE subscriptions (id {{PK}}, company_id INT NOT NULL DEFAULT 1, plan_id INT NOT NULL, customer_id INT NOT NULL, kind VARCHAR(10) NOT NULL DEFAULT 'person', business_name VARCHAR(150) NULL,
        service_id INT NOT NULL, neighborhood_id INT NOT NULL, address_text VARCHAR(500) NOT NULL, instructions VARCHAR(1000) NULL, frequency VARCHAR(12) NOT NULL, weekday INT NOT NULL,
        start_time VARCHAR(5) NOT NULL, start_date DATE NOT NULL, discount_percent INT NOT NULL DEFAULT 0, inputs TEXT NULL, status VARCHAR(12) NOT NULL DEFAULT 'requested',
        paused_until DATE NULL, notes TEXT NULL, admin_notes TEXT NULL, track_token VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL,
        activated_at DATETIME NULL, cancelled_at DATETIME NULL, cancel_reason VARCHAR(255) NULL) {{ENGINE}}");
    $x('CREATE UNIQUE INDEX uq_subscriptions_token ON subscriptions (track_token)');
    $x('CREATE INDEX idx_subscriptions_status ON subscriptions (company_id, status)');

    // Un passage = une date de l'abonnement. La contrainte d'unicité évite deux réservations pour la même date, même si deux générations se chevauchent.
    $x("CREATE TABLE subscription_visits (id {{PK}}, subscription_id INT NOT NULL, visit_date DATE NOT NULL, booking_id INT NULL, status VARCHAR(12) NOT NULL DEFAULT 'booked',
        note VARCHAR(255) NULL, created_at DATETIME NOT NULL) {{ENGINE}}");
    $x('CREATE UNIQUE INDEX uq_subscription_visits ON subscription_visits (subscription_id, visit_date)');

    $x('ALTER TABLE bookings ADD COLUMN subscription_id INT NULL');
    $x('ALTER TABLE bookings ADD COLUMN discount INT NOT NULL DEFAULT 0');
    $x('CREATE INDEX idx_bookings_subscription ON bookings (subscription_id)');

    $pid = Database::insert('permissions', ['name' => 'subscriptions.manage', 'label' => 'Gérer les abonnements (demandes, offres, passages)']);
    foreach (['admin', 'manager'] as $role) {
        $rid = Database::val('SELECT id FROM roles WHERE name = ?', [$role]);
        if ($rid) Database::insert('role_permissions', ['role_id' => $rid, 'permission_id' => $pid]);
    }
};
