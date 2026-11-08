<?php
// Tests HTTP de bout en bout : démarre le serveur PHP intégré sur une base temporaire. Lancer : php tests/smoke.php
declare(strict_types=1);
define('TESTING', true);
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/TestCase.php';

$db = sys_get_temp_dir() . '/lavage_smoke_' . getmypid() . '.db';
$port = random_int(20000, 40000);
$env = ['DB_DRIVER' => 'sqlite', 'DB_SQLITE_PATH' => $db, 'MAIL_DRIVER' => 'log', 'SMS_DRIVER' => 'log', 'APP_DEBUG' => 'false', 'CRON_TOKEN' => 'jeton-de-test-123', 'APP_URL' => "http://127.0.0.1:$port"];
foreach ($env as $k => $v) putenv("$k=$v");
fresh_db($db);
\App\Core\Database::reset();

$srv = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', BASE_PATH . '/public', BASE_PATH . '/public/index.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $p, BASE_PATH, array_merge(getenv(), $env));
register_shutdown_function(function () use ($srv, $db) { proc_terminate($srv); foreach (['', '-wal', '-shm'] as $s) @unlink($db . $s); });
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $port)) break; usleep(100000); }

final class Client
{
    public array $cookies = [];
    public function __construct(private int $port) {}
    /** @return array{0:int,1:array,2:string} */
    public function req(string $method, string $path, array|string|null $body = null, array $headers = [], bool $json = false): array
    {
        $h = $headers;
        if ($this->cookies) $h[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        $content = null;
        if ($json) { $h[] = 'Content-Type: application/json'; $h[] = 'Accept: application/json'; $content = json_encode($body); }
        elseif (is_array($body)) { $h[] = 'Content-Type: application/x-www-form-urlencoded'; $content = http_build_query($body); }
        elseif (is_string($body)) $content = $body;
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $h), 'content' => $content, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15]]);
        $res = @file_get_contents("http://127.0.0.1:{$this->port}$path", false, $ctx);
        $status = 0; $hd = [];
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) $status = (int) $m[1];
            elseif (str_contains($line, ':')) { [$k, $v] = explode(':', $line, 2); $hd[strtolower(trim($k))][] = trim($v);
                if (strtolower(trim($k)) === 'set-cookie') { [$kv] = explode(';', trim($v)); [$ck, $cv] = explode('=', $kv, 2); $this->cookies[$ck] = $cv; } }
        }
        return [$status, $hd, (string) $res];
    }
    public function get(string $p): array { return $this->req('GET', $p); }
    public function post(string $p, array $d = []): array { return $this->req('POST', $p, $d); }
    public function postJson(string $p, array $d, string $csrf): array { return $this->req('POST', $p, $d, ['X-CSRF-Token: ' . $csrf], true); }
    public function token(string $path = '/connexion'): string { [, , $b] = $this->get($path); preg_match('/name="_csrf" value="([a-f0-9]+)"/', $b, $m) || preg_match('/data-csrf="([a-f0-9]+)"/', $b, $m); return $m[1] ?? ''; }
    public function login(string $email, string $pwd): int { $t = $this->token(); [$s] = $this->post('/connexion', ['_csrf' => $t, 'email' => $email, 'password' => $pwd]); return $s; }
    public function multipart(string $path, array $fields, string $fileField, string $filename, string $content, string $mime): array
    {
        $b = '----t' . bin2hex(random_bytes(6)); $body = '';
        foreach ($fields as $k => $v) $body .= "--$b\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
        $body .= "--$b\r\nContent-Disposition: form-data; name=\"$fileField\"; filename=\"$filename\"\r\nContent-Type: $mime\r\n\r\n$content\r\n--$b--\r\n";
        return $this->req('POST', $path, $body, ["Content-Type: multipart/form-data; boundary=$b"]);
    }
}

$PNG = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

// ------------------------------------------------------------------ Pages publiques
T::group('Pages publiques et PWA');
$c = new Client($port);
foreach (['/' => 'Net', '/services' => 'Nos services', '/services/lavage-automobile' => 'Choisissez votre formule', '/reserver' => 'Réserver', '/faq' => 'Questions', '/contact' => 'Contact', '/a-propos' => 'À propos',
          '/conditions-generales' => 'Conditions', '/confidentialite' => 'confidentialité', '/suivi' => 'Suivre', '/connexion' => 'Espace équipe', '/hors-ligne' => 'hors connexion'] as $path => $needle) {
    [$s, , $b] = $c->get($path);
    T::ok($s === 200 && stripos($b, $needle) !== false, "GET $path → 200");
}
[$s] = $c->get('/page-inexistante'); T::eq($s, 404, 'URL inconnue → 404');
[$s] = $c->get('/services/n-existe-pas'); T::eq($s, 404, 'service inconnu → 404');
[$s, $h, $b] = $c->get('/manifest.webmanifest'); T::ok($s === 200 && json_decode($b, true)['display'] === 'standalone', 'manifest PWA valide');
[$s, , $b] = $c->get('/agent.webmanifest'); T::ok($s === 200 && json_decode($b, true)['start_url'] === 'agent', 'manifest agent valide');
[$s, , $b] = $c->get('/sw.js'); T::ok($s === 200 && str_contains($b, 'NEVER') && str_contains($b, 'admin|agent|api|reservation'), 'service worker exclut admin/agent/api/réservation du cache');
[$s] = $c->get('/assets/icons/icon-512.png'); T::eq($s, 200, 'icône PNG servie');
[$s, $h] = $c->get('/'); T::ok(isset($h['content-security-policy']) && isset($h['x-content-type-options']), 'en-têtes de sécurité présents (CSP, nosniff)');
[, , $b] = $c->get('/'); T::ok(!preg_match('#<script(?![^>]*\bsrc=)[^>]*>#i', $b), 'aucun script inline sur la page d\'accueil (compatible CSP)');
[, , $b] = $c->get('/reserver'); T::ok(!preg_match('#<script(?![^>]*\b(src|type)=)[^>]*>#i', $b), 'aucun script exécutable inline sur la page de réservation');

// ------------------------------------------------------------------ Sécurité des accès
T::group('Accès, CSRF, authentification');
$anon = new Client($port);
[$s, $h] = $anon->get('/admin'); T::ok($s === 302 && str_contains($h['location'][0], '/connexion'), '/admin sans connexion → redirigé vers /connexion');
[$s, $h] = $anon->get('/agent'); T::ok($s === 302, '/agent sans connexion → redirigé');
[$s] = $anon->get('/admin/crud/users'); T::eq($s, 302, '/admin/crud/users sans connexion → redirigé');
[$s] = $anon->get('/photo/1'); T::eq($s, 302, 'photo sans connexion → redirigé');
[$s] = $anon->post('/connexion', ['email' => 'admin@cleanup.test', 'password' => 'Admin#2026']); T::eq($s, 419, 'POST sans jeton CSRF → 419');
[$s] = $anon->post('/connexion', ['_csrf' => 'faux', 'email' => 'a@b.c', 'password' => 'x']); T::eq($s, 419, 'POST avec faux jeton → 419');
[$s] = $anon->req('POST', '/reservation', ['a' => 1], [], true); T::eq($s, 419, 'POST JSON /reservation sans jeton → 419');
[$s] = $anon->post('/deconnexion'); T::eq($s, 419, 'déconnexion sans jeton → 419');
T::eq($anon->login('admin@cleanup.test', 'mauvais'), 302, 'mauvais mot de passe → retour au formulaire');
[$s] = $anon->get('/admin'); T::eq($s, 302, '... et toujours non connecté');
T::eq($anon->login("admin@cleanup.test' OR '1'='1", 'x'), 302, 'tentative d\'injection SQL dans l\'email : refusée sans erreur');

