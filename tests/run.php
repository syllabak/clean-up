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
T::ok(str_starts_with($b1['reference'], 'CU-'), 'référence unique générée : ' . $b1['reference']);
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
$bk = DB::insert('bookings', ['company_id' => 1, 'reference' => 'CU-TEST-REM1', 'customer_id' => $cid, 'neighborhood_id' => nb('Plateau'), 'service_id' => svc('lavage-automobile'), 'address_text' => 'x',
    'scheduled_date' => $tomorrow, 'start_time' => date('H:i', $soon), 'end_time' => '23:59', 'duration_minutes' => 45, 'status' => 'confirmed', 'created_at' => now()]);
T::eq($ns->sendReminders(), 1, 'rappel envoyé pour une intervention dans moins de 24 h');
T::eq($ns->sendReminders(), 0, 'et une seule fois par réservation');

// ------------------------------------------------------------------ Sécurité
T::group('Sécurité : limitation de débit, mots de passe, téléversement');
T::eq([RateLimiter::hit('t1', 3, 60), RateLimiter::hit('t1', 3, 60), RateLimiter::hit('t1', 3, 60), RateLimiter::hit('t1', 3, 60)], [true, true, true, false], 'limitation : 3 autorisées, 4e refusée');
RateLimiter::clear('t1');
T::eq(RateLimiter::hit('t1', 3, 60), true, 'compteur réinitialisable');
$hash = DB::val("SELECT password_hash FROM users WHERE email = ?", ['admin@cleanup.test']);
T::ok(str_starts_with($hash, '$') && password_verify('Admin#2026', $hash) && !password_verify('mauvais', $hash), 'mot de passe haché (password_hash) et vérifié');
$fake = sys_get_temp_dir() . '/fake.jpg'; file_put_contents($fake, '<?php echo 1; ?>');
T::throws(fn() => Upload::image(['error' => 0, 'tmp_name' => $fake, 'size' => filesize($fake), 'name' => 'x.jpg'], sys_get_temp_dir() . '/up'), RuntimeException::class, 'script PHP déguisé en .jpg refusé');
$png = sys_get_temp_dir() . '/ok.png'; file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$name = Upload::image(['error' => 0, 'tmp_name' => $png, 'size' => filesize($png), 'name' => '../../evil.php'], sys_get_temp_dir() . '/up');
T::ok(preg_match('/^[a-f0-9]{24}\.png$/', (string) $name) === 1, "vraie image acceptée, nom aléatoire, extension imposée ($name)");
// IP derrière un proxy, verrouillage par compte
use App\Core\Request;
putenv('TRUSTED_PROXIES='); $_SERVER['REMOTE_ADDR'] = '203.0.113.9'; $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7'; $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
T::ok(Request::ip() === '203.0.113.9' && !Request::isHttps(), 'sans proxy de confiance : X-Forwarded-For et X-Forwarded-Proto ignorés (falsifiables)');
putenv('TRUSTED_PROXIES=203.0.113.0/24, 10.0.0.1');
T::ok(Request::ip() === '198.51.100.7' && Request::isHttps(), 'proxy de confiance : vraie IP du visiteur et HTTPS lus');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6, 198.51.100.7, 203.0.113.50';
T::eq(Request::ip(), '198.51.100.7', 'chaîne de proxys : IP falsifiée à gauche ignorée');
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
T::ok(Request::ip() === '192.0.2.1' && !Request::isHttps(), 'requête directe (hors plage de confiance) : en-têtes ignorés');
T::ok(Request::ipInList('2001:db8::5', ['2001:db8::/32']) && !Request::ipInList('2001:db9::5', ['2001:db8::/32']), 'plages IPv6 reconnues');
putenv('TRUSTED_PROXIES='); unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
for ($i = 0; $i < 10; $i++) RateLimiter::hit('login_acct|victime@x.sn', 10, 900);
T::ok(RateLimiter::blocked('login_acct|victime@x.sn', 10, 900) && !RateLimiter::blocked('login_acct|autre@x.sn', 10, 900), 'verrouillage par compte : bloqué après 10 échecs, autres comptes intacts');
$esc = e('<script>alert(1)</script>"\'');
T::ok(!str_contains($esc, '<script>') && str_contains($esc, '&lt;script&gt;'), 'échappement XSS (e())');

