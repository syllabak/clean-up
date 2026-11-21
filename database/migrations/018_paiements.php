<?php
// Paiement en ligne (PayTech, PayDunya) : une ligne par tentative de paiement, rattachée à la réservation.
use App\Core\{Database, Migrator};

return function (): void {
    $x = fn(string $sql) => Database::pdo()->exec(Migrator::translate($sql));
    $x("CREATE TABLE payments (id {{PK}}, company_id INT NOT NULL DEFAULT 1, booking_id INT NOT NULL, provider VARCHAR(12) NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL DEFAULT 'XOF',
        status VARCHAR(12) NOT NULL DEFAULT 'pending', ref VARCHAR(80) NOT NULL, token VARCHAR(120) NULL, checkout_url TEXT NULL, method VARCHAR(60) NULL, env VARCHAR(8) NULL,
        error VARCHAR(500) NULL, created_at DATETIME NOT NULL, paid_at DATETIME NULL, updated_at DATETIME NULL) {{ENGINE}}");
    $x('CREATE UNIQUE INDEX uq_payments_ref ON payments (ref)');
    $x('CREATE INDEX idx_payments_booking ON payments (booking_id)');
    $x('CREATE INDEX idx_payments_token ON payments (token)');
    $x('ALTER TABLE bookings ADD COLUMN paid_at DATETIME NULL');
};
