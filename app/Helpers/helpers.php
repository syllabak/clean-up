<?php
declare(strict_types=1);

use App\Core\{Csrf, Database, Env, Session};

function e(mixed $v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return gmdate('Y-m-d H:i:s'); }          // stockage en UTC
function url(string $path = ''): string { return rtrim(Env::get('APP_URL', ''), '/') . '/' . ltrim($path, '/'); }
function asset(string $path): string
{
    $f = BASE_PATH . '/public/assets/' . $path;
    return '/assets/' . $path . (is_file($f) ? '?v=' . filemtime($f) : '');
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">'; }
function old(string $k, mixed $d = ''): string { return e($_SESSION['_old'][$k] ?? $d); }
function flash(string $k): mixed { return Session::pull($k); }

function money(int|float|null $n): string { return number_format((float) $n, 0, ',', ' ') . ' ' . setting('currency', 'FCFA'); }
function fmt_date(?string $d): string
{
    if (!$d) return '';
    $days = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $t = strtotime($d);
    return $days[(int) date('N', $t)] . ' ' . date('j', $t) . ' ' . $months[(int) date('n', $t)] . ' ' . date('Y', $t);
}
function fmt_duration(int $min): string
{
    $h = intdiv($min, 60); $m = $min % 60;
    return $h > 0 ? ($m ? "{$h} h " . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : "{$h} h") : "{$m} min";
}

function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null || !empty($GLOBALS['__settings_dirty'])) {
        $cache = [];
        $GLOBALS['__settings_dirty'] = false;
        try {
            foreach (Database::all('SELECT skey, svalue FROM settings WHERE company_id = ?', [Env::companyId()]) as $r) $cache[$r['skey']] = $r['svalue'];
        } catch (\Throwable) {}
    }
    return $cache[$key] ?? $default;
}
function setting_set(string $key, string $value): void
{
    $cid = Env::companyId();
    $exists = Database::val('SELECT id FROM settings WHERE company_id = ? AND skey = ?', [$cid, $key]);
    $exists ? Database::exec('UPDATE settings SET svalue = ? WHERE id = ?', [$value, $exists])
            : Database::insert('settings', ['company_id' => $cid, 'skey' => $key, 'svalue' => $value]);
    $GLOBALS['__settings_dirty'] = true;
}

function normalize_phone(string $p): string
{
    $d = preg_replace('/\D+/', '', $p) ?? '';
    if (str_starts_with($d, '00')) $d = substr($d, 2);
    if (strlen($d) === 9) $d = '221' . $d;   // numéro sénégalais sans indicatif
    return $d;
}

function slugify(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','œ'=>'oe','&'=>'et']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $s) ?? '', '-') ?: 'item';
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . $to, true, $code);
    exit;
}
function json_response(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function abort(int $code, string $msg = ''): never
{
    http_response_code($code);
    if (\App\Core\Request::isJson()) json_response(['error' => $msg ?: "Erreur $code"], $code);
    $titles = [403 => 'Accès refusé', 404 => 'Page introuvable', 419 => 'Session expirée', 429 => 'Trop de requêtes', 500 => 'Erreur serveur'];
    echo \App\Core\View::render('public/error', ['code' => $code, 'title' => $titles[$code] ?? 'Erreur', 'message' => $msg]);
    exit;
}

const DAYS_FR = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

const BOOKING_STATUSES = [
    'pending' => ['En attente', 'warning'],
    'confirmed' => ['Confirmée', 'primary'],
    'assigned' => ['Affectée', 'info'],
    'en_route' => ['En route', 'secondary'],
    'in_progress' => ['En intervention', 'dark'],
    'completed' => ['Terminée', 'success'],
    'cancelled' => ['Annulée', 'danger'],
];
function status_label(string $s): string { return BOOKING_STATUSES[$s][0] ?? $s; }
function status_badge(string $s): string
{
    [$l, $c] = BOOKING_STATUSES[$s] ?? [$s, 'light'];
    return '<span class="badge text-bg-' . $c . '">' . e($l) . '</span>';
}
function hm_to_min(string $hm): int { [$h, $m] = array_map('intval', explode(':', $hm)); return $h * 60 + $m; }
function min_to_hm(int $m): string { return sprintf('%02d:%02d', intdiv($m, 60), $m % 60); }
function can(string $perm): bool { return \App\Core\Auth::can($perm); }

function View_flash(?string $ok, ?string $err): string
{
    return \App\Core\View::partial('partials/flash', ['ok' => $ok, 'err' => $err]);
}

/** Prix « dès » avec son unité : 300 FCFA /m² pour les services facturés à la surface. */
function from_label(array $service, int $price): string
{
    return money($price) . (($service['billing_unit'] ?? '') === 'per_sqm' ? ' /m²' : '');
}

/** +221 77 123 45 67 pour un numéro sénégalais normalisé ; sinon +chiffres. */
function phone_fmt(?string $p): string
{
    $d = preg_replace('/\D/', '', (string) $p) ?? '';
    if (strlen($d) === 12 && str_starts_with($d, '221')) return '+221 ' . substr($d, 3, 2) . ' ' . substr($d, 5, 3) . ' ' . substr($d, 8, 2) . ' ' . substr($d, 10, 2);
    return $d === '' ? '' : '+' . $d;
}
