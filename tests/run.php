<?php
// Lancer : php tests/run.php
declare(strict_types=1);
define('TESTING', true);
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/TestCase.php';

use App\Core\{Database as DB, RateLimiter, Upload, ValidationException};
use App\Services\{PricingService, AvailabilityService, BookingService, AssignmentService, NotificationService, SchedulingService, SlotUnavailableException};

$dbFile = sys_get_temp_dir() . '/lavage_tests_' . getmypid() . '.db';
putenv('MAIL_DRIVER=log'); putenv('SMS_DRIVER=log');
@unlink(BASE_PATH . '/storage/logs/mail.log'); @unlink(BASE_PATH . '/storage/logs/sms.log');

// ------------------------------------------------------------------ Migrations
T::group('Migrations');
fresh_db($dbFile);
T::ok(DB::tableExists('bookings') && DB::tableExists('notification_logs'), 'tables créées');
T::eq(\App\Core\Migrator::run(), 0, 'seconde exécution : rien à migrer (idempotent)');
T::eq(count(DB::tables()) >= 38, true, 'au moins 38 tables : ' . count(DB::tables()));

// ------------------------------------------------------------------ Tarification
T::group('Tarification (PricingService)');
$p = new PricingService();
$a = svc('lavage-automobile');
$q = $p->quote($a, ['formula_id' => formula($a, 'Complet'), 'fields' => ['vehicle' => choice($a, 'vehicle', 'SUV / 4x4'), 'count' => 2], 'options' => [opt($a, 'Shampoing moteur')]], nb('Unité 15'));
T::eq($q['subtotal'], 22000, 'lavage Complet SUV ×2 + shampoing moteur = (6000+2000+3000)×2');
T::eq($q['duration'], 280, 'durée = (15+75+20+30) × 2 véhicules');
T::eq($q['travel_fee'], 1000, 'frais de déplacement du quartier');
T::eq($q['total'], 23000, 'total = sous-total + déplacement');
$t = svc('nettoyage-tapis-moquettes');
$q = $p->quote($t, ['formula_id' => formula($t, 'Moquette'), 'fields' => ['area' => 20], 'options' => [opt($t, 'Traitement anti-taches')]]);
T::eq($q['subtotal'], 29000, 'moquette 20 m² = 1200×20 + 5000');
T::eq($q['duration'], 30 + 19 * 3 + 15, 'durée au m²');
$m = svc('nettoyage-maison');
$q = $p->quote($m, ['formula_id' => formula($m, 'Approfondi'), 'fields' => ['housing' => choice($m, 'housing', 'F2'), 'surface' => 80, 'rooms' => 4], 'options' => [opt($m, 'Nettoyage des vitres')]]);
T::eq($q['subtotal'], 33000, 'maison approfondie F2 = 20000×1,4 + 5000');
T::throws(fn() => $p->quote($a, ['formula_id' => 999999, 'fields' => []]), ValidationException::class, 'formule inexistante refusée');
T::throws(fn() => $p->quote($a, ['formula_id' => formula($a, 'Simple'), 'fields' => ['vehicle' => choice($a, 'vehicle', 'Citadine'), 'count' => 99]]), ValidationException::class, 'quantité hors limites refusée');
T::throws(fn() => $p->quote($a, ['formula_id' => formula($a, 'Simple'), 'fields' => ['vehicle' => choice($m, 'housing', 'Villa'), 'count' => 1]]), ValidationException::class, "choix d'un autre service refusé (falsification)");
T::throws(fn() => $p->quote($a, ['formula_id' => formula($a, 'Simple'), 'fields' => ['count' => 1]]), ValidationException::class, 'champ obligatoire manquant refusé');
DB::exec("UPDATE services SET availability_status = 'unavailable' WHERE id = ?", [$a]);
T::throws(fn() => $p->quote($a, []), ValidationException::class, 'service indisponible non réservable');
DB::exec("UPDATE services SET availability_status = 'available' WHERE id = ?", [$a]);

