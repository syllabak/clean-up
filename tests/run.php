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
T::throws(fn() => $svcB->changeStatus((int) $b['id'], 'assigned'), RuntimeException::class, 'statut inconnu (ancien « affectée ») : refusé');
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
T::eq(DB::val('SELECT status FROM bookings WHERE id = ?', [$b['id']]), 'confirmed', 'affectation d\'une équipe → statut « Confirmée »');
T::eq($as->staffCanAccess($moussa, (int) $b['id']), true, "l'agent affecté voit sa mission");
T::eq($as->staffCanAccess($cheikh, (int) $b['id']), false, 'un collègue de la même équipe ne voit pas la mission d\'un autre agent');
T::eq($as->staffCanAccess($awa, (int) $b['id']), false, "un agent d'une autre équipe ne la voit pas");
T::throws(fn() => $as->assign((int) $b['id'], null, $awa, 1), RuntimeException::class, "agent d'une équipe qui ne fait pas ce service : refusé");
$warn = $as->assign((int) $b['id'], null, $awa, 1, true);
T::ok(count($warn) > 0, 'forçage possible, avertissements conservés : ' . implode(' / ', $warn));
T::eq($as->staffCanAccess($moussa, (int) $b['id']), false, "réaffectation : l'ancien agent perd l'accès");
T::eq((int) DB::val('SELECT COUNT(*) FROM booking_assignments WHERE booking_id = ? AND active = 1', [$b['id']]), 1, 'une seule affectation active');
$svcB->changeStatus((int) $b['id'], 'in_progress', 1); $svcB->changeStatus((int) $b['id'], 'completed', 1);
T::eq(DB::val('SELECT status FROM bookings WHERE id = ?', [$b['id']]), 'completed', 'cycle complet confirmée → en cours → terminée');
T::throws(fn() => $svcB->changeStatus((int) $b['id'], 'confirmed'), RuntimeException::class, 'terminée : définitive, retour en arrière refusé');
T::eq(DB::val('SELECT status FROM bookings WHERE id = ?', [$b['id']]), 'completed', 'la commande terminée reste terminée');
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
T::eq(array_map(fn($l) => $l['audience'] . ':' . $l['channel'], $logs), ['client:email', 'admin:email'], 'client avec email : un seul message, par email (pas de SMS en double) + alerte admin');
T::ok(!array_filter($logs, fn($l) => $l['status'] !== 'sent'), 'tous envoyés (pilote « log »)');
$n0 = (int) DB::val('SELECT COUNT(*) FROM notification_logs');
$svcB->create(wash_input($mon, '09:00', 'Unité 15', ['phone' => '77 333 33 33', 'email' => '']));
$new = DB::all('SELECT channel, audience, recipient FROM notification_logs WHERE id > ? ORDER BY id', [$n0]);
T::eq(array_map(fn($l) => $l['audience'] . ':' . $l['channel'], $new), ['client:sms', 'admin:email'], 'client sans email : le SMS prend le relais');
T::ok(str_contains((string) @file_get_contents(BASE_PATH . '/storage/logs/sms.log'), '221773333333'), 'SMS écrit pour le bon numéro');
T::eq(NotificationService::chooseClientChannel('booking.created', ['customer_email' => 'client@example.com', 'customer_phone' => '221771234567']), 'email', 'canal choisi : email si le client en a donné un');
T::eq(NotificationService::chooseClientChannel('booking.created', ['customer_email' => 'pas-un-email', 'customer_phone' => '221771234567']), 'sms', 'canal choisi : email invalide → SMS');
T::eq(NotificationService::chooseClientChannel('booking.created', ['customer_email' => '', 'customer_phone' => '']), null, 'canal choisi : ni email ni téléphone → aucun');
// Email désactivé dans les paramètres : le SMS reprend, même si le client a un email
setting_set(NotificationService::toggleKey('booking.created', 'email'), '0');
T::eq(NotificationService::chooseClientChannel('booking.created', ['customer_email' => 'client@example.com', 'customer_phone' => '221771234567']), 'sms', 'email désactivé pour cet événement : SMS');
setting_set(NotificationService::toggleKey('booking.created', 'email'), '1');
// Ancien comportement, si on le souhaite : email et SMS tous les deux
setting_set('notif_prefer_email', '0');
$n0 = (int) DB::val('SELECT COUNT(*) FROM notification_logs');
$svcB->create(wash_input($mon, '10:00', 'Unité 15', ['phone' => '77 111 11 11', 'email' => 'x@example.com']));
T::eq((int) DB::val('SELECT COUNT(*) FROM notification_logs') - $n0, 3, 'priorité à l\'email désactivée : email et SMS partent tous les deux (comportement d\'avant)');
setting_set('notif_prefer_email', '1');
// Désactivation d'un type de notification
setting_set(NotificationService::toggleKey('booking.created', 'sms'), '0');
$n0 = (int) DB::val('SELECT COUNT(*) FROM notification_logs');
$svcB->create(wash_input($mon, '11:00', 'Unité 15', ['phone' => '77 111 11 12', 'email' => '']));
T::eq((int) DB::val('SELECT COUNT(*) FROM notification_logs') - $n0, 1, 'SMS désactivé et pas d\'email : seule l\'alerte admin part');
setting_set(NotificationService::toggleKey('booking.created', 'sms'), '1');
// Panne SMTP : le client est prévenu par SMS à la place
putenv('MAIL_DRIVER=smtp'); putenv('MAIL_HOST=127.0.0.1'); putenv('MAIL_PORT=1');
$b = $svcB->create(wash_input($mon, '15:00', 'Unité 15', ['phone' => '77 222 22 22', 'email' => 'panne@example.com']));
T::ok($b && $b['id'] > 0, 'panne email : la réservation est quand même enregistrée');
$failed = DB::one("SELECT * FROM notification_logs WHERE booking_id = ? AND channel = 'email' AND audience = 'client'", [$b['id']]);
T::eq([$failed['status'], (int) $failed['attempts']], ['failed', 1], 'envoi échoué journalisé avec 1 tentative');
T::ok(str_contains((string) $failed['error'], 'SMS envoyé à la place') && $failed['next_attempt_at'] === null, 'erreur conservée, SMS de secours signalé, plus de reprise inutile : ' . $failed['error']);
$sms = DB::one("SELECT status, recipient FROM notification_logs WHERE booking_id = ? AND channel = 'sms' AND audience = 'client'", [$b['id']]);
T::ok($sms && $sms['status'] === 'sent' && $sms['recipient'] === '221772222222', 'email impossible : le client reçoit le SMS à la place');
$ns = new NotificationService();
$ns->retryDue();
T::eq((int) DB::val("SELECT COUNT(*) FROM notification_logs WHERE booking_id = ? AND channel = 'sms' AND audience = 'client'", [$b['id']]), 1, 'une seule fois : pas de second SMS sur les reprises');
putenv('MAIL_DRIVER=log');
T::eq($ns->retryNow((int) $failed['id'], true), true, 'relance manuelle de l\'email réussie une fois le service rétabli');
T::eq(DB::val('SELECT status FROM notification_logs WHERE id = ?', [$failed['id']]), 'sent', 'statut passé à « envoyé »');
// Sans SMS disponible (désactivé), l'email échoué reste relancé automatiquement
setting_set(NotificationService::toggleKey('booking.created', 'sms'), '0');
putenv('MAIL_DRIVER=smtp');
$b2 = $svcB->create(wash_input(next_dow(3), '09:00', 'Unité 15', ['phone' => '77 444 44 44', 'email' => 'panne2@example.com']));
$f2 = DB::one("SELECT * FROM notification_logs WHERE booking_id = ? AND channel = 'email' AND audience = 'client'", [$b2['id']]);
T::ok($f2['status'] === 'failed' && $f2['next_attempt_at'] !== null, 'pas de SMS de secours possible : l\'email échoué est relancé plus tard');
putenv('MAIL_DRIVER=log'); setting_set(NotificationService::toggleKey('booking.created', 'sms'), '1');
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