// ------------------------------------------------------------------ CRM et gestion
T::group('CRM : canaux, prospects, attribution, rapports');
use App\Services\{CrmService, BusinessReportService};
fresh_db($dbFile);
$svcB = new BookingService(); $crm = new CrmService();
$bw = $svcB->create(wash_input(next_dow(1), '08:00'));
$insta = $crm->channelByCode('instagram'); $fb = $crm->channelByCode('facebook');
T::ok(DB::tableExists('leads') && DB::tableExists('expenses') && $insta && $fb, 'tables CRM et canaux par défaut créés');
T::eq((int) DB::val("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.name = 'manager' AND p.name = 'finance.manage'"), 0, 'le gestionnaire n\'a pas accès aux finances');
$l1 = $crm->createLead(['name' => 'Aminata Fall', 'phone' => '77 555 00 11', 'channel_id' => $fb, 'notes' => 'Veut un lavage SUV']);
T::eq($crm->createLead(['name' => 'Aminata Fall', 'phone' => '+221 77 555 00 11', 'notes' => 'relance']), $l1, 'même numéro : prospect réutilisé (pas de doublon)');
T::eq(DB::val('SELECT status FROM leads WHERE id = ?', [$l1]), 'new', 'prospect créé au statut « nouveau »');
$crm->setStatus($l1, 'contacted', null);
T::eq(DB::val('SELECT status FROM leads WHERE id = ?', [$l1]), 'contacted', 'changement de statut');
T::throws(fn() => $crm->setStatus($l1, 'bidon', null), RuntimeException::class, 'statut inconnu refusé');
// Une réservation avec le même numéro convertit le prospect et attribue le canal choisi par le client
$tue = next_dow(1);   // lundi, jour desservi par Unité 15
$bk = $svcB->create(wash_input($tue, '10:00', 'Unité 15', ['first_name' => 'Aminata', 'last_name' => 'Fall', 'phone' => '77 555 00 11', 'channel_id' => $insta]));
T::eq((int) DB::val('SELECT channel_id FROM bookings WHERE id = ?', [$bk['id']]), $insta, 'canal choisi par le client enregistré sur la réservation');
T::eq((int) DB::val('SELECT channel_id FROM customers WHERE id = ?', [$bk['customer_id']]), $insta, 'canal rattaché au nouveau client');
T::eq(DB::val('SELECT status FROM leads WHERE id = ?', [$l1]), 'won', 'prospect converti automatiquement par la réservation');
T::eq((int) DB::val('SELECT customer_id FROM leads WHERE id = ?', [$l1]), (int) $bk['customer_id'], 'prospect relié au client');
T::eq((int) DB::val('SELECT channel_id FROM bookings WHERE id = ?', [$bw['id']]), $crm->channelByCode('web'), 'sans choix : canal « site web » par défaut');
$bk2 = $svcB->create(wash_input($tue, '15:00', 'Unité 15', ['first_name' => 'Aminata', 'last_name' => 'Fall', 'phone' => '77 555 00 11', 'channel_id' => 999999]));
T::eq((int) DB::val('SELECT channel_id FROM bookings WHERE id = ?', [$bk2['id']]), $insta, 'canal invalide ignoré : on garde celui du client');
// Conversion manuelle
$l2 = $crm->createLead(['name' => 'Moussa Ba', 'phone' => '78 444 33 22', 'channel_id' => $fb]);
$cid2 = $crm->convert($l2, null);
T::eq([DB::val('SELECT status FROM leads WHERE id = ?', [$l2]), (int) DB::val('SELECT channel_id FROM customers WHERE id = ?', [$cid2])], ['won', $fb], 'conversion manuelle : client créé avec le canal du prospect');
T::throws(fn() => $crm->convert($crm->createLead(['name' => 'Sans Numéro', 'email' => 'x@y.sn']), null), RuntimeException::class, 'conversion impossible sans téléphone');
T::ok((int) DB::val('SELECT COUNT(*) FROM interactions WHERE lead_id = ?', [$l1]) >= 2, 'historique des échanges alimenté automatiquement');
T::eq(CrmService::segment(0, 0) . '/' . CrmService::segment(1, 5000) . '/' . CrmService::segment(2, 5000) . '/' . CrmService::segment(1, 150000), 'Nouveau/Occasionnel/Régulier/VIP', 'segmentation des clients');
// Rapports : CA, dépenses, coût par client, ROI
DB::update('bookings', (int) $bk['id'], ['status' => 'completed', 'declared_amount' => 20000]);
DB::insert('expenses', ['company_id' => 1, 'expense_date' => $tue, 'category' => 'marketing', 'label' => 'Pub Instagram', 'amount' => 10000, 'channel_id' => $insta, 'created_at' => now()]);
DB::insert('expenses', ['company_id' => 1, 'expense_date' => $tue, 'category' => 'produits', 'label' => 'Savon', 'amount' => 4000, 'created_at' => now()]);
$rep = new BusinessReportService();
[$from, $to] = BusinessReportService::range($tue, $tue);
$row = array_values(array_filter($rep->acquisition(date('Y-m-d', strtotime('-1 day')), $to), fn($r) => $r['id'] === $insta))[0] ?? null;
T::eq([$row['revenue'], $row['spend']], [20000, 40000], 'acquisition : CA et dépenses du canal (30 000 de démo + 10 000 saisis)');
T::eq([$row['roi'], $row['cac']], [-50, 40000], 'acquisition : ROI = (20 000 − 40 000) / 40 000 = −50 %, coût par client = 40 000 / 1 client');
$pf = $rep->profit($from, $to);
T::eq([$pf['revenue'], $pf['expenses'], $pf['profit']], [20000, 14000, 6000], 'résultat = CA − dépenses (20 000 − 14 000)');
T::eq(BusinessReportService::range('n\'importe quoi', '2026-03-01')[0], '2026-03-01', 'période invalide : début remplacé, dates remises dans l\'ordre');
T::eq(count($rep->monthly(6)), 6, 'résultat sur 6 mois');
$cu = $rep->customers($from, $to);
T::ok($cu['total'] >= 2 && $cu['buyers'] >= 1, 'statistiques clients');