// ------------------------------------------------------------------ Disponibilités
T::group('Disponibilités (AvailabilityService)');
fresh_db($dbFile);
$av = new AvailabilityService();
$dur = 45;                                     // lavage Simple citadine : 15+30
$mon = next_dow(1); $tue = next_dow(2); $sun = next_dow(7);
$s = $av->slots($a, nb('Unité 15'), $dur, $mon);
T::ok(count($s) > 5, "Unité 15 un lundi : créneaux proposés (" . count($s) . ')');
T::eq($s[0]['start'], '08:00', 'premier créneau à l\'ouverture');
T::eq(array_filter($s, fn($x) => hm_to_min($x['start']) < hm_to_min('14:00') && hm_to_min($x['end']) > hm_to_min('12:30')), [], 'aucun créneau ne chevauche la pause 12h30–14h');
T::eq($av->slots($a, nb('Unité 15'), $dur, $tue), [], "Unité 15 un mardi : jour d'intervention exclu");
T::eq($av->slots($a, nb('Plateau'), $dur, $sun), [], 'dimanche : équipe fermée');
$s = $av->slots($a, nb('Almadies'), $dur, $tue);
T::ok($s && $s[0]['start'] === '09:00' && end($s)['end'] <= '17:00', 'Almadies : plage 09:00–17:00 respectée');
T::eq($av->slots($a, nb('Almadies'), $dur, $mon), [], 'Almadies un lundi : non desservi ce jour');
T::eq($av->slots($a, nb('Plateau'), $dur, date('Y-m-d', strtotime('-1 day'))), [], 'date passée refusée');
T::eq($av->slots($a, nb('Plateau'), $dur, date('Y-m-d', strtotime('+90 day'))), [], 'au-delà de la période réservable refusé');
T::eq($av->slots($a, nb('Plateau'), 700, next_dow(3)), [], 'durée supérieure à la journée : aucun créneau');
// Fermeture entreprise
$wed = next_dow(3);
$before = count($av->slots($a, nb('Plateau'), $dur, $wed));
$id = DB::insert('time_off', ['company_id' => 1, 'start_date' => $wed, 'end_date' => $wed, 'reason' => 'Férié']);
T::eq($av->slots($a, nb('Plateau'), $dur, $wed), [], 'jour férié (fermeture entreprise) : aucun créneau');
DB::exec('DELETE FROM time_off WHERE id = ?', [$id]);
T::eq(count($av->slots($a, nb('Plateau'), $dur, $wed)), $before, 'après suppression du jour férié : créneaux revenus');
// Congé d'équipe
$teamAuto = (int) DB::val("SELECT id FROM teams WHERE name = 'Équipe Auto'");
$id = DB::insert('time_off', ['company_id' => 1, 'team_id' => $teamAuto, 'start_date' => $wed, 'end_date' => $wed]);
T::eq($av->slots($a, nb('Plateau'), $dur, $wed), [], "congé de l'équipe : plus de créneau pour son service");
T::ok(count($av->slots(svc('nettoyage-maison'), nb('Plateau'), 120, $wed)) > 0, "... mais les autres équipes restent disponibles");
DB::exec('DELETE FROM time_off WHERE id = ?', [$id]);
// Absence de tous les membres
$ids = [];
foreach (DB::all('SELECT id FROM staff WHERE team_id = ?', [$teamAuto]) as $r) $ids[] = DB::insert('time_off', ['company_id' => 1, 'staff_id' => $r['id'], 'start_date' => $wed, 'end_date' => $wed]);
T::eq($av->slots($a, nb('Plateau'), $dur, $wed), [], 'tous les agents absents : équipe indisponible');
DB::exec('DELETE FROM time_off WHERE staff_id IS NOT NULL');
// Compétence requise
DB::exec('DELETE FROM staff_skills WHERE staff_id IN (SELECT id FROM staff WHERE team_id = ?)', [$teamAuto]);
T::eq($av->slots($a, nb('Plateau'), $dur, $wed), [], 'compétence requise absente : équipe écartée');