// ------------------------------------------------------------------ Position et photos de la commande
T::group('Position GPS et photos « avant intervention »');
use App\Services\PhotoService;
T::eq(BookingService::coords(['lat' => '14.7601', 'lng' => '-17.4402', 'location_source' => 'gps']), [14.7601, -17.4402, 'gps'], 'position valide conservée');
T::eq(BookingService::coords(['lat' => 14.76, 'lng' => -17.44])[2], 'map', 'source inconnue : « map » par défaut');
T::eq(BookingService::coords(['lat' => 48.85, 'lng' => 2.35]), [null, null, null], 'position hors du Sénégal ignorée');
T::eq(BookingService::coords(['lat' => 'abc', 'lng' => 'x']), [null, null, null], 'position illisible ignorée');
T::eq(BookingService::coords([]), [null, null, null], 'aucune position : la commande reste possible');
$bkG = $svcB->create(wash_input(next_dow(1, 24), '14:00', 'Unité 15', ['first_name' => 'Gora', 'last_name' => 'Fall', 'phone' => '77 700 10 10', 'lat' => 14.7601, 'lng' => -17.4402, 'location_source' => 'gps']));
$gp = DB::one('SELECT lat, lng, location_source FROM bookings WHERE id = ?', [$bkG['id']]);
T::ok(abs((float) $gp['lat'] - 14.7601) < 1e-6 && abs((float) $gp['lng'] + 17.4402) < 1e-6 && $gp['location_source'] === 'gps', 'position enregistrée sur la commande');
$bkN = $svcB->create(wash_input(next_dow(1, 17), '14:00', 'Unité 15', ['first_name' => 'Sans', 'last_name' => 'Position', 'phone' => '77 700 10 11']));
T::eq(DB::val('SELECT lat FROM bookings WHERE id = ?', [$bkN['id']]), null, 'sans position : colonnes vides');
$tmp = sys_get_temp_dir() . '/ph_' . getmypid() . '.png'; file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$img = fn() => ['error' => UPLOAD_ERR_OK, 'tmp_name' => $tmp, 'size' => filesize($tmp), 'name' => 'a.png'];
$ps = new PhotoService(); $pb = DB::one('SELECT id, status FROM bookings WHERE id = ?', [$bkG['id']]);
$first = $ps->addFromClient($pb, $img());
T::ok($first > 0 && $ps->clientCount((int) $bkG['id']) === 1, 'photo du client enregistrée');
T::eq((int) DB::val('SELECT COUNT(*) FROM booking_photos WHERE id = ? AND kind = \'before\' AND user_id IS NULL', [$first]), 1, 'classée « avant », sans agent');
for ($i = 1; $i < PhotoService::MAX_CLIENT; $i++) $ps->addFromClient($pb, $img());
T::throws(fn() => $ps->addFromClient($pb, $img()), RuntimeException::class, 'au-delà de 8 photos : refusé');
T::throws(fn() => $ps->addFromClient(['id' => $bkN['id'], 'status' => 'completed'], $img()), RuntimeException::class, 'commande terminée : plus de photos');
T::throws(fn() => $ps->addFromClient(['id' => $bkN['id'], 'status' => 'pending'], ['error' => UPLOAD_ERR_NO_FILE]), RuntimeException::class, 'aucun fichier : message clair');
@unlink($tmp);