// ------------------------------------------------------------------ Catalogue : duplication, contrôle de publication, aperçu
T::group('Catalogue : duplication, contrôle « prêt à réserver », simulateur');
use App\Services\CatalogService;
$cat = new CatalogService(); $auto = svc('lavage-automobile');
$copy = $cat->duplicate($auto);
$cp = DB::one('SELECT * FROM services WHERE id = ?', [$copy]);
T::ok($cp['active'] == 0 && str_ends_with($cp['name'], '(copie)') && $cp['slug'] === 'lavage-automobile-copie', 'copie créée inactive, nom et adresse distincts');
T::eq((int) DB::val('SELECT COUNT(*) FROM service_prices WHERE service_id = ?', [$copy]), (int) DB::val('SELECT COUNT(*) FROM service_prices WHERE service_id = ?', [$auto]), 'formules copiées');
T::eq((int) DB::val('SELECT COUNT(*) FROM service_field_choices c JOIN service_fields f ON f.id = c.field_id WHERE f.service_id = ?', [$copy]), (int) DB::val('SELECT COUNT(*) FROM service_field_choices c JOIN service_fields f ON f.id = c.field_id WHERE f.service_id = ?', [$auto]), 'champs et choix copiés');
T::ok(DB::val('SELECT includes FROM service_prices WHERE service_id = ? AND popular = 1', [$copy]) !== null, 'contenu inclus et formule « populaire » copiés');
T::eq((int) DB::val('SELECT id FROM services WHERE id = ?', [$cat->duplicate($auto)]) > $copy, true, 'seconde copie : adresse unique (-copie-2)');
T::throws(fn() => (new PricingService())->quote($copy, ['formula_id' => formula($copy, 'Simple'), 'fields' => ['vehicle' => choice($copy, 'vehicle', 'Citadine'), 'count' => 1]]), ValidationException::class, 'copie inactive : invisible pour le public');
$pv = (new PricingService())->quote($copy, ['formula_id' => formula($copy, 'Simple'), 'fields' => ['vehicle' => choice($copy, 'vehicle', 'Citadine'), 'count' => 1]], null, true);
T::eq($pv['total'], 3000, 'aperçu admin : la copie inactive se simule quand même');
$bad = array_column(array_filter($cat->readiness($auto), fn($c) => $c[0] === 'bad'), 1);
T::eq($bad, [], 'service de démo : aucun blocage');
$nt = DB::insert('services', ['company_id' => 1, 'name' => 'Vide', 'slug' => 'vide', 'base_price' => 0, 'created_at' => now()]);
$msgs = implode(' | ', array_column(array_filter($cat->readiness($nt), fn($c) => $c[0] === 'bad'), 1));
T::ok(str_contains($msgs, 'Aucun prix') && str_contains($msgs, "Aucune équipe"), 'service vide : blocages « aucun prix » et « aucune équipe »');
$fld = DB::insert('service_fields', ['service_id' => $nt, 'field_key' => 'x', 'label' => 'Taille', 'type' => 'select', 'role' => 'none', 'required' => 1]);
T::ok(str_contains(implode('|', array_column($cat->readiness($nt), 1)), 'Taille'), 'liste sans choix détectée');
T::throws(fn() => $cat->duplicate(999999), RuntimeException::class, 'duplication d\'un service inconnu refusée');