// ------------------------------------------------------------------ Réservation
T::group('Réservation, trajets, regroupement (BookingService)');
fresh_db($dbFile);
$svcB = new BookingService(); $av = new AvailabilityService();
$mon = next_dow(1);
$b1 = $svcB->create(wash_input($mon, '08:00'));
T::ok(str_starts_with($b1['reference'], 'NX-'), 'référence unique générée : ' . $b1['reference']);
T::eq([$b1['status'], $b1['total'], $b1['duration_minutes'], $b1['end_time']], ['pending', 4000, 45, '08:45'], 'statut initial, prix serveur (3000+1000), durée, fin');
T::eq((int) DB::val('SELECT COUNT(*) FROM booking_items WHERE booking_id = ?', [$b1['id']]), 1, 'ligne de prestation enregistrée');
T::eq((int) DB::val('SELECT COUNT(*) FROM booking_status_history WHERE booking_id = ?', [$b1['id']]), 1, 'historique de statut initialisé');
T::eq(normalize_phone('77 123 45 67'), '221771234567', 'téléphone normalisé');
T::throws(fn() => $svcB->create(wash_input($mon, '08:00', 'Unité 15', ['phone' => '77 999 99 99'])), SlotUnavailableException::class, 'même créneau : refusé (double réservation)');
T::throws(fn() => $svcB->create(wash_input($mon, '08:30', 'Unité 15', ['phone' => '77 999 99 98'])), SlotUnavailableException::class, 'créneau chevauchant : refusé');
$slots = $av->slots($a, nb('Unité 15'), 45, $mon);
T::eq(array_column($slots, 'start')[0], '09:00', 'premier créneau libre = 08:45 + 10 min de battement, arrondi au pas de 30 min');
T::ok($slots[0]['grouped'] === true, 'créneau marqué « regroupé » (même quartier déjà visité ce jour)');
$otherZone = $av->slots($a, nb('Unité 24'), 45, $mon);   // même commune, quartier différent : trajet 25 min
T::eq($otherZone[0]['start'], '09:30', 'autre quartier : 25 min de trajet imposés → 09:30');
T::throws(fn() => $svcB->create(wash_input($mon, '09:00', 'Unité 15', ['phone' => '', 'first_name' => ''])), ValidationException::class, 'champs client manquants refusés');
T::throws(fn() => $svcB->create(wash_input($mon, '09:00', 'Unité 15', ['phone' => '123'])), ValidationException::class, 'téléphone invalide refusé');
T::throws(fn() => $svcB->create(wash_input($mon, '9h', 'Unité 15')), ValidationException::class, 'heure mal formée refusée');
T::throws(fn() => $svcB->create(wash_input($mon, '10:00', 'Unité 15', ['neighborhood_id' => 99999])), ValidationException::class, 'quartier inexistant refusé');
T::eq((int) DB::val('SELECT COUNT(*) FROM customers'), 1, 'un seul client créé (pas de doublon par téléphone)');
// Capacité quotidienne
DB::exec('UPDATE teams SET daily_capacity = 2 WHERE id = ?', [$teamAuto]);
$svcB->create(wash_input($mon, '10:00', 'Unité 15', ['phone' => '77 555 00 01']));
T::eq($av->slots($a, nb('Unité 15'), 45, $mon), [], 'capacité quotidienne atteinte : plus aucun créneau');
// Annulation libère le créneau
$svcB->changeStatus((int) $b1['id'], 'cancelled', null, 'Test');
T::ok(count($av->slots($a, nb('Unité 15'), 45, $mon)) > 0, "annulation : le créneau est de nouveau proposé");
DB::exec('UPDATE teams SET daily_capacity = 5 WHERE id = ?', [$teamAuto]);

T::group('Statuts et déplacement');
$b = $svcB->create(wash_input(next_dow(3), '08:00', 'Plateau', ['phone' => '77 600 00 00']));
T::throws(fn() => $svcB->changeStatus((int) $b['id'], 'completed'), RuntimeException::class, 'en attente → terminée : transition interdite');
$svcB->changeStatus((int) $b['id'], 'confirmed', 1);
T::eq(DB::val('SELECT status FROM bookings WHERE id = ?', [$b['id']]), 'confirmed', 'en attente → confirmée');
$newDate = next_dow(5);
$svcB->reschedule((int) $b['id'], $newDate, '10:00');
$row = DB::one('SELECT * FROM bookings WHERE id = ?', [$b['id']]);
T::eq([$row['scheduled_date'], $row['start_time'], $row['end_time']], [$newDate, '10:00', '10:45'], 'déplacement vers un créneau libre');
$svcB->create(wash_input($newDate, '14:00', 'Plateau', ['phone' => '77 600 00 01']));
T::throws(fn() => $svcB->reschedule((int) $b['id'], $newDate, '14:00'), SlotUnavailableException::class, 'déplacement vers un créneau occupé refusé');
T::throws(fn() => $svcB->reschedule((int) $b['id'], $newDate, '12:30'), SlotUnavailableException::class, 'déplacement dans la pause refusé');
$svcB->changeStatus((int) $b['id'], 'cancelled', null, '');
T::throws(fn() => $svcB->changeStatus((int) $b['id'], 'confirmed'), RuntimeException::class, 'réservation annulée : définitivement close');
T::ok(DB::val("SELECT COUNT(*) FROM audit_logs WHERE action = 'booking.status'") >= 2, "journal d'audit alimenté");