// ------------------------------------------------------------------ Catalogue de Dakar
T::group('Catalogue de Dakar : chargement, prix du marché, réservation');
$dkFile = sys_get_temp_dir() . '/dakar_' . getmypid() . '.db';
DB::reset(); foreach (['', '-wal', '-shm'] as $sfx) @unlink($dkFile . $sfx);
putenv('DB_DRIVER=sqlite'); putenv('DB_SQLITE_PATH=' . $dkFile); $GLOBALS['__settings_dirty'] = true;
\App\Core\Migrator::run();
require_once BASE_PATH . '/database/seeders/DakarCatalogSeeder.php';
use Database\Seeders\DakarCatalogSeeder as Dk;
T::eq(Dk::alreadyLoaded(), false, 'base vide : le catalogue peut se charger');
T::eq(Dk::run(), ['services' => 9, 'neighborhoods' => 33, 'teams' => 4, 'plans' => 10], '9 services, 33 quartiers, 4 équipes, 10 offres d\'abonnement');
T::eq(Dk::alreadyLoaded(), true, 'catalogue chargé : un second chargement est refusé (pas de doublons)');
$cat = new \App\Services\CatalogService();
foreach (DB::all('SELECT id, name FROM services') as $sv) T::eq(array_column(array_filter($cat->readiness((int) $sv['id']), fn($c) => $c[0] === 'bad'), 1), [], $sv['name'] . ' : prêt à être réservé');
T::eq((int) DB::val('SELECT COUNT(*) FROM neighborhoods WHERE lat IS NOT NULL AND lng IS NOT NULL AND lat BETWEEN 14.5 AND 14.9 AND lng BETWEEN -17.6 AND -17.1'), 33, 'les 33 quartiers ont des coordonnées plausibles (région de Dakar)');
$pq = new PricingService(); $plateau = nb('Plateau'); $almadies = nb('Almadies');
$a = svc('lavage-automobile');
T::eq($pq->quote($a, ['formula_id' => formula($a, 'Express'), 'fields' => ['vehicle' => choice($a, 'vehicle', 'Citadine'), 'count' => 1]], $plateau)['total'], 5000, 'lavage Express citadine = 5 000 (repère marché)');
T::eq($pq->quote($a, ['formula_id' => formula($a, 'Complet'), 'fields' => ['vehicle' => choice($a, 'vehicle', 'Citadine'), 'count' => 1]], $plateau)['total'], 9000, 'lavage Complet citadine = 9 000');
T::eq($pq->quote($a, ['formula_id' => formula($a, 'Détaillage'), 'fields' => ['vehicle' => choice($a, 'vehicle', 'Citadine'), 'count' => 1]], $plateau)['total'], 18000, 'lavage Détaillage citadine = 18 000');
T::eq($pq->quote($a, ['formula_id' => formula($a, 'Complet'), 'fields' => ['vehicle' => choice($a, 'vehicle', 'SUV / 4x4'), 'count' => 2]], $almadies)['total'], 11000 * 2 + 1500, 'Complet SUV x2 à Almadies = 22 000 + 1 500 de déplacement');
$c = svc('nettoyage-canapes-fauteuils');
T::eq($pq->quote($c, ['fields' => ['seats' => choice($c, 'seats', 'Canapé 3 places'), 'material' => choice($c, 'material', 'Tissu')]], $plateau)['total'], 15000, 'canapé 3 places = 15 000 (5 000 la place)');
$m = svc('nettoyage-matelas');
T::eq($pq->quote($m, ['fields' => ['size' => choice($m, 'size', '2 places (140 cm)'), 'count' => 1]], $plateau)['total'], 20000, 'matelas 2 places = 20 000 (repère marché)');
$t = svc('nettoyage-tapis-moquettes');
T::eq($pq->quote($t, ['formula_id' => formula($t, 'Tapis'), 'fields' => ['area' => 10]], $plateau)['total'], 25000, 'tapis 10 m² = 25 000 (2 500 le m²)');
$vt = svc('nettoyage-vitres');
T::eq($pq->quote($vt, ['fields' => ['type' => choice($vt, 'type', 'Fenêtre'), 'count' => 4]], $plateau)['total'], 6000, '4 fenêtres = 6 000');
$rp = svc('repassage-domicile');
T::eq($pq->quote($rp, ['formula_id' => formula($rp, 'Panier de linge'), 'fields' => ['count' => 2]], $plateau)['total'], 7000, '2 paniers de repassage = 7 000 (3 500 le panier)');
$bk = (new BookingService())->create(['first_name' => 'Awa', 'last_name' => 'Diop', 'phone' => '77 612 34 56', 'neighborhood_id' => $plateau, 'address_text' => 'Rue 10, près de la poste', 'date' => next_dow(2, 3), 'start' => '09:00',
    'service_id' => $a, 'formula_id' => formula($a, 'Complet'), 'fields' => ['vehicle' => choice($a, 'vehicle', 'Citadine'), 'count' => 1]]);
