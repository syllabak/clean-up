<?php
// Commandes : cinq statuts, position GPS de l'intervention, coordonnées des quartiers (pour détecter le quartier d'un client géolocalisé).
use App\Core\Database;

return function (): void {
    // « Affectée » et « En route » disparaissent : l'équipe affectée se lit à part, « en route » devient « en cours ».
    Database::exec("UPDATE bookings SET status = 'confirmed' WHERE status = 'assigned'");
    Database::exec("UPDATE bookings SET status = 'in_progress' WHERE status = 'en_route'");
    foreach (['lat DECIMAL(9,6) NULL', 'lng DECIMAL(9,6) NULL', 'location_source VARCHAR(10) NULL'] as $col) Database::pdo()->exec("ALTER TABLE bookings ADD COLUMN $col");
    foreach (['lat DECIMAL(9,6) NULL', 'lng DECIMAL(9,6) NULL'] as $col) Database::pdo()->exec("ALTER TABLE neighborhoods ADD COLUMN $col");
};