$admin = new Client($port);
[$s, $h] = (function () use ($admin) { $t = $admin->token(); return $admin->post('/connexion', ['_csrf' => $t, 'email' => 'admin@cleanup.test', 'password' => 'Admin#2026']); })();
T::ok($s === 302 && str_ends_with($h['location'][0], '/admin'), 'connexion admin → /admin');
[$s, , $b] = $admin->get('/admin'); T::ok($s === 200 && str_contains($b, 'Tableau de bord'), 'tableau de bord accessible');
foreach (['/admin/reservations', '/admin/planning', '/admin/planning?view=week', '/admin/parametres', '/admin/notifications', '/admin/audit', '/admin/crud/services', '/admin/crud/neighborhoods', '/admin/crud/teams', '/admin/crud/staff', '/admin/crud/users', '/admin/crud/notifications', '/admin/crud/time_off', '/admin/crud/pages'] as $p) {
    [$s] = $admin->get($p); T::eq($s, 200, "admin : GET $p");
}
[$s] = $admin->get('/admin/crud/inexistant'); T::eq($s, 404, 'ressource CRUD inconnue → 404');
$m = new Client($port); T::eq($m->login('gestion@cleanup.test', 'Gestion#2026'), 302, 'connexion gestionnaire');
[$s] = $m->get('/admin/reservations'); T::eq($s, 200, 'gestionnaire : réservations accessibles');
[$s] = $m->get('/admin/parametres'); T::eq($s, 403, 'gestionnaire : paramètres refusés (403)');
[$s] = $m->get('/admin/crud/users'); T::eq($s, 403, 'gestionnaire : utilisateurs refusés (403)');
[$s] = $m->get('/admin/audit'); T::eq($s, 403, "gestionnaire : journal d'audit refusé (403)");
// Limitation de débit de la connexion
$bf = new Client($port); $codes = [];
for ($i = 0; $i < 11; $i++) { $t = $bf->token(); [$s] = $bf->post('/connexion', ['_csrf' => $t, 'email' => 'x@y.zz', 'password' => 'nope' . $i]); $codes[] = $s; }
T::ok(in_array(429, $codes, true), 'force brute : blocage (429) après quelques tentatives [' . implode(',', $codes) . ']');
$stillBlocked = (new Client($port)); $t = $stillBlocked->token(); [$s] = $stillBlocked->post('/connexion', ['_csrf' => $t, 'email' => 'admin@cleanup.test', 'password' => 'Admin#2026']);
T::eq($s, 429, 'la même adresse IP reste bloquée même avec le bon mot de passe');
\App\Core\Database::reset(); \App\Core\Database::exec('DELETE FROM rate_limits');

// ------------------------------------------------------------------ Parcours de réservation client
T::group('Parcours de réservation complet (HTTP)');
$cl = new Client($port);
$csrf = $cl->token('/reserver');
T::ok(strlen($csrf) === 64, 'jeton CSRF fourni par la page de réservation');
[$s, , $b] = $cl->get('/api/services/' . svc('lavage-automobile') . '/form'); $def = json_decode($b, true);
T::ok($s === 200 && count($def['formulas']) === 3 && count($def['fields']) === 2 && count($def['options']) === 3, 'formulaire dynamique du service chargé (3 formules, 2 champs, 3 options)');
T::ok(!str_contains($b, 'price_delta') && !str_contains($b, 'multiplier'), 'coefficients de prix internes non exposés au client');
[$s, , $b] = $cl->get('/api/geo/regions/0'); $regions = json_decode($b, true); T::ok($s === 200 && count($regions) === 2, 'régions chargées');
[, , $b] = $cl->get('/api/geo/cities/' . $regions[0]['id']); $cities = json_decode($b, true);
[, , $b] = $cl->get('/api/geo/communes/' . $cities[0]['id']); $communes = json_decode($b, true);
$pa = array_values(array_filter($communes, fn($x) => $x['name'] === 'Parcelles Assainies'))[0];
[, , $b] = $cl->get('/api/geo/neighborhoods/' . $pa['id']); $hoods = json_decode($b, true);
T::ok(count($hoods) === 3 && $hoods[0]['name'] === 'Unité 15' && str_contains($hoods[0]['days'], 'lundi'), 'cascade région → ville → commune → quartiers (jours d\'intervention affichés)');
[$s] = $cl->get('/api/geo/hack/1'); T::eq($s, 404, 'type géographique inconnu → 404');

$sv = svc('lavage-automobile');
$pay = ['service_id' => $sv, 'formula_id' => formula($sv, 'Complet'), 'fields' => ['vehicle' => choice($sv, 'vehicle', 'SUV / 4x4'), 'count' => 2], 'options' => [opt($sv, 'Shampoing moteur')], 'neighborhood_id' => nb('Unité 15')];
[$s, , $b] = $cl->postJson('/api/quote', $pay, $csrf); $q = json_decode($b, true);
T::ok($s === 200 && $q['total'] === 23000 && $q['duration'] === 280, 'devis serveur : 23 000 FCFA, 280 min');
[$s, , $b] = $cl->postJson('/api/quote', array_merge($pay, ['total' => 1, 'price' => 1, 'subtotal' => 1]), $csrf); T::eq(json_decode($b, true)['total'], 23000, 'prix envoyé par le client ignoré');
[$s, , $b] = $cl->postJson('/api/quote', array_merge($pay, ['formula_id' => 9999]), $csrf); T::ok($s === 422 && isset(json_decode($b, true)['errors']['formula_id']), 'devis invalide → 422 avec message');

$pay['fields']['count'] = 1; $pay['options'] = []; $pay['formula_id'] = formula($sv, 'Simple'); $pay['fields']['vehicle'] = choice($sv, 'vehicle', 'Citadine');
[$s, , $b] = $cl->postJson('/api/availability/days', $pay, $csrf); $days = json_decode($b, true)['days'];
$open = array_keys(array_filter($days, fn($d) => $d['count'] > 0));
T::ok($s === 200 && count($open) >= 4, 'calendrier : ' . count($open) . ' jours avec créneaux');
T::ok(!array_filter($open, fn($d) => !in_array((int) date('N', strtotime($d)), [1, 3, 5], true)), 'seuls lundi/mercredi/vendredi proposés pour Unité 15');
$day = $open[1];
[$s, , $b] = $cl->postJson('/api/availability/slots', $pay + ['date' => $day], $csrf); $slots = json_decode($b, true)['slots'];
T::ok($s === 200 && count($slots) > 3 && !isset($slots[0]['team_id']), 'créneaux du jour reçus (sans exposer les équipes)');
$start = $slots[0]['start'];