T::ok($bk['status'] === 'pending' && (int) $bk['total'] === 9000, 'une réservation de lavage dans le catalogue de Dakar passe : ' . $bk['reference']);
// ------------------------------------------------------------------ Abonnements
T::group('Abonnements : rythme, demande, activation, passages, pause et résiliation');
use App\Services\SubscriptionService as Sub;
$sb = new Sub();
$planM = (int) DB::val("SELECT id FROM subscription_plans WHERE name = 'Ménage à domicile, chaque semaine'");
$planB = (int) DB::val("SELECT id FROM subscription_plans WHERE audience = 'business' ORDER BY id LIMIT 1");
T::eq((int) DB::val('SELECT discount_percent FROM subscription_plans WHERE id = ?', [$planM]), 10, 'offre de départ : ménage hebdomadaire à −10 %');
$svM = (int) DB::val('SELECT service_id FROM subscription_plans WHERE id = ?', [$planM]);
$fM = (int) DB::val('SELECT id FROM service_prices WHERE service_id = ? ORDER BY sort LIMIT 1', [$svM]);
$fieldsM = []; foreach (DB::all('SELECT id, field_key FROM service_fields WHERE service_id = ?', [$svM]) as $ff) $fieldsM[$ff['field_key']] = (string) DB::val('SELECT id FROM service_field_choices WHERE field_id = ? ORDER BY sort LIMIT 1', [$ff['id']]);
$subIn = ['plan_id' => $planM, 'kind' => 'person', 'first_name' => 'Awa', 'last_name' => 'Diop', 'phone' => '77 612 34 56', 'neighborhood_id' => $plateau, 'address_text' => 'Rue Carnot, 2e étage',
    'weekday' => 2, 'start_time' => '09:00', 'start_date' => date('Y-m-d', strtotime('+2 day')), 'formula_id' => $fM, 'fields' => $fieldsM];

// Rythme : un jour fixe de la semaine, un intervalle constant
$probe = ['start_date' => '2026-12-01', 'weekday' => 4, 'frequency' => 'weekly'];   // le 1er décembre 2026 est un mardi
T::eq(Sub::anchor($probe), '2026-12-03', 'premier passage : le premier jeudi à partir de la date de début');
T::eq(Sub::dates($probe, '2026-12-01', '2026-12-24'), ['2026-12-03', '2026-12-10', '2026-12-17', '2026-12-24'], 'chaque semaine : tous les 7 jours');
T::eq(Sub::dates(['frequency' => 'biweekly'] + $probe, '2026-12-01', '2027-01-10'), ['2026-12-03', '2026-12-17', '2026-12-31'], 'toutes les 2 semaines : tous les 14 jours');
T::eq(Sub::dates(['frequency' => 'quarterly'] + $probe, '2026-12-01', '2027-04-30'), ['2026-12-03', '2027-03-04'], 'tous les 3 mois : 13 semaines');
T::eq(Sub::dates($probe, '2026-12-11', '2026-12-18'), ['2026-12-17'], 'seules les dates de la période demandée sont renvoyées, le rythme reste calé sur le début');

// Demande : validations
T::throws(fn() => $sb->request(['kind' => 'business', 'business_name' => ''] + $subIn), ValidationException::class, 'entreprise sans nom : refusé');
T::throws(fn() => $sb->request(['weekday' => 7] + $subIn), ValidationException::class, 'dimanche : refusé');
T::throws(fn() => $sb->request(['start_date' => date('Y-m-d')] + $subIn), ValidationException::class, 'début aujourd\'hui : refusé (au plus tôt demain)');
T::throws(fn() => $sb->request(['formula_id' => 0] + $subIn), ValidationException::class, 'formule manquante : refusée par le calcul du prix');
T::throws(fn() => $sb->request(['plan_id' => $planB] + $subIn), ValidationException::class, 'offre entreprises demandée par un particulier : refusé');
T::throws(fn() => $sb->request(['plan_id' => 99999] + $subIn), ValidationException::class, 'offre inconnue : refusée');
T::eq((int) DB::val('SELECT COUNT(*) FROM subscriptions'), 0, 'demandes refusées : rien enregistré');
$sub = $sb->request($subIn);
T::ok($sub['status'] === 'requested' && (int) $sub['discount_percent'] === 10 && preg_match('/^[a-f0-9]{32}$/', $sub['track_token']), 'demande enregistrée : statut « demande », remise de l\'offre, lien personnel');
T::eq((int) $sb->request($subIn)['id'], (int) $sub['id'], 'même demande envoyée deux fois : une seule');
T::eq(Sub::dates($sub + ['frequency' => 'weekly'], date('Y-m-d'), date('Y-m-d', strtotime('+1 day'))), [], 'avant activation, aucun passage n\'existe');
T::eq((int) DB::val('SELECT COUNT(*) FROM bookings WHERE subscription_id IS NOT NULL'), 0, 'une simple demande ne crée aucune réservation');
$est = $sb->estimate($sub);
T::eq($est['discount'], (int) round($est['subtotal'] * 0.10), 'estimation : remise de 10 % sur le sous-total');
T::eq($est['total'], $est['subtotal'] - $est['discount'] + $est['travel_fee'], 'estimation : total = sous-total − remise + déplacement');

