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
foreach (['/' => 'Net', '/services' => 'Nos services', '/services/lavage-automobile' => 'Formules', '/reserver' => 'Réserver', '/faq' => 'Questions', '/contact' => 'Contact', '/a-propos' => 'À propos',
          '/conditions-generales' => 'Conditions', '/confidentialite' => 'confidentialité', '/suivi' => 'Suivre', '/connexion' => 'Espace équipe', '/hors-ligne' => 'hors connexion'] as $path => $needle) {
    [$s, , $b] = $c->get($path);
    T::ok($s === 200 && stripos($b, $needle) !== false, "GET $path → 200");
}
[$s] = $c->get('/page-inexistante'); T::eq($s, 404, 'URL inconnue → 404');
[$s] = $c->get('/services/n-existe-pas'); T::eq($s, 404, 'service inconnu → 404');
[$s, $h, $b] = $c->get('/manifest.webmanifest'); T::ok($s === 200 && json_decode($b, true)['display'] === 'standalone', 'manifest PWA valide');
[$s, , $b] = $c->get('/agent.webmanifest'); T::ok($s === 200 && json_decode($b, true)['start_url'] === '/agent', 'manifest agent valide');
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
[$s] = $anon->post('/connexion', ['email' => 'admin@netexpress.test', 'password' => 'Admin#2026']); T::eq($s, 419, 'POST sans jeton CSRF → 419');
[$s] = $anon->post('/connexion', ['_csrf' => 'faux', 'email' => 'a@b.c', 'password' => 'x']); T::eq($s, 419, 'POST avec faux jeton → 419');
[$s] = $anon->req('POST', '/reservation', ['a' => 1], [], true); T::eq($s, 419, 'POST JSON /reservation sans jeton → 419');
[$s] = $anon->post('/deconnexion'); T::eq($s, 419, 'déconnexion sans jeton → 419');
T::eq($anon->login('admin@netexpress.test', 'mauvais'), 302, 'mauvais mot de passe → retour au formulaire');
[$s] = $anon->get('/admin'); T::eq($s, 302, '... et toujours non connecté');
T::eq($anon->login("admin@netexpress.test' OR '1'='1", 'x'), 302, 'tentative d\'injection SQL dans l\'email : refusée sans erreur');

$admin = new Client($port);
[$s, $h] = (function () use ($admin) { $t = $admin->token(); return $admin->post('/connexion', ['_csrf' => $t, 'email' => 'admin@netexpress.test', 'password' => 'Admin#2026']); })();
T::ok($s === 302 && str_ends_with($h['location'][0], '/admin'), 'connexion admin → /admin');
[$s, , $b] = $admin->get('/admin'); T::ok($s === 200 && str_contains($b, 'Tableau de bord'), 'tableau de bord accessible');
foreach (['/admin/reservations', '/admin/planning', '/admin/planning?view=week', '/admin/parametres', '/admin/notifications', '/admin/audit', '/admin/crud/services', '/admin/crud/neighborhoods', '/admin/crud/teams', '/admin/crud/staff', '/admin/crud/users', '/admin/crud/notifications', '/admin/crud/time_off', '/admin/crud/pages'] as $p) {
    [$s] = $admin->get($p); T::eq($s, 200, "admin : GET $p");
}
[$s] = $admin->get('/admin/crud/inexistant'); T::eq($s, 404, 'ressource CRUD inconnue → 404');
$m = new Client($port); T::eq($m->login('gestion@netexpress.test', 'Gestion#2026'), 302, 'connexion gestionnaire');
[$s] = $m->get('/admin/reservations'); T::eq($s, 200, 'gestionnaire : réservations accessibles');
[$s] = $m->get('/admin/parametres'); T::eq($s, 403, 'gestionnaire : paramètres refusés (403)');
[$s] = $m->get('/admin/crud/users'); T::eq($s, 403, 'gestionnaire : utilisateurs refusés (403)');
[$s] = $m->get('/admin/audit'); T::eq($s, 403, "gestionnaire : journal d'audit refusé (403)");
// Limitation de débit de la connexion
$bf = new Client($port); $codes = [];
for ($i = 0; $i < 11; $i++) { $t = $bf->token(); [$s] = $bf->post('/connexion', ['_csrf' => $t, 'email' => 'x@y.zz', 'password' => 'nope' . $i]); $codes[] = $s; }
T::ok(in_array(429, $codes, true), 'force brute : blocage (429) après quelques tentatives [' . implode(',', $codes) . ']');
$stillBlocked = (new Client($port)); $t = $stillBlocked->token(); [$s] = $stillBlocked->post('/connexion', ['_csrf' => $t, 'email' => 'admin@netexpress.test', 'password' => 'Admin#2026']);
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
T::ok($s === 201 && str_starts_with($r['reference'], 'NX-'), 'réservation créée : ' . ($r['reference'] ?? $b));
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
[$s, , $b] = $tr->get('/suivi/NX-000000-ZZZZ'); T::eq($s, 302, 'référence d\'un tiers : pas d\'accès');
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
[$s] = $admin->post("/admin/reservations/$bid/statut", ['_csrf' => $at, 'to' => 'completed']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'confirmed', 'transition interdite refusée côté serveur');
[$s, , $b] = $admin->req('POST', '/admin/api/creneaux', ['booking_id' => $bid, 'date' => next_dow(5)], ['X-CSRF-Token: ' . $at], true); T::ok($s === 200 && count(json_decode($b, true)['slots']) > 3, 'API des créneaux pour déplacement');
$moussaStaff = (int) \App\Core\Database::val("SELECT id FROM staff WHERE name = 'Moussa Diop'");
[$s] = $admin->post("/admin/reservations/$bid/affecter", ['_csrf' => $at, 'staff_id' => $moussaStaff]); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'assigned', 'affectation à Moussa depuis l\'admin');
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
$admin->post('/admin/parametres', ['_csrf' => $t, 'company_phone' => '+221 33 999 99 99', 'slot_step_minutes' => '30', 'min_lead_hours' => '1', 'max_days_ahead' => '30', 'buffer_same_zone' => '10', 'cancel_hours_before' => '12', 'reminder_hours_before' => '24', 'notif_max_attempts' => '4', 'notif_retry_minutes' => '10', 'currency' => 'FCFA', 'company_name' => "Net'Express"]);
[, , $b] = $cl->get('/contact'); T::ok(str_contains($b, '+221 33 999 99 99'), 'paramètres : le téléphone modifié apparaît sur le site');
$t = $admin->token('/admin/parametres');
[$s, $h] = $admin->post('/admin/parametres', ['_csrf' => $t, 'slot_step_minutes' => 'abc']); [, , $b] = $admin->get('/admin/parametres'); T::ok(str_contains($b, 'doit être un nombre entier'), 'paramètres : valeur invalide refusée');
// Upload refusé / accepté dans les paramètres
[$s] = $admin->multipart('/admin/parametres', ['_csrf' => $admin->token('/admin/parametres'), 'slot_step_minutes' => '30', 'min_lead_hours' => '1', 'max_days_ahead' => '30', 'buffer_same_zone' => '10', 'cancel_hours_before' => '12', 'reminder_hours_before' => '24', 'notif_max_attempts' => '4', 'notif_retry_minutes' => '10'], 'hero_image', 'shell.php', '<?php system($_GET["c"]); ?>', 'image/jpeg');
[, , $b] = $admin->get('/admin/parametres'); T::ok(str_contains($b, 'Format refusé') || str_contains($b, 'alert-danger'), 'upload : script PHP déguisé en image refusé');
T::ok(!glob(BASE_PATH . '/public/uploads/site/*.php'), 'upload : aucun fichier PHP écrit sur le disque');