// ------------------------------------------------------------------ Marketing
T::group('Marketing : canaux configurables, campagnes, WhatsApp, suivi publicitaire');
use App\Services\{ChannelConfig, MarketingService, TrackingService, CustomerService};
use App\Services\Channels\WhatsAppGateway;
$mk = new MarketingService(); $cs = new CustomerService();

putenv('SMS_DRIVER=log');
setting_set('mk_sms_driver', 'http');
T::eq(ChannelConfig::get('sms_driver', 'SMS_DRIVER', 'log'), 'http', 'valeur saisie dans l\'administration : elle l\'emporte sur le .env');
setting_set('mk_sms_driver', '');
T::eq(ChannelConfig::get('sms_driver', 'SMS_DRIVER', 'log'), 'log', 'champ vide : retombe sur le .env');

$c1 = $cs->findOrCreate('Awa', 'Diop', '77 100 00 01', 'awa@example.com');
$c2 = $cs->findOrCreate('Moussa', 'Ba', '77 100 00 02', null);
$c3 = $cs->findOrCreate('Fatou', 'Sy', '77 100 00 03', 'fatou@example.com');
DB::exec('UPDATE customers SET marketing_consent = 1 WHERE id IN (?, ?)', [$c1, $c2]);
DB::exec('UPDATE customers SET marketing_consent = 1 WHERE marketing_consent = 0 AND id NOT IN (?, ?, ?)', [$c1, $c2, $c3]);   // sans effet sur c3
DB::exec('UPDATE customers SET marketing_consent = 0 WHERE id NOT IN (?, ?)', [$c1, $c2]);   // isole le jeu d'essai
T::eq(array_column($mk->audience('sms', 'consent'), 'id'), [$c1, $c2], 'SMS : seuls les clients consentants avec un numéro');
T::eq(array_column($mk->audience('email', 'consent'), 'id'), [$c1], 'email : il faut aussi une adresse valide');
T::eq(array_column($mk->audience('sms', 'new'), 'id'), [$c1, $c2], 'segment « nouveaux » : aucune intervention terminée');
T::eq($mk->audience('sms', 'inactive'), [], 'segment « inactifs » : personne sans historique');
T::throws(fn() => $mk->create(['name' => '', 'channel' => 'sms', 'audience' => 'consent', 'body' => 'x'], null), ValidationException::class, 'campagne sans nom refusée');
T::throws(fn() => $mk->create(['name' => 'Promo', 'channel' => 'email', 'audience' => 'consent', 'body' => 'x'], null), ValidationException::class, 'email sans objet refusé');
T::throws(fn() => $mk->create(['name' => 'Promo', 'channel' => 'fax', 'audience' => 'consent', 'body' => 'x'], null), ValidationException::class, 'canal inconnu refusé');

$cid1 = $mk->create(['name' => 'Promo canapés', 'channel' => 'sms', 'audience' => 'consent', 'body' => 'Bonjour {client}, -10 % : {lien}'], null);
T::eq($mk->launch($cid1, null), 2, 'lancement : un message par client consentant');
$msg = DB::one('SELECT * FROM marketing_messages WHERE campaign_id = ? AND customer_id = ?', [$cid1, $c1]);
T::ok(str_contains($msg['content'], 'Bonjour Awa') && str_contains($msg['content'], 'utm_source=sms') && str_contains($msg['content'], 'utm_medium=campaign') && str_contains($msg['content'], 'utm_campaign=promo-canapes'), 'message personnalisé avec lien suivi');
T::ok(str_contains($msg['content'], 'STOP'), 'mention de désinscription ajoutée');
T::throws(fn() => $mk->launch($cid1, null), RuntimeException::class, 'une campagne ne se lance qu\'une fois');
T::eq($mk->processQueue(), 2, 'tâche planifiée : les deux SMS partent');
T::eq(DB::val('SELECT status FROM marketing_campaigns WHERE id = ?', [$cid1]), 'done', 'campagne terminée');
T::ok(str_contains((string) @file_get_contents(BASE_PATH . '/storage/logs/sms.log'), '221771000001'), 'SMS écrit dans le journal du mode test');
T::eq($mk->processQueue(), 0, 'rien à renvoyer ensuite');