$book = $pay + ['date' => $day, 'start' => $start, 'first_name' => 'Mariama', 'last_name' => 'Thiam', 'phone' => '77 456 78 90', 'email' => 'mariama@example.com', 'address_text' => 'Villa 24, rue 8, Unité 15', 'instructions' => 'Portail bleu', 'website' => ''];
[$s, , $b] = $cl->postJson('/reservation', array_merge($book, ['website' => 'http://spam.example']), $csrf); T::eq($s, 400, 'piège à robots (champ caché rempli) → refusé');
[$s, , $b] = $cl->postJson('/reservation', $book, $csrf); $r = json_decode($b, true);
T::ok($s === 201 && str_starts_with($r['reference'], 'CU-'), 'réservation créée : ' . ($r['reference'] ?? $b));
$ref = $r['reference'];
$cl2 = new Client($port); $csrf2 = $cl2->token('/reserver');
[$s, , $b] = $cl2->postJson('/reservation', array_merge($book, ['phone' => '77 111 22 33', 'first_name' => 'Autre']), $csrf2);
T::ok($s === 409 && json_decode($b, true)['code'] === 'slot_taken', 'même créneau pris par un autre client → 409 « créneau pris »');
[$s, , $b] = $cl->get($r['redirect']); T::ok($s === 200 && str_contains($b, $ref) && str_contains($b, '4 000'), 'page de confirmation avec référence et total (3000 + 1000 de déplacement)');
[$s, $h] = $cl2->get($r['redirect']); T::eq($s, 302, 'la confirmation n\'est visible que par le client concerné');
// Calculs côté serveur : le prix en base est celui du serveur
\App\Core\Database::reset();
$row = \App\Core\Database::one('SELECT * FROM bookings WHERE reference = ?', [$ref]);
T::ok($row && (int) $row['total'] === 4000 && $row['status'] === 'pending' && $row['team_id'] !== null, 'base : total 4 000, statut en attente, équipe réservée');
// Suivi
$tr = new Client($port); $tt = $tr->token('/suivi');
[$s] = $tr->get("/suivi/$ref"); T::eq($s, 302, 'suivi direct sans vérification → redirigé');
[$s, $h] = $tr->post('/suivi', ['_csrf' => $tt, 'reference' => $ref, 'phone' => '77 000 00 00']); T::ok($s === 302 && str_ends_with($h['location'][0], '/suivi'), 'mauvais téléphone → refusé');
[$s] = $tr->get("/suivi/$ref"); T::eq($s, 302, '... toujours pas d\'accès');
[$s, $h] = $tr->post('/suivi', ['_csrf' => $tt, 'reference' => strtolower($ref), 'phone' => '+221 77 456 78 90']); T::ok($s === 302 && str_ends_with($h['location'][0], "/suivi/$ref"), 'bonne référence + téléphone (autre format) → accès');
[$s, , $b] = $tr->get("/suivi/$ref"); T::ok($s === 200 && str_contains($b, 'Annuler ma réservation'), 'page de suivi avec bouton d\'annulation');
[$s, , $b] = $tr->get('/suivi/CU-000000-ZZZZ'); T::eq($s, 302, 'référence d\'un tiers : pas d\'accès');
[$s] = $tr->post("/suivi/$ref/annuler", ['_csrf' => $tt]); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE reference = ?', [$ref]), 'cancelled', 'annulation par le client enregistrée');
// Contact
$ct = new Client($port); $t = $ct->token('/contact');
[$s, $h] = $ct->post('/contact', ['_csrf' => $t, 'name' => 'Ali <script>alert(1)</script>', 'phone' => '771112233', 'message' => 'Bonjour, pouvez-vous intervenir à Thiès ?']);
T::ok($s === 302, 'message de contact envoyé'); \App\Core\Database::reset();
T::eq((int) \App\Core\Database::val('SELECT COUNT(*) FROM contact_messages'), 1, 'message enregistré');
[$s, , $b] = $admin->get('/admin/crud/contact_messages'); T::ok(str_contains($b, '&lt;script&gt;') && !str_contains($b, '<script>alert'), 'XSS : le nom malveillant est échappé dans l\'administration');

// ------------------------------------------------------------------ Administration : réservation, affectation, CRUD
T::group('Administration : réservation et CRUD (HTTP)');
\App\Core\Database::reset();
$b2 = (new \App\Services\BookingService())->create(wash_input(next_dow(3), '09:00', 'Unité 15', ['phone' => '77 700 00 01', 'email' => '']));
$bid = (int) $b2['id'];
[$s, , $b] = $admin->get("/admin/reservations/$bid"); T::ok($s === 200 && str_contains($b, $b2['reference']), 'fiche réservation');
$at = $admin->token("/admin/reservations/$bid");
[$s] = $admin->post("/admin/reservations/$bid/statut", ['_csrf' => $at, 'to' => 'confirmed']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'confirmed', 'confirmation depuis l\'admin');
[$s] = $admin->post("/admin/reservations/$bid/statut", ['_csrf' => $at, 'to' => 'assigned']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'confirmed', 'statut inconnu refusé côté serveur');
[$s, , $b] = $admin->req('POST', '/admin/api/creneaux', ['booking_id' => $bid, 'date' => next_dow(5)], ['X-CSRF-Token: ' . $at], true); T::ok($s === 200 && count(json_decode($b, true)['slots']) > 3, 'API des créneaux pour déplacement');
$moussaStaff = (int) \App\Core\Database::val("SELECT id FROM staff WHERE name = 'Moussa Diop'");
[$s] = $admin->post("/admin/reservations/$bid/affecter", ['_csrf' => $at, 'staff_id' => $moussaStaff]); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'confirmed', 'affectation à Moussa depuis l\'admin (commande confirmée)');
[$s] = $admin->post("/admin/reservations/$bid/montant", ['_csrf' => $at, 'declared_amount' => '4000', 'payment_note' => 'Espèces']); \App\Core\Database::reset();
T::eq((int) \App\Core\Database::val('SELECT declared_amount FROM bookings WHERE id = ?', [$bid]), 4000, 'montant encaissé saisi manuellement');
[$s, , $b] = $admin->get('/admin/planning?date=' . next_dow(3)); T::ok($s === 200 && str_contains($b, 'Équipe Auto') && str_contains($b, 'libre'), 'planning du jour avec charge des équipes');
[$s, , $b] = $admin->get('/admin/planning?view=week&group=neighborhood&date=' . next_dow(3)); T::ok($s === 200 && str_contains($b, 'week-grid'), 'planning hebdomadaire groupé par quartier');
// CRUD : création, validation, modification, suppression logique
$t = $admin->token('/admin/crud/services/nouveau');
[$s, $h] = $admin->post('/admin/crud/services', ['_csrf' => $t, 'name' => '', 'base_price' => 'abc']); T::ok($s === 302 && str_contains($h['location'][0], 'nouveau'), 'CRUD : formulaire invalide renvoyé sur le formulaire');
[, , $b] = $admin->get('/admin/crud/services/nouveau'); T::ok(str_contains($b, 'est obligatoire'), 'CRUD : messages d\'erreur affichés');
$t = $admin->token('/admin/crud/services/nouveau');
[$s] = $admin->post('/admin/crud/services', ['_csrf' => $t, 'name' => 'Nettoyage de vitres', 'base_price' => '15000', 'base_duration' => '60', 'billing_unit' => 'fixed', 'availability_status' => 'available', 'active' => '1', 'description' => 'Vitres et baies vitrées', 'sort' => '9']);
\App\Core\Database::reset();
$new = \App\Core\Database::one("SELECT * FROM services WHERE name = 'Nettoyage de vitres'");
T::ok($new && $new['slug'] === 'nettoyage-de-vitres' && (int) $new['base_price'] === 15000, 'CRUD : service créé avec slug automatique');
$t = $admin->token('/admin/crud/services');
$admin->post('/admin/crud/services', ['_csrf' => $t, 'name' => 'Nettoyage de vitres', 'base_price' => '1', 'base_duration' => '30', 'billing_unit' => 'fixed', 'availability_status' => 'available']); \App\Core\Database::reset();
T::eq((int) \App\Core\Database::val("SELECT COUNT(DISTINCT slug) FROM services WHERE name = 'Nettoyage de vitres'"), 2, 'CRUD : slug en doublon rendu unique');
$admin->post("/admin/crud/services/{$new['id']}", ['_csrf' => $t, 'name' => 'Vitres premium', 'base_price' => '18000', 'base_duration' => '60', 'billing_unit' => 'fixed', 'availability_status' => 'available', 'active' => '1']); \App\Core\Database::reset();
T::eq((int) \App\Core\Database::val('SELECT base_price FROM services WHERE id = ?', [$new['id']]), 18000, 'CRUD : modification enregistrée');
[, , $b] = $cl->get('/services'); T::ok(str_contains($b, 'Vitres premium'), 'le nouveau service apparaît sur le site public immédiatement');
$admin->post("/admin/crud/services/{$new['id']}/supprimer", ['_csrf' => $t]); \App\Core\Database::reset();
T::ok(\App\Core\Database::val('SELECT deleted_at FROM services WHERE id = ?', [$new['id']]) !== null, 'CRUD : suppression logique (la ligne est conservée)');
[$s] = $cl->get('/services/' . $new['slug']); T::eq($s, 404, '... et le service n\'est plus visible publiquement');
// Équipe : pivots
$team = (int) \App\Core\Database::val("SELECT id FROM teams WHERE name = 'Équipe Textile'");
$t = $admin->token("/admin/crud/teams/$team/modifier");
$admin->post("/admin/crud/teams/$team", ['_csrf' => $t, 'name' => 'Équipe Textile', 'daily_capacity' => '4', 'status' => 'active', 'services' => [svc('nettoyage-matelas')]]); \App\Core\Database::reset();
T::eq(array_column(\App\Core\Database::all('SELECT service_id FROM team_services WHERE team_id = ?', [$team]), 'service_id'), [svc('nettoyage-matelas')], 'CRUD : services de l\'équipe (table pivot) mis à jour');
// Paramètres
$t = $admin->token('/admin/parametres');
$admin->post('/admin/parametres', ['_csrf' => $t, 'company_phone' => '+221 33 999 99 99', 'slot_step_minutes' => '30', 'min_lead_hours' => '1', 'max_days_ahead' => '30', 'buffer_same_zone' => '10', 'cancel_hours_before' => '12', 'reminder_hours_before' => '24', 'notif_max_attempts' => '4', 'notif_retry_minutes' => '10', 'currency' => 'FCFA', 'company_name' => "Clean Up"]);
[, , $b] = $cl->get('/contact'); T::ok(str_contains($b, '+221 33 999 99 99'), 'paramètres : le téléphone modifié apparaît sur le site');
$t = $admin->token('/admin/parametres');
[$s, $h] = $admin->post('/admin/parametres', ['_csrf' => $t, 'slot_step_minutes' => 'abc']); [, , $b] = $admin->get('/admin/parametres'); T::ok(str_contains($b, 'doit être un nombre entier'), 'paramètres : valeur invalide refusée');
// Upload refusé / accepté dans les paramètres
[$s] = $admin->multipart('/admin/parametres', ['_csrf' => $admin->token('/admin/parametres'), 'slot_step_minutes' => '30', 'min_lead_hours' => '1', 'max_days_ahead' => '30', 'buffer_same_zone' => '10', 'cancel_hours_before' => '12', 'reminder_hours_before' => '24', 'notif_max_attempts' => '4', 'notif_retry_minutes' => '10'], 'hero_image', 'shell.php', '<?php system($_GET["c"]); ?>', 'image/jpeg');
[, , $b] = $admin->get('/admin/parametres'); T::ok(str_contains($b, 'Format refusé') || str_contains($b, 'alert-danger'), 'upload : script PHP déguisé en image refusé');
T::ok(!glob(BASE_PATH . '/public/uploads/site/*.php'), 'upload : aucun fichier PHP écrit sur le disque');

