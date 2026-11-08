<?php
// Jeu de données d'exemple pour les captures des guides : à lancer sur une base VIDE déjà migrée et chargée par bin/seed-dakar.php.
// Usage : DB_SQLITE_PATH=/tmp/guide.db php docs/guides/seed-demo.php [dossier des photos d'exemple]
// Les clients, numéros et photos sont fictifs (illustrations).
require __DIR__ . '/../../bootstrap.php';

use App\Core\Database as DB;
use App\Services\BookingService;

$photoDir = $argv[1] ?? '/tmp/guide-photos';

// Comptes de l'exemple : un administrateur et un gestionnaire
$roles = array_column(DB::all('SELECT id, name FROM roles'), 'id', 'name');
DB::insert('users', ['role_id' => $roles['admin'], 'name' => 'Aminata Seck', 'email' => 'admin@exemple.sn', 'password_hash' => password_hash('Guide#2026-demo', PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now()]);
DB::insert('users', ['role_id' => $roles['manager'], 'name' => 'Ousmane Kane', 'email' => 'gestion@exemple.sn', 'password_hash' => password_hash('Guide#2026-demo', PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now()]);
setting_set('company_name', 'Clean Up'); setting_set('company_phone', '+221 77 456 78 90'); setting_set('company_email', 'contact@exemple.sn'); setting_set('company_whatsapp', '221774567890');

$svc = fn(string $slug) => (int) DB::val('SELECT id FROM services WHERE slug = ?', [$slug]);
$hood = fn(string $n) => (int) DB::val('SELECT id FROM neighborhoods WHERE name = ?', [$n]);
$formula = fn(int $s, string $n) => (int) DB::val('SELECT id FROM service_prices WHERE service_id = ? AND name = ?', [$s, $n]);
$choice = fn(int $s, string $key, string $label) => (int) DB::val('SELECT c.id FROM service_field_choices c JOIN service_fields f ON f.id = c.field_id WHERE f.service_id = ? AND f.field_key = ? AND c.label = ?', [$s, $key, $label]);

/** Prochain jour ouvré (lundi à samedi) à $plus jours ou plus. */
$day = function (int $plus): string { for ($i = $plus; $i < $plus + 8; $i++) { $t = strtotime("+$i day"); if ((int) date('N', $t) <= 6) return date('Y-m-d', $t); } return date('Y-m-d', strtotime("+$plus day")); };

$orders = [
    // [service, formule, champs, quartier, nom, prénom, téléphone, adresse, lat, lng, +jours, heure, statut, photos]
    ['nettoyage-canapes-fauteuils', null, ['seats' => 'Canapé 3 places', 'material' => 'Velours'], 'Almadies', 'Ndiaye', 'Moussa', '77 301 22 18', 'Villa 14, route des Almadies, près de la station', 14.7452, -17.5238, 1, '09:00', 'pending', ['canape-1', 'canape-2', 'canape-3']],
    ['nettoyage-matelas', null, ['size' => '2 places (140 cm)', 'count' => 1], 'Mermoz', 'Sow', 'Fatou', '78 455 90 31', 'Résidence Les Palmiers, 3e étage', 14.7098, -17.4712, 1, '11:00', 'confirmed', ['matelas']],
    ['nettoyage-tapis-moquettes', 'Tapis', ['area' => 12], 'Ouakam', 'Fall', 'Cheikh', '77 889 14 02', 'Rue OK 14, Ouakam village', 14.7222, -17.4881, 2, '09:00', 'in_progress', ['tapis']],
    ['menage-domicile', 'Standard', ['housing' => 'F3'], 'Plateau', 'Ba', 'Aïssatou', '76 120 33 47', 'Avenue Pompidou, immeuble Fayçal, 2e étage', 14.6712, -17.4391, 2, '14:00', 'completed', []],
    ['nettoyage-vitres', null, ['type' => 'Baie vitrée', 'count' => 3], 'Yoff', 'Sarr', 'Ibrahima', '77 702 65 90', 'Yoff Layène, villa bleue en face de la mosquée', 14.7601, -17.4742, 3, '10:00', 'pending', ['vitres']],
    ['lavage-automobile', 'Complet', ['vehicle' => 'SUV / 4x4', 'count' => 1], 'Sicap Liberté', 'Thiam', 'Mariama', '70 543 11 28', 'Sicap Liberté 4, villa 4512', 14.7240, -17.4602, 3, '08:30', 'cancelled', ['voiture']],
];

$svcB = new BookingService(); $n = 0;
foreach ($orders as [$slug, $form, $fields, $nb, $last, $first, $phone, $addr, $lat, $lng, $plus, $start, $status, $photos]) {
    $sid = $svc($slug);
    $f = [];
    foreach ($fields as $k => $v) $f[$k] = is_int($v) ? $v : $choice($sid, $k, $v);
    $in = ['service_id' => $sid, 'neighborhood_id' => $hood($nb), 'first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'address_text' => $addr, 'lat' => $lat, 'lng' => $lng, 'location_source' => $n % 2 ? 'map' : 'gps',
        'date' => $day($plus), 'start' => $start, 'fields' => $f, 'marketing_consent' => $n % 2 === 0];
    if ($form) $in['formula_id'] = $formula($sid, $form);
    $b = $svcB->create($in);
    foreach ($photos as $p) {
        $name = bin2hex(random_bytes(10)) . '.jpg';
        @mkdir(BASE_PATH . '/storage/uploads/missions', 0775, true);
        copy("$photoDir/$p.jpg", BASE_PATH . '/storage/uploads/missions/' . $name);
        DB::insert('booking_photos', ['booking_id' => (int) $b['id'], 'user_id' => null, 'kind' => 'before', 'path' => $name, 'created_at' => now()]);
    }
    if ($status !== 'pending') $svcB->changeStatus((int) $b['id'], $status, 1);
    if ($status === 'completed') DB::exec('UPDATE bookings SET scheduled_date = ?, declared_amount = total WHERE id = ?', [date('Y-m-d', strtotime('-1 day')), (int) $b['id']]);
    $n++;
    echo "  {$b['reference']}  $first $last — $slug ($status)\n";
}
echo "$n commandes d'exemple créées.\n";