$cid2 = $mk->create(['name' => 'Relance', 'channel' => 'sms', 'audience' => 'consent', 'body' => 'Salut {client}'], null);
$mk->launch($cid2, null);
DB::exec('UPDATE customers SET marketing_consent = 0 WHERE id = ?', [$c2]);
T::eq($mk->processQueue(), 1, 'consentement retiré entre le lancement et l\'envoi : message ignoré');
T::eq((int) DB::val("SELECT COUNT(*) FROM marketing_messages WHERE campaign_id = ? AND status = 'skipped'", [$cid2]), 1, 'message marqué « ignoré »');
DB::exec('UPDATE customers SET marketing_consent = 1 WHERE id = ?', [$c2]);

$cid3 = $mk->create(['name' => 'Annulée', 'channel' => 'sms', 'audience' => 'consent', 'body' => 'Salut', 'scheduled_at' => date('Y-m-d\TH:i', strtotime('+2 days'))], null);
$mk->launch($cid3, null);
T::eq($mk->processQueue(), 0, 'campagne programmée dans le futur : rien ne part');
$mk->cancel($cid3, null);
T::eq((int) DB::val("SELECT COUNT(*) FROM marketing_messages WHERE campaign_id = ? AND status = 'cancelled'", [$cid3]), 2, 'annulation : les messages restants ne partiront pas');

// WhatsApp : désactivé, sans API (liens), avec API
$wa = $mk->create(['name' => 'WhatsApp promo', 'channel' => 'whatsapp', 'audience' => 'consent', 'body' => 'Hello {client}'], null);
setting_set('mk_wa_mode', 'off');
T::throws(fn() => $mk->launch($wa, null), RuntimeException::class, 'WhatsApp désactivé : lancement refusé');
setting_set('mk_wa_mode', 'link');
T::eq($mk->launch($wa, null), 2, 'WhatsApp sans API : messages préparés');
T::eq((int) DB::val("SELECT COUNT(*) FROM marketing_messages WHERE campaign_id = ? AND status = 'manual'", [$wa]), 2, 'messages à envoyer à la main');
T::eq($mk->processQueue(), 0, 'la tâche planifiée n\'envoie rien en mode sans API');
T::eq(WhatsAppGateway::link('+221 77 100 00 01', 'Bonjour & merci'), 'https://wa.me/221771000001?text=Bonjour%20%26%20merci', 'lien WhatsApp avec message encodé');
foreach (DB::all('SELECT id FROM marketing_messages WHERE campaign_id = ?', [$wa]) as $r) $mk->markManual((int) $r['id'], true);
T::eq(DB::val('SELECT status FROM marketing_campaigns WHERE id = ?', [$wa]), 'done', 'tous marqués envoyés : campagne terminée');
setting_set('mk_wa_mode', 'cloud');
T::throws(fn() => WhatsAppGateway::send('221771000001', 'Hello'), RuntimeException::class, 'API WhatsApp sans identifiant ni jeton : erreur claire');
setting_set('mk_wa_mode', 'off');
T::throws(fn() => WhatsAppGateway::send('221771000001', 'Hello'), RuntimeException::class, 'WhatsApp désactivé : envoi API refusé');

// Notifications automatiques par WhatsApp : seulement en mode API
$nWa = (int) DB::val("SELECT COUNT(*) FROM notifications WHERE channel = 'whatsapp'");
T::ok($nWa >= 3, 'modèles WhatsApp fournis pour les réservations');