// ------------------------------------------------------------------ Agent
T::group('Interface agent : droits et mission');
$ag = new Client($port); T::eq($ag->login('moussa@cleanup.test', 'Agent#2026'), 302, 'connexion agent Moussa');
[$s, $h] = $ag->get('/admin'); T::eq($s, 403, 'agent : /admin interdit (403)');
[$s] = $ag->get('/admin/crud/services'); T::eq($s, 403, 'agent : CRUD interdit (403)');
[$s] = $ag->get('/admin/reservations'); T::eq($s, 403, 'agent : réservations (admin) interdites');
[$s, , $b] = $ag->get('/agent'); T::ok($s === 200 && str_contains($b, 'Bonjour Moussa'), 'tableau de bord agent');
[$s, , $b] = $ag->get("/agent/mission/$bid"); T::ok($s === 200 && str_contains($b, 'tel:') && str_contains($b, 'google.com/maps') && str_contains($b, 'Je commence'), 'mission affectée : téléphone cliquable, itinéraire, bouton d\'étape');
$otherAgent = new Client($port); $otherAgent->login('awa@cleanup.test', 'Agent#2026');
[$s] = $otherAgent->get("/agent/mission/$bid"); T::eq($s, 404, 'autre agent : mission d\'un collègue introuvable (404)');
[$s] = $otherAgent->post("/agent/mission/$bid/statut", ['_csrf' => $otherAgent->token('/agent'), 'to' => 'in_progress']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'confirmed', 'autre agent : changement de statut refusé');
$at = $ag->token("/agent/mission/$bid");
[$s] = $ag->post("/agent/mission/$bid/statut", ['_csrf' => $at, 'to' => 'completed']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'confirmed', 'agent : impossible de sauter des étapes');
$ag->post("/agent/mission/$bid/statut", ['_csrf' => $at, 'to' => 'in_progress']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'in_progress', 'agent : intervention en cours');
$at = $ag->token("/agent/mission/$bid"); $ag->post("/agent/mission/$bid/statut", ['_csrf' => $at, 'to' => 'in_progress']);
$ag->post("/agent/mission/$bid/note", ['_csrf' => $ag->token("/agent/mission/$bid"), 'note' => 'Rayure existante sur la portière gauche']); \App\Core\Database::reset();
T::eq((int) \App\Core\Database::val('SELECT COUNT(*) FROM booking_notes WHERE booking_id = ? AND note LIKE ?', [$bid, '%Rayure%']), 1, 'agent : note ajoutée');
[$s] = $ag->multipart("/agent/mission/$bid/photo", ['_csrf' => $ag->token("/agent/mission/$bid"), 'kind' => 'before'], 'photo', 'avant.png', $PNG, 'image/png'); \App\Core\Database::reset();
$ph = \App\Core\Database::one('SELECT * FROM booking_photos WHERE booking_id = ?', [$bid]);
T::ok($ph && is_file(BASE_PATH . '/storage/uploads/missions/' . $ph['path']), 'agent : photo enregistrée hors du dossier public');
[$s] = $ag->multipart("/agent/mission/$bid/photo", ['_csrf' => $ag->token("/agent/mission/$bid"), 'kind' => 'after'], 'photo', 'x.jpg', 'ceci n\'est pas une image', 'image/jpeg'); \App\Core\Database::reset();
T::eq((int) \App\Core\Database::val('SELECT COUNT(*) FROM booking_photos WHERE booking_id = ?', [$bid]), 1, 'agent : fichier non image refusé');
[$s, $h, $b] = $ag->get("/photo/{$ph['id']}"); T::ok($s === 200 && ($h['content-type'][0] ?? '') === 'image/png', 'agent : voit sa photo');
[$s] = $otherAgent->get("/photo/{$ph['id']}"); T::eq($s, 403, 'autre agent : photo refusée (403)');
[$s] = $admin->get("/photo/{$ph['id']}"); T::eq($s, 200, 'admin : voit la photo');
[$s] = $cl->get('/storage/uploads/missions/' . $ph['path']); T::eq($s, 404, 'photo non accessible par URL directe');
$ag->post("/agent/mission/$bid/statut", ['_csrf' => $ag->token("/agent/mission/$bid"), 'to' => 'completed']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'completed', 'agent : mission terminée');
[$s, , $b] = $ag->get('/agent'); T::ok($s === 200, 'agent : tableau de bord après mission');