// Activation et génération
$logsBefore = (int) DB::val('SELECT COUNT(*) FROM notification_logs');
$g = $sb->activate((int) $sub['id'], [], null);
T::ok($g['booked'] >= 2 && $g['unavailable'] === 0, 'activation : ' . $g['booked'] . ' passages planifiés sur 21 jours');
$vis = DB::all('SELECT b.* FROM bookings b WHERE b.subscription_id = ? ORDER BY b.scheduled_date', [(int) $sub['id']]);
T::ok(count($vis) === $g['booked'] && !array_filter($vis, fn($b) => $b['status'] !== 'confirmed'), 'les passages sont des réservations confirmées dans le planning');
T::ok(!array_filter($vis, fn($b) => (int) date('N', strtotime($b['scheduled_date'])) !== 2 || $b['start_time'] !== '09:00'), 'tous le mardi à 09:00');
T::ok(!array_filter($vis, fn($b) => (int) $b['discount'] !== (int) round($b['subtotal'] * 0.10) || (int) $b['total'] !== (int) $b['subtotal'] - (int) $b['discount'] + (int) $b['travel_fee']), 'chaque passage : remise de 10 % appliquée au total');
T::eq((int) DB::val('SELECT COUNT(*) FROM notification_logs'), $logsBefore, 'passages générés : aucun message envoyé au client (pas de « réservation enregistrée » en rafale)');
T::eq($sb->generate((int) $sub['id'])['booked'], 0, 'générer deux fois : aucun doublon (idempotent)');
T::eq((int) DB::val('SELECT COUNT(*) FROM subscription_visits WHERE subscription_id = ?', [(int) $sub['id']]), count($vis), 'un passage suivi par date');
T::throws(fn() => $sb->activate((int) $sub['id'], [], null), \RuntimeException::class, 'activer deux fois : refusé');
// Un passage annulé à la main n'est pas recréé : le client saute cette date seulement.
(new BookingService())->changeStatus((int) $vis[0]['id'], 'cancelled', null, 'Je suis absent', true);
T::eq($sb->generate((int) $sub['id'])['booked'], 0, 'passage annulé à la main : la date n\'est pas recréée, l\'abonnement continue');

// Créneau habituel impossible : décalage à l'heure libre la plus proche le même jour
DB::exec('UPDATE subscriptions SET start_time = ? WHERE id = ?', ['17:50', (int) $sub['id']]);
DB::exec('DELETE FROM subscription_visits WHERE subscription_id = ? AND visit_date = ?', [(int) $sub['id'], $vis[1]['scheduled_date']]);
DB::exec('UPDATE bookings SET status = ? WHERE id = ?', ['cancelled', (int) $vis[1]['id']]);
$sb->generate((int) $sub['id']);
$moved = DB::one('SELECT b.start_time, v.note FROM subscription_visits v JOIN bookings b ON b.id = v.booking_id WHERE v.subscription_id = ? AND v.visit_date = ?', [(int) $sub['id'], $vis[1]['scheduled_date']]);
T::ok($moved && $moved['start_time'] !== '17:50' && str_contains((string) $moved['note'], 'décalée'), 'heure habituelle impossible : passage décalé à l\'heure libre la plus proche (' . ($moved['start_time'] ?? '?') . ')');
DB::exec('UPDATE subscriptions SET start_time = ? WHERE id = ?', ['09:00', (int) $sub['id']]);

// Pause, reprise, résiliation
$open = fn() => (int) DB::val("SELECT COUNT(*) FROM bookings WHERE subscription_id = ? AND status IN ('pending','confirmed') AND scheduled_date >= ?", [(int) $sub['id'], date('Y-m-d', strtotime('+1 day'))]);
T::ok($open() >= 2, 'avant la pause : des passages à venir');
$logsBefore = (int) DB::val('SELECT COUNT(*) FROM notification_logs');
$sb->pause((int) $sub['id'], null, 'client', null);
T::eq([$open(), DB::val('SELECT status FROM subscriptions WHERE id = ?', [(int) $sub['id']])], [0, 'paused'], 'pause : les passages à venir sont annulés');
T::eq((int) DB::val('SELECT COUNT(*) FROM notification_logs'), $logsBefore, 'pause : aucun message d\'annulation envoyé');
T::eq($sb->generate((int) $sub['id']), ['booked' => 0, 'unavailable' => 0], 'en pause : rien n\'est généré');
T::throws(fn() => $sb->pause((int) $sub['id'], null, 'client', null), \RuntimeException::class, 'pause d\'un abonnement déjà en pause : refusée');
$r = $sb->resume((int) $sub['id'], null);
T::ok($r['booked'] >= 2 && $open() >= 2, 'reprise : les passages sont de nouveau planifiés');
DB::exec('UPDATE subscriptions SET status = ?, paused_until = ? WHERE id = ?', ['paused', date('Y-m-d'), (int) $sub['id']]);
$auto = $sb->generateAll();
T::ok(DB::val('SELECT status FROM subscriptions WHERE id = ?', [(int) $sub['id']]) === 'active' && $auto['subscriptions'] >= 1, 'tâche planifiée : une pause arrivée à échéance reprend toute seule');
$sb->cancel((int) $sub['id'], 'Déménagement', null);
T::eq([$open(), DB::val('SELECT status FROM subscriptions WHERE id = ?', [(int) $sub['id']])], [0, 'cancelled'], 'résiliation : passages à venir annulés');
T::eq($sb->generate((int) $sub['id']), ['booked' => 0, 'unavailable' => 0], 'abonnement résilié : plus aucun passage');
// Une réservation en ligne ne peut pas s'attribuer une remise d'abonnement
$hack = (new BookingService())->create(['first_name' => 'Test', 'last_name' => 'Remise', 'phone' => '77 500 00 01', 'neighborhood_id' => $plateau, 'address_text' => 'Rue 1, Plateau', 'date' => date('Y-m-d', strtotime('+9 day')), 'start' => '10:00',
    'service_id' => $svM, 'formula_id' => $fM, 'fields' => $fieldsM, 'subscription_id' => 0, 'discount_percent' => 50]);