// Suivi publicitaire
$_GET = ['utm_source' => 'Face<book>', 'utm_medium' => 'paid_social', 'gclid' => 'abc_123', 'autre' => 'ignoré']; $_SESSION = [];
TrackingService::capture();
T::eq(TrackingService::attribution(), ['utm_source' => 'Facebook', 'utm_medium' => 'paid_social', 'gclid' => 'abc_123'], 'origine retenue, nettoyée, paramètres inconnus ignorés');
$_GET = []; $_SESSION = [];
$g = $crm->channelByCode('google'); $ig = $crm->channelByCode('instagram'); $fbc = $crm->channelByCode('facebook'); $ads = $crm->channelByCode('ads');
T::ok($g && $fbc && $ads, 'canaux Google, Facebook et « publicité » présents');
T::eq(TrackingService::channelId(['gclid' => 'x']), $g, 'clic Google Ads → canal Google');
T::eq(TrackingService::channelId(['fbclid' => 'x']), $fbc, 'clic Facebook → canal Facebook');
T::eq(TrackingService::channelId(['utm_source' => 'instagram']), $ig, 'source Instagram → canal Instagram');
T::eq(TrackingService::channelId(['utm_source' => 'x', 'utm_medium' => 'cpc']), $ads, 'source inconnue en publicité payante → canal « publicité »');
T::eq(TrackingService::channelId([]), null, 'aucun repère → pas de canal imposé');
$bkAd = $svcB->create(wash_input(next_dow(1, 10), '15:00', 'Unité 15', ['first_name' => 'Ibou', 'last_name' => 'Ndiaye', 'phone' => '77 321 00 99', 'attr' => ['utm_source' => 'facebook', 'utm_medium' => 'paid_social', 'utm_campaign' => 'promo', 'fbclid' => 'IwAR1']]));
$row = DB::one('SELECT utm_source, utm_medium, utm_campaign, fbclid, gclid FROM bookings WHERE id = ?', [$bkAd['id']]);
T::eq($row, ['utm_source' => 'facebook', 'utm_medium' => 'paid_social', 'utm_campaign' => 'promo', 'fbclid' => 'IwAR1', 'gclid' => null], 'origine publicitaire enregistrée avec la réservation');
$p = TrackingService::metaPayload('Lead', 'CU-TEST', ['phone' => '77 100 00 01', 'email' => ' Awa@Example.com ', 'ip' => '1.2.3.4'], 6000, ['fbclid' => 'IwAR1'], 'https://x.sn/', 'TEST1');
$json = json_encode($p);
T::ok(!str_contains($json, '771000001') && !str_contains($json, 'awa@example') && str_contains($json, hash('sha256', '221771000001')) && str_contains($json, hash('sha256', 'awa@example.com')), 'API Conversions : téléphone et email envoyés hachés, jamais en clair');
T::ok($p['data'][0]['event_id'] === 'CU-TEST' && $p['data'][0]['custom_data']['value'] === 6000 && $p['test_event_code'] === 'TEST1' && str_contains($p['data'][0]['user_data']['fbc'], 'IwAR1'), 'événement : identifiant de dédoublonnage, valeur, code de test, clic Facebook');
T::eq(TrackingService::enabled(), false, 'suivi publicitaire inactif par défaut');
T::eq(TrackingService::cspExtra(), ['script-src' => [], 'connect-src' => [], 'img-src' => []], 'inactif : aucun domaine externe autorisé');
setting_set('mk_meta_enabled', '1'); setting_set('mk_meta_pixel_id', 'pas-un-id');
T::eq(TrackingService::metaPixel(), null, 'identifiant de pixel invalide ignoré');
setting_set('mk_meta_pixel_id', '1234567890');
T::eq(TrackingService::metaPixel(), '1234567890', 'pixel valide actif');
T::ok(in_array('https://connect.facebook.net', TrackingService::cspExtra()['script-src'], true), 'pixel actif : domaine Meta autorisé dans la CSP');
setting_set('mk_google_enabled', '1'); setting_set('mk_google_ads_id', 'AW-123456789'); setting_set('mk_google_label_booking', 'AbC-dEfGh1'); setting_set('mk_google_ga4_id', "G-AB12'<x");
T::eq(TrackingService::google(), ['ads' => 'AW-123456789', 'ga4' => null, 'label_booking' => 'AbC-dEfGh1', 'label_lead' => null], 'Google : identifiants validés, valeur piégée refusée');
T::ok(in_array('https://www.googletagmanager.com', TrackingService::cspExtra()['script-src'], true), 'Google actif : domaine autorisé dans la CSP');
foreach (['mk_meta_enabled', 'mk_meta_pixel_id', 'mk_google_enabled', 'mk_google_ads_id', 'mk_google_label_booking', 'mk_google_ga4_id'] as $k) setting_set($k, '');
T::eq(TrackingService::enabled(), false, 'suivi désactivé de nouveau');