// ------------------------------------------------------------------ CRM et gestion
T::group('CRM : droits, pages, prospects, fiche client, gestion');
$pub = new Client($port);
[$s] = $pub->post('/contact', ['_csrf' => $pub->token('/contact'), 'name' => 'Khady Seck', 'phone' => '77 888 77 66', 'message' => 'Je voudrais un devis pour un canapé']); \App\Core\Database::reset();
$lead = \App\Core\Database::one("SELECT * FROM leads WHERE phone = '221778887766'");
T::ok($s === 302 && $lead && $lead['status'] === 'new', 'formulaire de contact : un prospect est créé');
T::eq((int) \App\Core\Database::val("SELECT id FROM acquisition_channels WHERE code = 'contact'"), (int) $lead['channel_id'], 'prospect rattaché au canal « formulaire de contact »');
[$s] = (new Client($port))->get('/admin/crm'); T::eq($s, 302, 'CRM : visiteur anonyme redirigé vers la connexion');
foreach (['/admin/crm', '/admin/crm/prospects/' . $lead['id'], '/admin/gestion', '/admin/crud/acquisition_channels', '/admin/crud/leads', '/admin/crud/expenses'] as $u) { [$s] = $admin->get($u); T::eq($s, 200, "admin : $u"); }
[$s] = $m->get('/admin/crm'); T::eq($s, 200, 'gestionnaire : CRM autorisé');
[$s] = $m->get('/admin/gestion'); T::eq($s, 403, 'gestionnaire : gestion financière refusée');
[$s] = $m->get('/admin/crud/expenses'); T::eq($s, 403, 'gestionnaire : dépenses refusées');
[$s] = $ag->get('/admin/crm'); T::ok($s === 403 || $s === 302, 'agent : CRM refusé (' . $s . ')');
[$s] = $admin->post("/admin/crm/prospects/{$lead['id']}/statut", ['status' => 'contacted']); T::eq($s, 419, 'changement de statut sans jeton CSRF refusé');
$t = $admin->token('/admin/crm/prospects/' . $lead['id']);
$admin->post("/admin/crm/prospects/{$lead['id']}/statut", ['_csrf' => $t, 'status' => 'quoted']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM leads WHERE id = ?', [$lead['id']]), 'quoted', 'statut du prospect modifié');
$admin->post("/admin/crm/prospects/{$lead['id']}/echange", ['_csrf' => $t, 'type' => 'call', 'summary' => 'Appelée, rappel jeudi']); \App\Core\Database::reset();
T::eq((int) \App\Core\Database::val('SELECT COUNT(*) FROM interactions WHERE lead_id = ? AND type = ?', [$lead['id'], 'call']), 1, 'échange enregistré');
[$s, $h] = $admin->post("/admin/crm/prospects/{$lead['id']}/convertir", ['_csrf' => $t]); \App\Core\Database::reset();
$cust = \App\Core\Database::one("SELECT * FROM customers WHERE phone = '221778887766'");
T::ok($s === 302 && $cust, 'prospect converti en client');
[$s, , $b] = $admin->get('/admin/clients/' . $cust['id']); T::ok($s === 200 && str_contains($b, 'Seck') && str_contains($b, 'Appelée, rappel jeudi'), 'fiche client : coordonnées et historique affichés');
[$s] = $admin->get('/admin/clients/999999'); T::eq($s, 404, 'fiche client inconnue : 404');
[$s, , $b] = $admin->get('/admin/gestion?from=2020-01-01&to=2099-12-31'); T::ok($s === 200 && str_contains($b, 'Acquisition par canal'), 'gestion : rapport sur période personnalisée');
[$s, , $b] = $admin->get('/admin/gestion?from=%3Cscript%3E&to=x'); T::ok($s === 200 && !str_contains($b, '<script>alert'), 'gestion : dates invalides sans effet');
[$s, , $b] = $admin->get('/admin'); T::ok($s === 200 && str_contains($b, 'prospects à relancer'), 'tableau de bord : indicateurs prospects');

// ------------------------------------------------------------------ Fiche service, simulateur, parcours client
T::group('Fiche service admin, simulateur, parcours client enrichi');
$A = (int) \App\Core\Database::val("SELECT id FROM services WHERE slug = 'lavage-automobile'");
[$s, , $b] = $admin->get("/admin/services/$A"); T::ok($s === 200 && str_contains($b, 'Simulateur de prix') && str_contains($b, 'Prêt à être réservé') && str_contains($b, 'La plus choisie'), 'fiche service : formules, contrôle de publication, simulateur');
[$s] = $ag->get("/admin/services/$A"); T::ok($s === 403 || $s === 302, 'agent : fiche service refusée');
[$s] = $admin->get('/admin/services/999999'); T::eq($s, 404, 'fiche service inconnue : 404');
$vh = (int) \App\Core\Database::val("SELECT id FROM service_field_choices WHERE label = 'SUV / 4x4'"); $fid = (int) \App\Core\Database::val("SELECT f.id FROM service_fields f WHERE f.service_id = $A AND f.field_key = 'vehicle'");
$cplx = (int) \App\Core\Database::val("SELECT id FROM service_prices WHERE service_id = $A AND name = 'Complet'");
$tk = $admin->token("/admin/services/$A");
[$s, , $b] = $admin->postJson("/admin/services/$A/simuler", ['formula_id' => $cplx, 'fields' => ['vehicle' => $vh, 'count' => 2], 'options' => []], $tk); $q = json_decode($b, true);
T::ok($s === 200 && $q['subtotal'] === 16000 && $q['total'] === 16000, 'simulateur : Complet SUV ×2 = 16 000 (même calcul que le site)');
[$s, , $b] = $admin->postJson("/admin/services/$A/simuler", ['formula_id' => 999999, 'fields' => []], $tk); T::eq($s, 422, 'simulateur : formule invalide refusée');
[$s] = $admin->req('POST', "/admin/services/$A/simuler", [], [], true); T::eq($s, 419, 'simulateur sans jeton CSRF refusé');
[$s, $h] = $admin->post("/admin/services/$A/dupliquer", ['_csrf' => $tk]); T::ok($s === 302 && str_contains($h['location'][0] ?? '', '/admin/services/'), 'duplication : redirige vers la fiche de la copie');
// Création d'une formule depuis la fiche : retour sur la fiche
$tk2 = $admin->token("/admin/crud/service_prices/nouveau?service_id=$A");
[$s, $h] = $admin->post('/admin/crud/service_prices', ['_csrf' => $tk2, 'service_id' => $A, 'name' => 'Express', 'amount' => '2500', 'duration_minutes' => '20', 'includes' => "Lavage rapide\nSéchage", 'popular' => '0', 'active' => '1', 'sort' => '9']); \App\Core\Database::reset();
T::ok($s === 302 && str_contains($h['location'][0] ?? '', "/admin/services/$A"), 'formule créée depuis la fiche : retour sur la fiche');
T::eq((int) \App\Core\Database::val("SELECT amount FROM service_prices WHERE name = 'Express' AND service_id = $A"), 2500, 'nouvelle formule enregistrée avec son prix');
[$s, , $b] = $admin->get("/admin/crud/service_images/nouveau?service_id=$A"); T::eq($s, 200, 'formulaire de photo');
[$s, $h] = $admin->multipart('/admin/crud/service_images', ['_csrf' => $admin->token("/admin/crud/service_images/nouveau?service_id=$A"), 'service_id' => $A, 'sort' => '1'], 'zz', 'x.txt', 'x', 'text/plain'); \App\Core\Database::reset();
T::eq((int) \App\Core\Database::val('SELECT COUNT(*) FROM service_images'), 0, 'photo obligatoire : rien créé sans fichier');
[$s, $h] = $admin->multipart('/admin/crud/service_images', ['_csrf' => $admin->token("/admin/crud/service_images/nouveau?service_id=$A"), 'service_id' => $A, 'sort' => '1'], 'path', 'g.png', $PNG, 'image/png'); \App\Core\Database::reset();
T::eq((int) \App\Core\Database::val('SELECT COUNT(*) FROM service_images WHERE service_id = ?', [$A]), 1, 'photo ajoutée à la galerie');
$pub2 = new Client($port);
[$s, , $b] = $pub2->get('/services/lavage-automobile'); T::ok($s === 200 && str_contains($b, 'La plus choisie') && str_contains($b, 'Aspiration intérieure') && str_contains($b, "formula=$cplx") && str_contains($b, 'Express'), 'page service : formules, contenu inclus, badge, bouton « choisir »');
[$s, , $b] = $pub2->get("/reserver?service=lavage-automobile&formula=$cplx"); T::ok($s === 200 && str_contains($b, "data-preformula=\"$cplx\"") && str_contains($b, 'id="mobile-total"'), 'réservation : formule présélectionnée, total mobile');
[$s, , $b] = $pub2->get("/api/services/$A/form"); $d = json_decode($b, true); $cx = array_values(array_filter($d['formulas'], fn($f) => $f['name'] === 'Complet'))[0] ?? null;
T::ok($s === 200 && $cx && $cx['popular'] === true && count($cx['includes']) === 4, 'API formulaire : formule populaire et contenu inclus');
// calendrier .ics et suivi
$ref = \App\Core\Database::val("SELECT reference FROM bookings WHERE status <> 'cancelled' ORDER BY id DESC LIMIT 1"); $phone = \App\Core\Database::val('SELECT c.phone FROM bookings b JOIN customers c ON c.id = b.customer_id WHERE b.reference = ?', [$ref]);
[$s] = $pub2->get("/suivi/$ref/calendrier"); T::eq($s, 302, 'agenda : refusé sans avoir prouvé sa réservation');
$pub2->post('/suivi', ['_csrf' => $pub2->token('/suivi'), 'reference' => $ref, 'phone' => $phone]);
[$s, $h, $b] = $pub2->get("/suivi/$ref/calendrier"); T::ok($s === 200 && str_contains($h['content-type'][0] ?? '', 'text/calendar') && str_contains($b, 'BEGIN:VEVENT') && str_contains($b, "UID:$ref@") && preg_match('/DTSTART:\d{8}T\d{6}Z/', $b) === 1, 'agenda : fichier .ics valide pour la réservation');
[$s, , $b] = $pub2->get("/suivi/$ref"); T::ok($s === 200 && str_contains($b, 'Avancement de la réservation') && str_contains($b, 'aria-current="step"'), 'suivi : progression visuelle de la réservation');

// Permissions relues à chaque requête
\App\Core\Database::reset();
$mgr = (int) \App\Core\Database::val("SELECT id FROM roles WHERE name = 'manager'"); $pcrm = (int) \App\Core\Database::val("SELECT id FROM permissions WHERE name = 'crm.manage'");
\App\Core\Database::exec('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?', [$mgr, $pcrm]);
[$s] = $m->get('/admin/crm'); T::eq($s, 403, 'permission retirée au rôle : effective tout de suite, sans reconnexion');
\App\Core\Database::insert('role_permissions', ['role_id' => $mgr, 'permission_id' => $pcrm]);
[$s] = $m->get('/admin/crm'); T::eq($s, 200, 'permission rendue : effective tout de suite');

// ------------------------------------------------------------------ Marketing
T::group('Marketing : droits, canaux, campagnes, balises publicitaires');
[$s] = (new Client($port))->get('/admin/marketing'); T::eq($s, 302, 'marketing : visiteur anonyme redirigé');
foreach (['/admin/marketing', '/admin/marketing/canaux', '/admin/marketing/campagnes', '/admin/marketing/campagnes/nouveau', '/admin/marketing/liens'] as $u) { [$s] = $admin->get($u); T::eq($s, 200, "admin : $u"); }
[$s] = $m->get('/admin/marketing'); T::eq($s, 200, 'gestionnaire : marketing autorisé');
[$s] = $m->get('/admin/marketing/campagnes/nouveau'); T::eq($s, 200, 'gestionnaire : création de campagne autorisée');
[$s] = $m->get('/admin/marketing/canaux'); T::eq($s, 403, 'gestionnaire : réglage des canaux (clés, mots de passe) refusé');
[$s] = $m->post('/admin/marketing/canaux/meta', ['_csrf' => $m->token('/admin/marketing'), 'meta_enabled' => '1']); T::eq($s, 403, 'gestionnaire : enregistrement des canaux refusé');
[$s] = $ag->get('/admin/marketing'); T::ok($s === 403 || $s === 302, 'agent : marketing refusé (' . $s . ')');
[$s] = $admin->post('/admin/marketing/canaux/meta', ['meta_enabled' => '1']); T::eq($s, 419, 'enregistrement sans jeton CSRF refusé');

$t = $admin->token('/admin/marketing/canaux');
[$s] = $admin->post('/admin/marketing/canaux/meta', ['_csrf' => $t, 'meta_enabled' => '1', 'meta_pixel_id' => 'pas-un-id', 'meta_capi_token' => '', 'meta_test_code' => '']);
\App\Core\Database::reset(); $GLOBALS['__settings_dirty'] = true;
T::ok($s === 302 && setting('mk_meta_pixel_id', '') === '', 'pixel invalide refusé, rien d\'enregistré');
[$s] = $admin->post('/admin/marketing/canaux/meta', ['_csrf' => $t, 'meta_enabled' => '1', 'meta_pixel_id' => '1234567890', 'meta_capi_token' => 'SECRET-TOKEN-XYZ', 'meta_test_code' => '']);
\App\Core\Database::reset(); $GLOBALS['__settings_dirty'] = true;
T::ok(setting('mk_meta_pixel_id') === '1234567890' && setting('mk_meta_capi_token') === 'SECRET-TOKEN-XYZ', 'pixel et jeton enregistrés');
[, , $page] = $admin->get('/admin/marketing/canaux');
T::ok(!str_contains($page, 'SECRET-TOKEN-XYZ') && str_contains($page, 'enregistré'), 'le jeton enregistré n\'est jamais réaffiché');
$admin->post('/admin/marketing/canaux/meta', ['_csrf' => $t, 'meta_enabled' => '1', 'meta_pixel_id' => '1234567890', 'meta_capi_token' => '', 'meta_test_code' => '']);
\App\Core\Database::reset(); $GLOBALS['__settings_dirty'] = true;
T::eq(setting('mk_meta_capi_token'), 'SECRET-TOKEN-XYZ', 'champ secret laissé vide : valeur conservée');
[$s] = $admin->post('/admin/marketing/canaux/sms', ['_csrf' => $t, 'sms_driver' => 'http', 'sms_api_url' => 'javascript:alert(1)', 'sms_token' => '', 'sms_sender' => '', 'sms_body_template' => '']);
\App\Core\Database::reset(); $GLOBALS['__settings_dirty'] = true;
T::ok($s === 302 && (setting('mk_sms_api_url', '') === ''), 'adresse d\'API SMS non https refusée');
[$s] = $admin->post('/admin/marketing/canaux/inconnu', ['_csrf' => $t]); T::eq($s, 404, 'canal inconnu : 404');

$pubm = new Client($port);
[$s, $hd, $body] = $pubm->get('/');
T::ok($s === 200 && str_contains($body, 'name="mk-config"') && str_contains($body, 'marketing.js'), 'balises : le site charge le script de suivi quand le pixel est actif');
T::ok(str_contains(implode(' ', $hd['content-security-policy'] ?? []), 'https://connect.facebook.net'), 'politique de sécurité : domaine Meta autorisé seulement maintenant');
T::ok(!str_contains($body, '1234567890\'') && !str_contains($body, 'fbq('), 'aucun script publicitaire en ligne dans la page (chargé après accord)');
[$s] = $pubm->get('/assets/js/marketing.js'); T::eq($s, 200, 'script de consentement servi');
$admin->post('/admin/marketing/canaux/meta', ['_csrf' => $t, 'meta_enabled' => '0', 'meta_pixel_id' => '1234567890', 'meta_capi_token' => '', 'meta_test_code' => '']);
[, $hd, $body] = (new Client($port))->get('/');
T::ok(!str_contains($body, 'mk-config') && !str_contains(implode(' ', $hd['content-security-policy'] ?? []), 'facebook'), 'suivi désactivé : aucune balise, aucun domaine externe autorisé');

$t = $admin->token('/admin/marketing/campagnes/nouveau');
[$s, $hd] = $admin->post('/admin/marketing/campagnes', ['_csrf' => $t, 'name' => 'Promo test', 'channel' => 'sms', 'audience' => 'consent', 'body' => 'Bonjour {client} {lien}']);
$loc = $hd['location'][0] ?? '';
T::ok($s === 302 && preg_match('#/admin/marketing/campagnes/(\d+)$#', $loc, $mm), 'campagne créée en brouillon');
[$s, , $b] = $admin->get($mm[0] ?? '/admin/marketing/campagnes'); T::ok($s === 200 && str_contains($b, 'Promo test') && str_contains($b, 'Avant de lancer'), 'fiche de la campagne avec aperçu avant lancement');
[$s] = $admin->post('/admin/marketing/campagnes', ['_csrf' => $t, 'name' => '', 'channel' => 'sms', 'audience' => 'consent', 'body' => 'x']); T::eq($s, 302, 'campagne invalide : retour au formulaire');
[$s] = $admin->get('/admin/marketing/campagnes/999999'); T::eq($s, 404, 'campagne inconnue : 404');
[$s, , $b] = $admin->get('/admin/marketing/liens?preset=facebook&campaign=Promo+Novembre');
T::ok($s === 200 && str_contains($b, 'utm_source=facebook') && str_contains($b, 'utm_medium=paid_social') && str_contains($b, 'utm_campaign=promo-novembre'), 'lien suivi Facebook généré');
[$s, , $b] = (new Client($port))->get('/cron/run?token=jeton-de-test-123'); T::ok($s === 200 && array_key_exists('campaign_messages', json_decode($b, true) ?? []), 'tâche planifiée : traite aussi les campagnes');

// ------------------------------------------------------------------ Désinscription STOP (webhooks)
T::group('Webhooks : désinscription STOP par WhatsApp et SMS');
$wh = new Client($port);
$cons = \App\Services\CustomerService::class; $cid1 = (new $cons())->findOrCreate('Test', 'Stop', '77 500 00 01', null); $cid2 = (new $cons())->findOrCreate('Test', 'Sms', '77 500 00 02', null);
\App\Core\Database::exec('UPDATE customers SET marketing_consent = 1 WHERE id IN (?, ?)', [$cid1, $cid2]);
$st = fn(int $id) => (int) \App\Core\Database::val('SELECT marketing_consent FROM customers WHERE id = ?', [$id]);
[$s] = $wh->req('POST', '/webhooks/sms', json_encode(['from' => '77 500 00 02', 'text' => 'STOP']), ['Content-Type: application/json']);
T::eq($s, 403, 'SMS entrant sans jeton configuré ni fourni : refusé (et pas de 419 : pas de CSRF ici)');
setting_set('mk_sms_inbound_token', 'jeton-sms-123'); setting_set('mk_wa_verify_token', 'verif-123'); setting_set('mk_wa_app_secret', 'secret-app');
[$s] = $wh->req('POST', '/webhooks/sms', json_encode(['from' => '77 500 00 02', 'text' => 'STOP']), ['Content-Type: application/json', 'X-Webhook-Token: mauvais']);
T::eq($s, 403, 'SMS entrant avec un mauvais jeton : refusé');
T::eq($st($cid2), 1, 'client toujours inscrit après les refus');
[$s] = $wh->req('POST', '/webhooks/sms', json_encode(['from' => '77 500 00 02', 'text' => 'Merci beaucoup']), ['Content-Type: application/json', 'X-Webhook-Token: jeton-sms-123']);
\App\Core\Database::reset(); T::ok($s === 200 && $st($cid2) === 1, 'SMS entrant ordinaire : aucune désinscription');
[$s, , $b] = $wh->req('POST', '/webhooks/sms', json_encode(['msisdn' => '221775000002', 'message' => ' stop ']), ['Content-Type: application/json', 'X-Webhook-Token: jeton-sms-123']);
\App\Core\Database::reset(); T::ok($s === 200 && (json_decode($b, true)['optouts'] ?? 0) === 1 && $st($cid2) === 0, 'SMS « STOP » avec le bon jeton : client désinscrit');
[$s] = $wh->req('POST', '/webhooks/sms?token=jeton-sms-123', http_build_query(['from' => '77 500 00 02', 'text' => 'STOP']), ['Content-Type: application/x-www-form-urlencoded']); T::eq($s, 200, 'SMS entrant : jeton aussi accepté dans l\'adresse, corps en formulaire');

[$s, , $b] = $wh->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verif-123&hub_challenge=987654'); T::ok($s === 200 && trim($b) === '987654', 'WhatsApp : vérification de l\'adresse par Meta');
[$s] = $wh->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=faux&hub_challenge=987654'); T::eq($s, 403, 'WhatsApp : mauvais jeton de vérification refusé');
$payload = json_encode(['entry' => [['changes' => [['value' => ['messages' => [['from' => '221775000001', 'type' => 'text', 'text' => ['body' => 'STOP']]]]]]]]]);
[$s] = $wh->req('POST', '/webhooks/whatsapp', $payload, ['Content-Type: application/json']); T::eq($s, 403, 'WhatsApp : message sans signature refusé');
[$s] = $wh->req('POST', '/webhooks/whatsapp', $payload, ['Content-Type: application/json', 'X-Hub-Signature-256: sha256=' . hash_hmac('sha256', $payload, 'mauvaise-cle')]); T::eq($s, 403, 'WhatsApp : mauvaise signature refusée');
\App\Core\Database::reset(); T::eq($st($cid1), 1, 'client toujours inscrit après les refus');
[$s, , $b] = $wh->req('POST', '/webhooks/whatsapp', $payload, ['Content-Type: application/json', 'X-Hub-Signature-256: sha256=' . hash_hmac('sha256', $payload, 'secret-app')]);
\App\Core\Database::reset(); T::ok($s === 200 && $st($cid1) === 0, 'WhatsApp : « STOP » correctement signé, client désinscrit');
$ev = \App\Core\Database::one('SELECT marketing_optout_at, marketing_consent_source FROM customers WHERE id = ?', [$cid1]);
T::ok($ev['marketing_optout_at'] && $ev['marketing_consent_source'] === 'whatsapp', 'désinscription datée, source « WhatsApp »');
[$s, , $b] = $admin->get('/admin/clients/' . $cid1); T::ok($s === 200 && str_contains($b, 'Désinscrit le'), 'fiche client : désinscription visible avec sa date');
[$s, , $b] = $admin->get('/admin/marketing/canaux'); T::ok(str_contains($b, '/webhooks/whatsapp') && str_contains($b, '/webhooks/sms') && !str_contains($b, 'secret-app') && !str_contains($b, 'jeton-sms-123'), 'adresses des webhooks affichées, secrets jamais réaffichés');
[$s, , $b] = $admin->get('/admin/marketing/publicites'); T::ok($s === 200 && str_contains($b, 'Par plateforme') && str_contains($b, 'Par annonce'), 'résultats publicitaires : page affichée');
[$s] = $m->get('/admin/marketing/publicites'); T::eq($s, 200, 'gestionnaire : résultats publicitaires autorisés');
[$s] = $ag->get('/admin/marketing/publicites'); T::ok($s === 403 || $s === 302, 'agent : résultats publicitaires refusés');
// Consentement à la réservation : la case apparaît sur le formulaire public
[, , $b] = (new Client($port))->get('/reserver'); T::ok(str_contains($b, 'id="consent"') && !preg_match('/id="consent"[^>]*checked/', $b), 'formulaire de réservation : case d\'offres présente, décochée par défaut');