T::eq([(int) $hack['discount'], $hack['subscription_id']], [0, null], 'réservation ordinaire : aucune remise ni abonnement, même si on les demande');

// ------------------------------------------------------------------ Paiement en ligne (PayTech, PayDunya)
T::group('Paiement en ligne : PayTech et PayDunya (API simulée)');
use App\Services\PaymentService as Pay;
$pay = new Pay(); $calls = [];
Pay::$transport = function (string $m, string $u, array $h, ?string $b) use (&$calls, &$reply) { $calls[] = compact('m', 'u', 'h', 'b'); return $reply($m, $u, $h, $b); };
$mkBooking = fn(string $h, string $ph) => (new BookingService())->create(['first_name' => 'Awa', 'last_name' => 'Diop', 'phone' => $ph, 'email' => 'awa@exemple.sn', 'neighborhood_id' => $plateau, 'address_text' => 'Rue Carnot, 2e étage',
    'date' => (function () use ($ph) { $d = 8 + 2 * (int) substr($ph, -2); while ((int) date('N', strtotime("+$d day")) === 7) $d++; return date('Y-m-d', strtotime("+$d day")); })(), 'start' => $h, 'service_id' => $svM, 'formula_id' => $fM, 'fields' => $fieldsM]);
$bk1 = $mkBooking('09:00', '77 600 00 01');
T::eq(Pay::available(), [], 'rien n\'est proposé tant que le paiement en ligne n\'est pas activé');
T::throws(fn() => $pay->start($bk1, 'paytech'), \RuntimeException::class, 'paiement refusé si le prestataire n\'est pas proposé');
foreach (['pay_mode' => 'optional', 'paytech_enabled' => '1', 'paytech_env' => 'test', 'paytech_api_key' => 'KEY123', 'paytech_api_secret' => 'SECRET456'] as $k => $v) setting_set('mk_' . $k, $v);
T::eq(array_keys(Pay::available()), ['paytech'], 'PayTech complet et activé : proposé au client');
setting_set('mk_paytech_api_secret', '');
T::eq(Pay::available(), [], 'clé secrète manquante : PayTech n\'est pas proposé');
setting_set('mk_paytech_api_secret', 'SECRET456');
$reply = fn($m, $u, $h, $b) => [200, json_encode(['success' => 1, 'token' => 'tok_pt_1', 'redirect_url' => 'https://paytech.sn/payment/checkout/tok_pt_1'])];
$url = $pay->start($bk1, 'paytech');
$call = $calls[0]; $sent = json_decode($call['b'], true);
T::eq($url, 'https://paytech.sn/payment/checkout/tok_pt_1', 'PayTech : adresse de paiement renvoyée');
T::ok($call['u'] === 'https://paytech.sn/api/payment/request-payment' && in_array('API_KEY: KEY123', $call['h'], true) && in_array('API_SECRET: SECRET456', $call['h'], true), 'PayTech : bonne adresse d\'API et clés en en-têtes');
T::ok($sent['item_price'] === (int) $bk1['total'] && $sent['currency'] === 'XOF' && $sent['env'] === 'test' && str_starts_with($sent['ref_command'], $bk1['reference'] . '-') && str_contains($sent['success_url'], '/paiement/retour/' . $bk1['reference']), 'PayTech : montant de la réservation, XOF, référence unique, adresse de retour');
$pm1 = DB::one('SELECT * FROM payments WHERE booking_id = ?', [(int) $bk1['id']]);
T::ok($pm1['status'] === 'pending' && $pm1['token'] === 'tok_pt_1' && (int) $pm1['amount'] === (int) $bk1['total'], 'paiement enregistré « en attente » avec son jeton');
// Notification PayTech
$ipn = ['type_event' => 'sale_complete', 'ref_command' => $pm1['ref'], 'item_price' => (string) $pm1['amount'], 'payment_method' => 'Wave'];
T::throws(fn() => $pay->paytechIpn($ipn), \RuntimeException::class, 'IPN PayTech sans signature : refusée');
T::throws(fn() => $pay->paytechIpn($ipn + ['hmac_compute' => 'faux']), \RuntimeException::class, 'IPN PayTech avec fausse signature : refusée');
T::eq(DB::val('SELECT paid_at FROM bookings WHERE id = ?', [(int) $bk1['id']]), null, 'IPN refusées : la réservation n\'est pas marquée payée');
$hmac = fn(array $d) => hash_hmac('sha256', $d['item_price'] . '|' . $d['ref_command'] . '|KEY123', 'SECRET456');
T::eq($pay->paytechIpn($ipn + ['hmac_compute' => $hmac($ipn)]), 'paid', 'IPN PayTech signée (HMAC-SHA256) : payé');
$bkRow = DB::one('SELECT * FROM bookings WHERE id = ?', [(int) $bk1['id']]);
T::ok($bkRow['paid_at'] !== null && (int) $bkRow['declared_amount'] === (int) $bk1['total'] && str_contains((string) $bkRow['payment_note'], 'Wave'), 'réservation marquée payée : montant encaissé et mode de paiement notés');
$h0 = (int) DB::val('SELECT COUNT(*) FROM booking_status_history WHERE booking_id = ?', [(int) $bk1['id']]);
T::eq($pay->paytechIpn($ipn + ['hmac_compute' => $hmac($ipn)]), 'paid', 'même notification reçue deux fois : sans effet');
T::eq((int) DB::val('SELECT COUNT(*) FROM booking_status_history WHERE booking_id = ?', [(int) $bk1['id']]), $h0, 'pas de doublon dans l\'historique');
T::throws(fn() => $pay->start(DB::one('SELECT b.*, s.name AS service_name, c.first_name AS customer_first_name, c.last_name AS customer_last_name, c.email AS customer_email, c.phone AS customer_phone FROM bookings b JOIN services s ON s.id = b.service_id JOIN customers c ON c.id = b.customer_id WHERE b.id = ?', [(int) $bk1['id']]), 'paytech'), \RuntimeException::class, 'réservation déjà payée : pas de second paiement');
// Méthode SHA256 classique, annulation, référence inconnue
$bk2 = $mkBooking('09:00', '77 600 00 02'); $pay->start($bk2, 'paytech'); $pm2 = DB::one('SELECT * FROM payments WHERE booking_id = ?', [(int) $bk2['id']]);
$sha = ['api_key_sha256' => hash('sha256', 'KEY123'), 'api_secret_sha256' => hash('sha256', 'SECRET456')];
T::eq($pay->paytechIpn(['type_event' => 'sale_canceled', 'ref_command' => $pm2['ref'], 'item_price' => (string) $pm2['amount']] + $sha), 'cancelled', 'IPN PayTech (hachage SHA256) : annulation prise en compte');
T::eq(DB::val('SELECT status FROM payments WHERE id = ?', [(int) $pm2['id']]), 'cancelled', 'paiement passé à « annulé »');
T::eq($pay->paytechIpn(['type_event' => 'sale_complete', 'ref_command' => 'INCONNUE', 'item_price' => '100'] + $sha), 'ignored', 'référence inconnue : ignorée sans erreur');
// Montant différent en production : jamais crédité
setting_set('mk_paytech_env', 'prod');
$bk3 = $mkBooking('09:00', '77 600 00 03'); $pay->start($bk3, 'paytech'); $pm3 = DB::one('SELECT * FROM payments WHERE booking_id = ?', [(int) $bk3['id']]);
T::eq(json_decode($calls[array_key_last($calls)]['b'], true)['env'], 'prod', 'environnement de production transmis à PayTech');
$bad = ['type_event' => 'sale_complete', 'ref_command' => $pm3['ref'], 'item_price' => '100', 'payment_method' => 'Wave']; $bad['hmac_compute'] = $hmac($bad);
T::eq($pay->paytechIpn($bad), 'ignored', 'production : montant notifié différent du montant demandé → pas crédité');
T::ok(DB::val('SELECT paid_at FROM bookings WHERE id = ?', [(int) $bk3['id']]) === null && str_contains((string) DB::val('SELECT error FROM payments WHERE id = ?', [(int) $pm3['id']]), 'différent'), 'écart signalé pour vérification manuelle');
setting_set('mk_paytech_env', 'test');
// Pannes de PayTech
$reply = fn($m, $u, $h, $b) => [200, json_encode(['success' => 0, 'message' => 'Clés invalides'])];
$bk4 = $mkBooking('09:00', '77 600 00 04');
try { $pay->start($bk4, 'paytech'); T::ok(false, 'refus de PayTech : exception attendue'); } catch (\RuntimeException $e) { T::ok(str_contains($e->getMessage(), 'momentanément') && str_contains($e->getMessage(), 'après l\'intervention'), 'refus de PayTech : message simple, le client peut payer à l\'équipe'); }
T::eq(DB::val("SELECT status FROM payments WHERE booking_id = ?", [(int) $bk4['id']]), 'failed', 'tentative échouée journalisée');
$reply = fn($m, $u, $h, $b) => [200, json_encode(['success' => 1, 'token' => 'x', 'redirect_url' => 'https://pirate.example/pay'])];
T::throws(fn() => $pay->start($bk4, 'paytech'), \RuntimeException::class, 'adresse de paiement hors https://paytech.sn... : refusée');
T::ok(count(array_filter(DB::all('SELECT checkout_url FROM payments WHERE booking_id = ?', [(int) $bk4['id']]), fn($r) => $r['checkout_url'])) === 0, 'adresse suspecte jamais enregistrée');