// ------------------------------------------------------------------ Consentement, STOP, résultats publicitaires
T::group('Consentement daté, désinscription STOP, résultats publicitaires');
use App\Services\ConsentService;
foreach (['STOP', 'stop', ' Stop. ', 'ARRÊT', 'Arrêt', 'désabonner', 'unsubscribe'] as $w) T::ok(ConsentService::isStop($w), "« $w » retire le consentement");
foreach (['stopper la fuite', 'merci', 'oui', 'je veux un devis', ''] as $w) T::ok(!ConsentService::isStop($w), "« $w » n'est pas une désinscription");
$cons = new ConsentService();
$k1 = $cs->findOrCreate('Ndeye', 'Gueye', '77 400 00 01', null);
$cons->grant($k1, 'reservation');
$row = DB::one('SELECT marketing_consent, marketing_consent_at, marketing_optout_at, marketing_consent_source FROM customers WHERE id = ?', [$k1]);
T::ok($row['marketing_consent'] == 1 && $row['marketing_consent_at'] && $row['marketing_optout_at'] === null && $row['marketing_consent_source'] === 'reservation', 'consentement daté avec sa source');
T::eq((int) DB::val("SELECT COUNT(*) FROM interactions WHERE customer_id = ? AND summary LIKE 'Consentement aux offres donné%'", [$k1]), 1, 'consentement tracé dans l\'historique du client');
$at = $row['marketing_consent_at']; $cons->grant($k1, 'admin');
T::eq(DB::val('SELECT marketing_consent_at FROM customers WHERE id = ?', [$k1]), $at, 'redonner un consentement existant ne change pas sa date');
T::eq($cons->optOutByPhone('+221 77 400 00 01', 'whatsapp'), 1, 'STOP : le client est désinscrit par son numéro');
$row = DB::one('SELECT marketing_consent, marketing_optout_at, marketing_consent_source FROM customers WHERE id = ?', [$k1]);
T::ok($row['marketing_consent'] == 0 && $row['marketing_optout_at'] && $row['marketing_consent_source'] === 'whatsapp', 'désinscription datée avec sa source');
T::eq($cons->optOutByPhone('77 400 00 01', 'sms'), 0, 'déjà désinscrit : rien à faire');
T::eq($cons->optOutByPhone('12', 'sms'), 0, 'numéro invalide ignoré');
T::eq(ConsentService::stamp(['marketing_consent' => 1], ['marketing_consent' => 0])['marketing_consent_source'], 'admin', 'saisie par l\'équipe : horodatage ajouté');
T::eq(array_keys(ConsentService::stamp(['marketing_consent' => 1], ['marketing_consent' => 1])), ['marketing_consent'], 'case inchangée : aucun horodatage');
$bkC = $svcB->create(wash_input(next_dow(1, 17), '15:00', 'Unité 15', ['first_name' => 'Rama', 'last_name' => 'Thiam', 'phone' => '77 400 00 02', 'marketing_consent' => true]));
$cr = DB::one('SELECT marketing_consent, marketing_consent_source FROM customers WHERE id = ?', [$bkC['customer_id']]);
T::ok($cr['marketing_consent'] == 1 && $cr['marketing_consent_source'] === 'reservation', 'case cochée à la réservation : consentement enregistré');
$bkN = $svcB->create(wash_input(next_dow(1, 17), '11:00', 'Unité 15', ['first_name' => 'Pape', 'last_name' => 'Sarr', 'phone' => '77 400 00 03']));
T::eq((int) DB::val('SELECT marketing_consent FROM customers WHERE id = ?', [$bkN['customer_id']]), 0, 'case non cochée : aucun consentement');
$cons->grant((int) $bkN['customer_id'], 'admin'); $svcB->create(wash_input(next_dow(1, 10), '11:00', 'Unité 15', ['first_name' => 'Pape', 'last_name' => 'Sarr', 'phone' => '77 400 00 03']));
T::eq((int) DB::val('SELECT marketing_consent FROM customers WHERE id = ?', [$bkN['customer_id']]), 1, 'une réservation sans la case ne retire jamais un consentement');

