<?php
// Lien de suivi personnel : le client suit sa réservation d'un clic, sans retaper référence et téléphone.
use App\Core\Database;

return function (): void {
    Database::pdo()->exec('ALTER TABLE bookings ADD COLUMN track_token VARCHAR(40) NULL');
    foreach (Database::all('SELECT id FROM bookings') as $r) Database::update('bookings', (int) $r['id'], ['track_token' => bin2hex(random_bytes(16))]);
    Database::pdo()->exec('CREATE UNIQUE INDEX uq_bookings_track_token ON bookings (track_token)');
    // Les messages « réservation enregistrée » et « confirmée » reçoivent le lien (sauf si le modèle le contient déjà).
    foreach (Database::all("SELECT id, channel, body FROM notifications WHERE audience = 'client' AND event IN ('booking.created', 'booking.confirmed') AND body NOT LIKE '%{lien_suivi}%'") as $t) {
        $add = $t['channel'] === 'sms' ? ' Suivi : {lien_suivi}' : "\n\nSuivre ma réservation en un clic : {lien_suivi}";
        Database::update('notifications', (int) $t['id'], ['body' => rtrim((string) $t['body']) . $add]);
    }
};