// ------------------------------------------------------------------ Agent
T::group('Interface agent : droits et mission');
$ag = new Client($port); T::eq($ag->login('moussa@netexpress.test', 'Agent#2026'), 302, 'connexion agent Moussa');
[$s, $h] = $ag->get('/admin'); T::eq($s, 403, 'agent : /admin interdit (403)');
[$s] = $ag->get('/admin/crud/services'); T::eq($s, 403, 'agent : CRUD interdit (403)');
[$s] = $ag->get('/admin/reservations'); T::eq($s, 403, 'agent : réservations (admin) interdites');
[$s, , $b] = $ag->get('/agent'); T::ok($s === 200 && str_contains($b, 'Bonjour Moussa'), 'tableau de bord agent');
[$s, , $b] = $ag->get("/agent/mission/$bid"); T::ok($s === 200 && str_contains($b, 'tel:') && str_contains($b, 'google.com/maps') && str_contains($b, 'Je suis en route'), 'mission affectée : téléphone cliquable, itinéraire, bouton d\'étape');
$otherAgent = new Client($port); $otherAgent->login('awa@netexpress.test', 'Agent#2026');
[$s] = $otherAgent->get("/agent/mission/$bid"); T::eq($s, 404, 'autre agent : mission d\'un collègue introuvable (404)');
[$s] = $otherAgent->post("/agent/mission/$bid/statut", ['_csrf' => $otherAgent->token('/agent'), 'to' => 'en_route']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'assigned', 'autre agent : changement de statut refusé');
$at = $ag->token("/agent/mission/$bid");
[$s] = $ag->post("/agent/mission/$bid/statut", ['_csrf' => $at, 'to' => 'completed']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'assigned', 'agent : impossible de sauter des étapes');
$ag->post("/agent/mission/$bid/statut", ['_csrf' => $at, 'to' => 'en_route']); \App\Core\Database::reset();
T::eq(\App\Core\Database::val('SELECT status FROM bookings WHERE id = ?', [$bid]), 'en_route', 'agent : en route');
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

// ------------------------------------------------------------------ Cron
T::group('Tâches planifiées HTTP');
[$s] = $cl->get('/cron/run'); T::eq($s, 403, 'cron sans jeton → 403');
[$s] = $cl->get('/cron/run?token=mauvais'); T::eq($s, 403, 'cron avec mauvais jeton → 403');
[$s, , $b] = $cl->get('/cron/run?token=jeton-de-test-123'); T::ok($s === 200 && isset(json_decode($b, true)['reminders']), 'cron avec bon jeton → 200');
[$s] = $admin->post('/deconnexion', ['_csrf' => $admin->token('/admin')]); [$s2] = $admin->get('/admin'); T::eq($s2, 302, 'déconnexion : /admin de nouveau protégé');

exit(T::summary());