DB::update('bookings', (int) $bkAd['id'], ['channel_id' => $fbc]);
DB::insert('expenses', ['company_id' => 1, 'expense_date' => date('Y-m-d'), 'category' => 'marketing', 'label' => 'Facebook Ads', 'amount' => 10000, 'channel_id' => $fbc, 'created_at' => now()]);
DB::insert('expenses', ['company_id' => 1, 'expense_date' => date('Y-m-d'), 'category' => 'marketing', 'label' => 'Google Ads', 'amount' => 5000, 'channel_id' => $g, 'created_at' => now()]);
$rep = $mk->adReport(date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+90 days')));
$pf = array_column($rep['platforms'], null, 'name');
T::ok(isset($pf['Facebook']) && $pf['Facebook']['bookings'] === 1 && $pf['Facebook']['spend'] === 10000 && $pf['Facebook']['cost_per_booking'] === 10000, 'plateforme Facebook : réservation, dépense et coût par réservation');
T::ok(isset($pf['Google (Ads, recherche)']) && $pf['Google (Ads, recherche)']['bookings'] === 0 && $pf['Google (Ads, recherche)']['roi'] === -100, 'plateforme sans réservation : dépense visible, ROI de -100 %');
$adRow = array_values(array_filter($rep['ads'], fn($a) => $a['campaign'] === 'promo'))[0] ?? null;
T::ok($adRow && $adRow['source'] === 'facebook' && $adRow['medium'] === 'paid_social' && $adRow['bookings'] === 1 && !$adRow['internal'], 'annonce « promo » : source, type et réservation');
T::eq($rep['total']['spend'], (int) DB::val('SELECT SUM(amount) FROM expenses WHERE channel_id IS NOT NULL AND deleted_at IS NULL AND expense_date >= ? AND expense_date <= ?', [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+90 days'))]), 'total dépensé = toutes les dépenses rattachées à un canal sur la période');
T::eq($mk->adReport('2000-01-01', '2000-01-31')['platforms'], [], 'période sans données : rien');

// ------------------------------------------------------------------ Parcours simplifiés : lien de suivi, premiers pas
T::group('Lien de suivi personnel, notifications, premiers pas');
use App\Services\OnboardingService;
$tk = DB::one('SELECT reference, track_token FROM bookings WHERE id = ?', [$bkAd['id']]);
T::ok(preg_match('/^[a-f0-9]{32}$/', (string) $tk['track_token']) === 1, 'chaque réservation reçoit un jeton de suivi aléatoire');
T::eq((int) DB::val('SELECT COUNT(DISTINCT track_token) FROM bookings'), (int) DB::val('SELECT COUNT(*) FROM bookings'), 'jetons tous différents');
$link = DB::val("SELECT content FROM notification_logs WHERE booking_id = ? AND channel = 'sms' AND event = 'booking.created'", [$bkAd['id']]);
T::ok($link !== null && str_contains((string) $link, '/suivi/' . $tk['reference'] . '/' . $tk['track_token']), 'le SMS « réservation enregistrée » contient le lien de suivi personnel');
T::ok(str_contains((string) DB::val("SELECT body FROM notifications WHERE event = 'booking.confirmed' AND channel = 'email' AND audience = 'client'"), '{lien_suivi}'), 'modèle email « confirmée » : lien de suivi ajouté');
$steps = OnboardingService::steps();
T::eq(count($steps), 6, 'six étapes de « premiers pas »');
$by = array_column($steps, 'done', 'label');
T::eq($by["Renseigner les coordonnées de l'entreprise"], false, 'coordonnées par défaut : étape à faire');
T::eq($by['Supprimer ou sécuriser les comptes de démonstration'], false, 'comptes de démonstration présents : étape à faire');
T::eq($by["Brancher l'envoi des emails ou des SMS"], false, 'envois en mode test : étape à faire');
setting_set('company_phone', '+221 77 123 45 67'); setting_set('company_email', 'contact@monentreprise.sn');
DB::exec("UPDATE users SET email = REPLACE(email, '@cleanup.test', '@monentreprise.sn')");
setting_set('mk_email_driver', 'smtp');
$by = array_column(OnboardingService::steps(), 'done', 'label');
T::ok($by["Renseigner les coordonnées de l'entreprise"] && $by['Supprimer ou sécuriser les comptes de démonstration'] && $by["Brancher l'envoi des emails ou des SMS"], 'étapes cochées une fois réglées');
setting_set('mk_email_driver', '');

DB::reset();
foreach (['', '-wal', '-shm'] as $s) @unlink($dbFile . $s);
exit(T::summary());