// ------------------------------------------------------------------ Parcours simplifiés
T::group('Parcours simplifiés : lien de suivi, recherche, actions rapides, tableau de bord');
\App\Core\Database::reset();
$tok = \App\Core\Database::val('SELECT track_token FROM bookings WHERE reference = ?', [$ref]);
$lk = new Client($port);
[$s, $h] = $lk->get("/suivi/$ref/$tok"); T::ok($s === 302 && str_ends_with($h['location'][0], "/suivi/$ref"), 'lien de suivi personnel : redirige vers la réservation');
[$s, , $b] = $lk->get("/suivi/$ref"); T::ok($s === 200 && str_contains($b, $ref), 'réservation visible sans saisir référence ni téléphone');
[$s, $hh] = $lk->get("/suivi/$ref/calendrier"); T::ok($s === 200 && str_contains(implode(' ', $hh['content-type'] ?? []), 'text/calendar'), 'le lien personnel n\'avale pas la route « agenda »');
T::ok(str_contains($b, 'Réserver à nouveau') && str_contains($b, '/reserver?service='), 'réservation terminée ou annulée : bouton « Réserver à nouveau »');
$bad = new Client($port);
[$s, $h] = $bad->get("/suivi/$ref/" . str_repeat('a', 32)); T::ok($s === 302 && str_ends_with($h['location'][0], '/suivi'), 'mauvais jeton : renvoyé au formulaire');
[$s] = $bad->get("/suivi/$ref"); T::eq($s, 302, 'mauvais jeton : aucun accès accordé');
[$s] = $bad->get('/suivi/CU-INCONNU/' . str_repeat('b', 32)); T::eq($s, 302, 'référence inconnue : même réponse');
[, , $b] = (new Client($port))->get('/reserver'); T::ok(str_contains($b, 'id="prefill-note"') && str_contains($b, 'id="prefill-clear"'), 'formulaire de réservation : mémoire des coordonnées (avec « effacer »)');