// PayDunya
foreach (['paydunya_enabled' => '1', 'paydunya_mode' => 'test', 'paydunya_master_key' => 'MASTER', 'paydunya_private_key' => 'test_private_X', 'paydunya_token' => 'TOKENX', 'paydunya_channels' => 'wave-senegal, orange-money-senegal'] as $k => $v) setting_set('mk_' . $k, $v);
T::eq(array_keys(Pay::available()), ['paytech', 'paydunya'], 'PayTech et PayDunya proposés ensemble');
$bk5 = $mkBooking('09:00', '77 600 00 05');
$reply = fn($m, $u, $h, $b) => [200, json_encode(['response_code' => '00', 'response_text' => 'https://app.paydunya.com/sandbox-checkout/invoice/test_AAA', 'description' => 'Checkout Invoice Created', 'token' => 'test_AAA'])];
T::eq($pay->start($bk5, 'paydunya'), 'https://app.paydunya.com/sandbox-checkout/invoice/test_AAA', 'PayDunya : adresse de paiement renvoyée');
$call = $calls[array_key_last($calls)]; $sent = json_decode($call['b'], true);
T::ok($call['u'] === 'https://app.paydunya.com/sandbox-api/v1/checkout-invoice/create' && in_array('PAYDUNYA-MASTER-KEY: MASTER', $call['h'], true) && in_array('PAYDUNYA-PRIVATE-KEY: test_private_X', $call['h'], true) && in_array('PAYDUNYA-TOKEN: TOKENX', $call['h'], true), 'PayDunya : API de test et trois clés en en-têtes');
T::ok($sent['invoice']['total_amount'] === (int) $bk5['total'] && $sent['invoice']['channels'] === ['wave-senegal', 'orange-money-senegal'] && str_ends_with($sent['actions']['callback_url'], '/webhooks/paydunya') && $sent['invoice']['customer']['phone'] === '776000005', 'PayDunya : montant, moyens de paiement choisis, adresse de rappel, client prérempli');
$pm5 = DB::one('SELECT * FROM payments WHERE booking_id = ?', [(int) $bk5['id']]);
$confirm = fn(string $status, int $total, string $hash = null) => fn($m, $u, $h, $b) => [200, json_encode(['response_code' => '00', 'response_text' => 'Transaction Found', 'hash' => $hash ?? hash('sha512', 'MASTER'), 'status' => $status, 'invoice' => ['token' => 'test_AAA', 'total_amount' => $total]])];
$cb = ['data' => ['hash' => hash('sha512', 'MASTER'), 'status' => 'completed', 'invoice' => ['token' => 'test_AAA']]];
T::throws(fn() => $pay->paydunyaCallback(['data' => ['hash' => 'faux', 'invoice' => ['token' => 'test_AAA']]]), \RuntimeException::class, 'rappel PayDunya avec fausse signature : refusé');
$reply = $confirm('completed', (int) $pm5['amount'] + 500);
T::eq($pay->paydunyaCallback($cb), 'pending', 'PayDunya : montant confirmé différent → pas crédité');
$reply = $confirm('completed', (int) $pm5['amount'], 'faux');
T::eq($pay->paydunyaCallback($cb), 'pending', 'PayDunya : réponse de confirmation non authentifiée → pas crédité');
$reply = fn($m, $u, $h, $b) => [500, 'panne'];
T::eq($pay->paydunyaCallback($cb), 'pending', 'PayDunya injoignable : en attente (le prestataire réessaiera)');
T::eq(DB::val('SELECT paid_at FROM bookings WHERE id = ?', [(int) $bk5['id']]), null, 'aucune des tentatives douteuses n\'a marqué la réservation payée');
$reply = $confirm('completed', (int) $pm5['amount']);
T::eq($pay->paydunyaCallback($cb), 'paid', 'PayDunya : rappel signé et confirmé auprès de l\'API → payé');
T::ok(str_ends_with($calls[array_key_last($calls)]['u'], '/sandbox-api/v1/checkout-invoice/confirm/test_AAA') && $calls[array_key_last($calls)]['m'] === 'GET', 'le statut est vérifié par un appel serveur à serveur');
T::ok(DB::val('SELECT paid_at FROM bookings WHERE id = ?', [(int) $bk5['id']]) !== null && str_contains((string) DB::val('SELECT payment_note FROM bookings WHERE id = ?', [(int) $bk5['id']]), 'PayDunya'), 'réservation payée via PayDunya');
$bk6 = $mkBooking('09:00', '77 600 00 06');
$reply = fn($m, $u, $h, $b) => [200, json_encode(['response_code' => '00', 'response_text' => 'https://app.paydunya.com/sandbox-checkout/invoice/test_BBB', 'token' => 'test_BBB'])];
$pay->start($bk6, 'paydunya'); $pm6 = DB::one('SELECT * FROM payments WHERE booking_id = ?', [(int) $bk6['id']]);
$reply = $confirm('cancelled', (int) $pm6['amount']);
T::eq($pay->reconcile($pm6), 'cancelled', 'retour du client après annulation : statut « annulé » confirmé par l\'API');
// Test de connexion de l'administration
$reply = fn($m, $u, $h, $b) => [200, json_encode(['success' => 1, 'token' => 'tok_test', 'redirect_url' => 'https://paytech.sn/payment/checkout/tok_test'])];
T::ok(str_contains($pay->testConnection('paytech'), 'réussie'), 'test de connexion PayTech : réussi');
T::eq(json_decode($calls[array_key_last($calls)]['b'], true)['env'], 'test', 'le test de connexion se fait toujours en environnement de test');
setting_set('mk_paytech_api_key', '');
T::throws(fn() => $pay->testConnection('paytech'), \RuntimeException::class, 'test de connexion sans clé : message clair');
setting_set('mk_paytech_api_key', 'KEY123');
$st = $pay->status();
T::ok(str_contains($st['payment'], 'PayTech') && str_contains($st['payment'], 'PayDunya') && str_contains($st['email'], 'test'), 'résumé des réglages lisible');
setting_set('mk_pay_mode', 'after'); T::eq(Pay::available(), [], 'mode « après l\'intervention » : plus aucun paiement en ligne proposé');
Pay::$transport = null;
foreach (['pay_mode', 'paytech_enabled', 'paytech_env', 'paytech_api_key', 'paytech_api_secret', 'paydunya_enabled', 'paydunya_mode', 'paydunya_master_key', 'paydunya_private_key', 'paydunya_token', 'paydunya_channels'] as $k) setting_set('mk_' . $k, '');

DB::reset(); foreach (['', '-wal', '-shm'] as $sfx) @unlink($dkFile . $sfx);

DB::reset();
foreach (['', '-wal', '-shm'] as $s) @unlink($dbFile . $s);
exit(T::summary());