// ------------------------------------------------------------------ Concurrence
T::group('Concurrence : 8 clients, même créneau, même seconde');
fresh_db($dbFile);
$mon = next_dow(1);
$at = microtime(true) + 1.5;
$procs = [];
for ($i = 1; $i <= 8; $i++) {
    $procs[$i] = proc_open([PHP_BINARY, __DIR__ . '/book_worker.php', $dbFile, $mon, '08:00', (string) $i, (string) $at], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
}
$results = [];
foreach ($procs as $i => $pr) { $results[$i] = stream_get_contents($pipes[$i][1]) . stream_get_contents($pipes[$i][2]); proc_close($pr); }
DB::reset();
$okCount = count(array_filter($results, fn($r) => str_starts_with($r, 'OK:')));
$taken = count(array_filter($results, fn($r) => $r === 'TAKEN'));
T::eq($okCount, 1, 'exactement 1 réservation acceptée');
T::eq($taken, 7, 'les 7 autres reçoivent « créneau pris » (résultats : ' . implode(' | ', array_map(fn($r) => substr($r, 0, 40), $results)) . ')');
T::eq((int) DB::val("SELECT COUNT(*) FROM bookings WHERE scheduled_date = ? AND start_time = '08:00'", [$mon]), 1, 'une seule ligne en base sur ce créneau');

// ------------------------------------------------------------------ Affectation
T::group('Affectation et accès agent');
fresh_db($dbFile);
$svcB = new BookingService(); $as = new AssignmentService();
$mon = next_dow(1);
$b = $svcB->create(wash_input($mon, '08:00'));
$moussa = (int) DB::val("SELECT id FROM staff WHERE name = 'Moussa Diop'");
$cheikh = (int) DB::val("SELECT id FROM staff WHERE name = 'Cheikh Ndiaye'");
$awa = (int) DB::val("SELECT id FROM staff WHERE name = 'Awa Fall'");
T::eq($as->staffCanAccess($moussa, (int) $b['id']), false, 'avant affectation : aucun agent ne voit la mission');
$as->assign((int) $b['id'], null, $moussa, 1);
T::eq(DB::val('SELECT status FROM bookings WHERE id = ?', [$b['id']]), 'assigned', 'affectation → statut « Affectée »');
T::eq($as->staffCanAccess($moussa, (int) $b['id']), true, "l'agent affecté voit sa mission");
T::eq($as->staffCanAccess($cheikh, (int) $b['id']), false, 'un collègue de la même équipe ne voit pas la mission d\'un autre agent');
T::eq($as->staffCanAccess($awa, (int) $b['id']), false, "un agent d'une autre équipe ne la voit pas");
T::throws(fn() => $as->assign((int) $b['id'], null, $awa, 1), RuntimeException::class, "agent d'une équipe qui ne fait pas ce service : refusé");
$warn = $as->assign((int) $b['id'], null, $awa, 1, true);
T::ok(count($warn) > 0, 'forçage possible, avertissements conservés : ' . implode(' / ', $warn));
T::eq($as->staffCanAccess($moussa, (int) $b['id']), false, "réaffectation : l'ancien agent perd l'accès");
T::eq((int) DB::val('SELECT COUNT(*) FROM booking_assignments WHERE booking_id = ? AND active = 1', [$b['id']]), 1, 'une seule affectation active');
$svcB->changeStatus((int) $b['id'], 'en_route', 1); $svcB->changeStatus((int) $b['id'], 'in_progress', 1); $svcB->changeStatus((int) $b['id'], 'completed', 1);
T::eq(DB::val('SELECT status FROM bookings WHERE id = ?', [$b['id']]), 'completed', 'cycle complet assigned → en route → intervention → terminée');
// Auto-affectation (architecture préparée, désactivée par défaut)
$b2 = $svcB->create(wash_input($mon, '10:00', 'Unité 15', ['phone' => '77 800 00 00']));
T::eq(DB::val('SELECT status FROM bookings WHERE id = ?', [$b2['id']]), 'pending', 'auto_assign désactivé par défaut');
T::eq($as->autoAssign((int) $b2['id']), true, 'autoAssign() disponible pour plus tard');
T::eq($as->staffCanAccess($moussa, (int) $b2['id']) || $as->staffCanAccess($cheikh, (int) $b2['id']), true, 'agent choisi parmi les membres compétents');

// ------------------------------------------------------------------ Planning
T::group('Planning : charge et conflits');
$sc = new SchedulingService();
$load = $sc->dayLoad($mon);
$auto = array_values(array_filter($load, fn($l) => $l['team']['name'] === 'Équipe Auto'))[0];
T::eq([$auto['count'], $auto['window']], [1, 510], "charge du jour : 1 intervention (l'autre a été réaffectée à l'équipe Textile), 8 h 30 de travail effectif");
$fake = [['id' => 1, 'team_id' => 1, 'scheduled_date' => $mon, 'start_time' => '08:00', 'end_time' => '09:00', 'neighborhood_id' => 1, 'avg_travel_minutes' => 25, 'status' => 'pending', 'reference' => 'A'],
         ['id' => 2, 'team_id' => 1, 'scheduled_date' => $mon, 'start_time' => '09:05', 'end_time' => '10:00', 'neighborhood_id' => 2, 'avg_travel_minutes' => 25, 'status' => 'pending', 'reference' => 'B'],
         ['id' => 3, 'team_id' => 1, 'scheduled_date' => $mon, 'start_time' => '09:50', 'end_time' => '11:00', 'neighborhood_id' => 2, 'avg_travel_minutes' => 25, 'status' => 'pending', 'reference' => 'C']];
$c = $sc->conflicts($fake);
T::ok(isset($c[2]) && str_contains($c[2][0], 'Trajet court'), 'trajet insuffisant détecté');
T::ok(isset($c[3]) && str_contains($c[3][0], 'Chevauche'), 'chevauchement détecté');
T::eq($sc->zoneJumps($fake), 1, 'indicateur de dispersion géographique');
$planId = $sc->buildRoutePlan((int) DB::val("SELECT id FROM teams WHERE name = 'Équipe Auto'"), $mon);
T::eq((int) DB::val('SELECT COUNT(*) FROM route_stops WHERE route_plan_id = ?', [$planId]), 1, 'feuille de route générée (1 arrêt)');

// ------------------------------------------------------------------ Notifications
T::group('Notifications : journal, pannes, reprises');
fresh_db($dbFile);
$svcB = new BookingService();
$mon = next_dow(1);
$svcB->create(wash_input($mon, '08:00', 'Unité 15', ['email' => 'client@example.com']));
$logs = DB::all('SELECT channel, audience, status FROM notification_logs ORDER BY id');
T::eq(count($logs), 3, 'réservation créée : SMS client + email client + email admin');
T::ok(!array_filter($logs, fn($l) => $l['status'] !== 'sent'), 'tous envoyés (pilote « log »)');
T::ok(str_contains((string) @file_get_contents(BASE_PATH . '/storage/logs/sms.log'), '221771234567'), 'SMS écrit pour le bon numéro');
// Désactivation d'un type de notification
setting_set(NotificationService::toggleKey('booking.created', 'sms'), '0');
$n0 = (int) DB::val('SELECT COUNT(*) FROM notification_logs');
$svcB->create(wash_input($mon, '10:00', 'Unité 15', ['phone' => '77 111 11 11', 'email' => 'x@example.com']));
T::eq((int) DB::val('SELECT COUNT(*) FROM notification_logs') - $n0, 2, 'SMS désactivé dans les paramètres : seulement 2 envois');
setting_set(NotificationService::toggleKey('booking.created', 'sms'), '1');
// Panne SMTP
putenv('MAIL_DRIVER=smtp'); putenv('MAIL_HOST=127.0.0.1'); putenv('MAIL_PORT=1');
$b = $svcB->create(wash_input($mon, '15:00', 'Unité 15', ['phone' => '77 222 22 22', 'email' => 'panne@example.com']));
T::ok($b && $b['id'] > 0, 'panne email : la réservation est quand même enregistrée');
$failed = DB::one("SELECT * FROM notification_logs WHERE booking_id = ? AND channel = 'email' AND audience = 'client'", [$b['id']]);
T::eq([$failed['status'], (int) $failed['attempts']], ['failed', 1], 'envoi échoué journalisé avec 1 tentative');
T::ok(!empty($failed['error']) && $failed['next_attempt_at'] !== null, 'erreur conservée et nouvelle tentative planifiée : ' . $failed['error']);
$sms = DB::one("SELECT status FROM notification_logs WHERE booking_id = ? AND channel = 'sms'", [$b['id']]);
T::eq($sms['status'], 'sent', 'le SMS part malgré la panne email (canaux indépendants)');
$ns = new NotificationService();
for ($k = 2; $k <= 4; $k++) {
    DB::exec('UPDATE notification_logs SET next_attempt_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 5), $failed['id']]);
    $ns->retryDue();
}
$after = DB::one('SELECT status, attempts, next_attempt_at FROM notification_logs WHERE id = ?', [$failed['id']]);
T::eq([(int) $after['attempts'], $after['next_attempt_at']], [4, null], 'après 4 tentatives : plus aucune reprise planifiée');
DB::exec('UPDATE notification_logs SET next_attempt_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 5), $failed['id']]);
$ns->retryDue();
T::eq((int) DB::val('SELECT attempts FROM notification_logs WHERE id = ?', [$failed['id']]), 4, 'la file automatique ne dépasse jamais le plafond de tentatives');
putenv('MAIL_DRIVER=log');
T::eq($ns->retryNow((int) $failed['id'], true), true, 'relance manuelle forcée réussie une fois le service rétabli');
T::eq(DB::val('SELECT status FROM notification_logs WHERE id = ?', [$failed['id']]), 'sent', 'statut passé à « envoyé »');
// Rappels
$soon = time() + 20 * 3600;   // dans 20 h : dans la fenêtre de rappel de 24 h
$tomorrow = date('Y-m-d', $soon);
$cid = (int) DB::val('SELECT id FROM customers LIMIT 1');
$bk = DB::insert('bookings', ['company_id' => 1, 'reference' => 'NX-TEST-REM1', 'customer_id' => $cid, 'neighborhood_id' => nb('Plateau'), 'service_id' => svc('lavage-automobile'), 'address_text' => 'x',
    'scheduled_date' => $tomorrow, 'start_time' => date('H:i', $soon), 'end_time' => '23:59', 'duration_minutes' => 45, 'status' => 'confirmed', 'created_at' => now()]);
T::eq($ns->sendReminders(), 1, 'rappel envoyé pour une intervention dans moins de 24 h');
T::eq($ns->sendReminders(), 0, 'et une seule fois par réservation');

// ------------------------------------------------------------------ Sécurité
T::group('Sécurité : limitation de débit, mots de passe, téléversement');
T::eq([RateLimiter::hit('t1', 3, 60), RateLimiter::hit('t1', 3, 60), RateLimiter::hit('t1', 3, 60), RateLimiter::hit('t1', 3, 60)], [true, true, true, false], 'limitation : 3 autorisées, 4e refusée');
RateLimiter::clear('t1');
T::eq(RateLimiter::hit('t1', 3, 60), true, 'compteur réinitialisable');
$hash = DB::val("SELECT password_hash FROM users WHERE email = ?", ['admin@netexpress.test']);
T::ok(str_starts_with($hash, '$') && password_verify('Admin#2026', $hash) && !password_verify('mauvais', $hash), 'mot de passe haché (password_hash) et vérifié');
$fake = sys_get_temp_dir() . '/fake.jpg'; file_put_contents($fake, '<?php echo 1; ?>');
T::throws(fn() => Upload::image(['error' => 0, 'tmp_name' => $fake, 'size' => filesize($fake), 'name' => 'x.jpg'], sys_get_temp_dir() . '/up'), RuntimeException::class, 'script PHP déguisé en .jpg refusé');
$png = sys_get_temp_dir() . '/ok.png'; file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$name = Upload::image(['error' => 0, 'tmp_name' => $png, 'size' => filesize($png), 'name' => '../../evil.php'], sys_get_temp_dir() . '/up');
T::ok(preg_match('/^[a-f0-9]{24}\.png$/', (string) $name) === 1, "vraie image acceptée, nom aléatoire, extension imposée ($name)");
$esc = e('<script>alert(1)</script>"\'');
T::ok(!str_contains($esc, '<script>') && str_contains($esc, '&lt;script&gt;'), 'échappement XSS (e())');

DB::reset();
foreach (['', '-wal', '-shm'] as $s) @unlink($dbFile . $s);
exit(T::summary());