[$s, , $b] = $admin->get('/admin'); T::ok($s === 200 && str_contains($b, 'À faire maintenant') && str_contains($b, 'Premiers pas'), 'tableau de bord : « À faire maintenant » et « Premiers pas »');
T::eq(substr_count($b, 'class="side-link'), 8, 'menu d\'administration : 8 entrées seulement');
T::ok(str_contains($b, 'href="/admin/configuration"') && str_contains($b, 'name="q"') && str_contains($b, '/admin/recherche'), 'entrée « Configuration » et barre de recherche présentes');
foreach (['/admin/configuration' => 'Configuration', '/admin/crud/services' => 'Configuration', '/admin/parametres' => 'Configuration', '/admin/crud/customers' => 'Clients', '/admin/crud/expenses' => 'Gestion', '/admin/crud/acquisition_channels' => 'Marketing', '/admin/marketing/campagnes' => 'Marketing'] as $u => $lab) {
    [, , $pg] = $admin->get($u); preg_match('#class="side-link active"[^>]*>([^<]+)<#', $pg, $mm); T::eq($mm[1] ?? null, $lab, "menu : « $u » garde « $lab » actif");
}
[$s, , $b] = $admin->get('/admin/configuration'); T::ok($s === 200 && str_contains($b, 'Services et tarifs') && str_contains($b, 'Quartiers desservis') && str_contains($b, 'Utilisateurs'), 'Configuration : tous les réglages rangés sur une page');
[$s, , $b] = $m->get('/admin/configuration'); T::ok($s === 200 && str_contains($b, 'Services et tarifs') && !str_contains($b, 'Utilisateurs') && !str_contains($b, 'Paramètres'), 'Configuration : le gestionnaire ne voit que ce qui lui est permis');
[$s] = $ag->get('/admin/configuration'); T::ok($s === 403 || $s === 302, 'Configuration : agent refusé');
[$s, , $b] = (new Client($port))->get('/api/geo/all/0'); $hoods = json_decode($b, true); T::ok($s === 200 && count($hoods) > 5 && isset($hoods[0]['commune'], $hoods[0]['travel_fee']), 'une seule liste de quartiers pour le client : ' . count($hoods) . ' quartiers');
[, , $b] = (new Client($port))->get('/reserver'); T::ok(!str_contains($b, 'id="region"') && !str_contains($b, 'id="commune"') && str_contains($b, 'id="full_name"') && substr_count($b, 'data-step="') === 6, 'formulaire : plus de listes en cascade, un seul champ pour le nom');
[$s, , $b] = $m->get('/admin'); T::ok($s === 200 && !str_contains($b, 'Premiers pas'), 'gestionnaire : pas de « Premiers pas » (réservé à l\'administrateur)');
[$s] = (new Client($port))->get('/admin/recherche?q=test'); T::eq($s, 302, 'recherche : visiteur anonyme redirigé');
[$s] = $ag->get('/admin/recherche?q=test'); T::ok($s === 403 || $s === 302, 'recherche : agent refusé');
[$s, , $b] = $admin->get('/admin/recherche?q=' . rawurlencode('Khady')); T::ok($s === 200 && str_contains($b, 'Prospects') && str_contains($b, 'Khady Seck'), 'recherche par nom : prospect retrouvé');
[$s, , $b] = $admin->get('/admin/recherche?q=' . rawurlencode('7 456 78')); T::ok($s === 200 && str_contains($b, 'Clients'), 'recherche par morceau de numéro : client retrouvé');
[$s, $h] = $admin->get('/admin/recherche?q=' . rawurlencode($ref)); T::ok($s === 302 && str_contains($h['location'][0], '/admin/reservations/'), 'référence exacte : ouvre directement la réservation');
[$s, , $b] = $admin->get('/admin/recherche?q=a'); T::ok($s === 200 && str_contains($b, 'au moins 2 caractères'), 'recherche trop courte : consigne affichée');
[$s, , $b] = $admin->get('/admin/recherche?q=' . rawurlencode("'; DROP TABLE bookings; --")); T::eq($s, 200, 'recherche : saisie piégée sans effet');
[$s, , $b] = $m->get('/admin/recherche?q=Khady'); T::ok($s === 200 && str_contains($b, 'Prospects'), 'gestionnaire : recherche prospects autorisée (droit CRM)');

// Confirmation en un clic depuis une liste
\App\Core\Database::reset();
$pend = \App\Core\Database::val("SELECT id FROM bookings WHERE status = 'pending' LIMIT 1");
if ($pend) {
    [, , $b] = $admin->get('/admin/reservations'); T::ok(str_contains($b, 'value="confirmed"') && str_contains($b, 'wa.me/') && str_contains($b, 'tel:+'), 'liste des réservations : boutons Confirmer, Appeler, WhatsApp');
    $tt = $admin->token('/admin/reservations');
    [$s, $h] = $admin->req('POST', "/admin/reservations/$pend/statut", ['_csrf' => $tt, 'to' => 'confirmed', 'back' => '1'], ['Referer: http://127.0.0.1:' . $port . '/admin/reservations']);
    \App\Core\Database::reset();
    T::ok($s === 302 && str_ends_with($h['location'][0], '/admin/reservations') && \App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$pend]) === 'confirmed', 'Confirmer en un clic : statut changé, retour sur la liste');
} else T::ok(true, 'aucune réservation en attente à confirmer dans ce jeu d\'essai');

// ------------------------------------------------------------------ Cron
T::group('Tâches planifiées HTTP');
[$s] = $cl->get('/cron/run'); T::eq($s, 403, 'cron sans jeton → 403');
[$s] = $cl->get('/cron/run?token=mauvais'); T::eq($s, 403, 'cron avec mauvais jeton → 403');
[$s, , $b] = $cl->get('/cron/run?token=jeton-de-test-123'); T::ok($s === 200 && isset(json_decode($b, true)['reminders']), 'cron avec bon jeton → 200');
[$s, , $b] = $cl->req('GET', '/cron/run', null, ['X-Cron-Token: jeton-de-test-123']); T::ok($s === 200 && isset(json_decode($b, true)['reminders']), 'cron : jeton accepté en en-tête X-Cron-Token');
[$s, , $b] = $cl->req('GET', '/cron/run', null, ['Authorization: Bearer jeton-de-test-123']); T::eq($s, 200, 'cron : jeton accepté en Authorization: Bearer');
[$s] = $cl->req('GET', '/cron/run', null, ['X-Cron-Token: faux']); T::eq($s, 403, 'cron : mauvais jeton en en-tête refusé');
[$s] = $admin->post('/deconnexion', ['_csrf' => $admin->token('/admin')]); [$s2] = $admin->get('/admin'); T::eq($s2, 302, 'déconnexion : /admin de nouveau protégé');

exit(T::summary());
