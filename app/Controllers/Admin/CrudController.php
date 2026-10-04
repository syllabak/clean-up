<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\{Audit, Auth, Database as DB, Env, Request, Session, Upload};

/** CRUD générique : une seule implémentation, pilotée par config/crud.php (≈ 25 ressources). */
final class CrudController extends Controller
{
    private const PER_PAGE = 30;

    public function index(string $type): void
    {
        $c = $this->cfg($type);
        [$where, $p] = $this->scope($c);
        if (($q = Request::str('q')) !== '' && !empty($c['search'])) {
            $where[] = '(' . implode(' OR ', array_map(fn($col) => "$col LIKE ?", $c['search'])) . ')';
            foreach ($c['search'] as $_) $p[] = '%' . $q . '%';
        }
        $page = max(1, Request::int('page', 1));
        $total = (int) DB::val("SELECT COUNT(*) FROM {$c['table']} WHERE " . implode(' AND ', $where), $p);
        $rows = DB::all("SELECT * FROM {$c['table']} WHERE " . implode(' AND ', $where) . ' ORDER BY ' . ($c['order'] ?? 'id') . ' LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $p);
        $this->admin('admin/crud_index', ['title' => $c['title'], 'c' => $c, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'labels' => $this->listLabels($c, $rows), 'filterQs' => $this->filterQuery($c, Request::all()), 'parent' => $this->parentLabel($c)]);
    }

    public function create(string $type): void
    {
        $c = $this->cfg($type);
        if (($c['can_create'] ?? true) === false) abort(404);
        $values = [];
        foreach ($c['fields'] as $k => $f) $values[$k] = $f['default'] ?? '';
        foreach ($c['filters'] ?? [] as $k) if (Request::int($k)) $values[$k] = Request::int($k);
        if (!empty($_SESSION['_old'])) $values = array_merge($values, array_filter($_SESSION['_old'], fn($v) => $v !== ''));
        $this->form($c, 'Nouveau', $values, null);
    }

    public function edit(string $type, string $id): void
    {
        $c = $this->cfg($type);
        $row = $this->row($c, (int) $id);
        $values = $row;
        foreach ($c['fields'] as $k => $f) {
            if (($f['type'] ?? '') === 'pivot') $values[$k] = array_column(DB::all("SELECT {$f['other']} AS v FROM {$f['table']} WHERE {$f['key']} = ?", [(int) $id]), 'v');
            if (($f['type'] ?? '') === 'password') $values[$k] = '';
            if (($f['type'] ?? '') === 'weekdays') $values[$k] = $row[$k] === '' ? [] : explode(',', (string) $row[$k]);
        }
        if (!empty($_SESSION['_old'])) $values = array_merge($values, $_SESSION['_old']);
        $this->form($c, 'Modifier', $values, $row);
    }

    public function store(string $type): void
    {
        $c = $this->cfg($type);
        if (($c['can_create'] ?? true) === false) abort(404);
        [$errors, $data, $pivots] = $this->collect($c, null);
        if ($errors) { $this->withOld(); Session::flash('errors', $errors); redirect("/admin/crud/$type/nouveau" . $this->filterQuery($c, Request::all())); }
        if (!empty($c['company'])) $data['company_id'] = Env::companyId();
        if (DB::hasColumn($c['table'], 'created_at')) $data['created_at'] = now();
        try {
            $id = DB::transaction(function () use ($c, $data, $pivots) { $id = DB::insert($c['table'], $data); $this->savePivots($c, $id, $pivots); return $id; });
        } catch (\PDOException $e) {
            $this->withOld(); $this->err($this->dbMessage($e)); redirect("/admin/crud/$type/nouveau" . $this->filterQuery($c, Request::all()));
        }
        Audit::log("crud.$type.create", $type, $id);
        $this->ok('Élément créé.');
        redirect("/admin/crud/$type" . $this->filterQuery($c, $data));
    }

    public function update(string $type, string $id): void
    {
        $c = $this->cfg($type);
        $row = $this->row($c, (int) $id);
        [$errors, $data, $pivots] = $this->collect($c, $row);
        if ($errors) { $this->withOld(); Session::flash('errors', $errors); redirect("/admin/crud/$type/$id/modifier"); }
        if (DB::hasColumn($c['table'], 'updated_at')) $data['updated_at'] = now();
        try {
            DB::transaction(function () use ($c, $id, $data, $pivots) { if ($data) DB::update($c['table'], (int) $id, $data); $this->savePivots($c, (int) $id, $pivots); });
        } catch (\PDOException $e) {
            $this->withOld(); $this->err($this->dbMessage($e)); redirect("/admin/crud/$type/$id/modifier");
        }
        Audit::log("crud.$type.update", $type, (int) $id, ['fields' => array_keys($data)]);
        $this->ok('Modifications enregistrées.');
        redirect("/admin/crud/$type" . $this->filterQuery($c, $data + $row));
    }

    public function destroy(string $type, string $id): void
    {
        $c = $this->cfg($type);
        $row = $this->row($c, (int) $id);
        if ($type === 'users' && (int) $id === Auth::id()) { $this->err('Vous ne pouvez pas supprimer votre propre compte.'); redirect('/admin/crud/users'); }
        try {
            if (!empty($c['soft_delete'])) DB::update($c['table'], (int) $id, ['deleted_at' => now()]);
            else DB::transaction(function () use ($c, $id) {
                foreach ($c['fields'] as $f) if (($f['type'] ?? '') === 'pivot') DB::exec("DELETE FROM {$f['table']} WHERE {$f['key']} = ?", [(int) $id]);
                DB::exec("DELETE FROM {$c['table']} WHERE id = ?", [(int) $id]);
            });
        } catch (\PDOException $e) { $this->err('Suppression impossible : cet élément est utilisé ailleurs.'); redirect("/admin/crud/$type" . $this->filterQuery($c, $row)); }
        Audit::log("crud.$type.delete", $type, (int) $id);
        $this->ok('Élément supprimé.');
        redirect("/admin/crud/$type" . $this->filterQuery($c, $row));
    }

    // ------------------------------------------------------------------ internes
    private function cfg(string $type): array
    {
        $all = require BASE_PATH . '/config/crud.php';
        $c = $all[$type] ?? abort(404);
        if (!Auth::can($c['permission'])) { Audit::log('access_denied', 'crud', null, ['type' => $type]); abort(403, "Vous n'avez pas la permission de gérer cette section."); }
        return $c + ['type' => $type];
    }

    private function scope(array $c): array
    {
        $w = ['1 = 1']; $p = [];
        if (!empty($c['company'])) { $w[] = 'company_id = ?'; $p[] = Env::companyId(); }
        if (!empty($c['soft_delete'])) $w[] = 'deleted_at IS NULL';
        foreach ($c['filters'] ?? [] as $k) if (Request::int($k)) { $w[] = "$k = ?"; $p[] = Request::int($k); }
        return [$w, $p];
    }

    private function row(array $c, int $id): array
    {
        $w = ['id = ?']; $p = [$id];
        if (!empty($c['company'])) { $w[] = 'company_id = ?'; $p[] = Env::companyId(); }
        if (!empty($c['soft_delete'])) $w[] = 'deleted_at IS NULL';
        return DB::one("SELECT * FROM {$c['table']} WHERE " . implode(' AND ', $w), $p) ?? abort(404);
    }

    private function form(array $c, string $mode, array $values, ?array $row): void
    {
        $opts = [];
        foreach ($c['fields'] as $k => $f) {
            if (isset($f['ref'])) $opts[$k] = $this->refOptions($f['ref']);
            elseif (isset($f['options'])) $opts[$k] = $f['options'];
        }
        $this->admin('admin/crud_form', ['title' => $mode . ' — ' . $c['title'], 'c' => $c, 'values' => $values, 'row' => $row, 'opts' => $opts, 'mode' => $mode, 'filterQs' => $this->filterQuery($c, $row ?? Request::all())]);
    }

    private function refOptions(array $ref): array
    {
        [$table, $col, $where] = array_pad($ref, 3, '');
        $rows = DB::all("SELECT id, $col AS label FROM $table" . ($where ? " WHERE $where" : '') . " ORDER BY $col");
        return array_column($rows, 'label', 'id');
    }

    /** @return array{0:array,1:array,2:array} erreurs, colonnes à enregistrer, pivots */
    private function collect(array $c, ?array $existing): array
    {
        $in = Request::all(); $errors = []; $data = []; $pivots = [];
        foreach ($c['fields'] as $k => $f) {
            $type = $f['type']; $label = $f['label'];
            if (!empty($f['readonly'])) continue;
            $col = $f['column'] ?? $k;
            if ($type === 'pivot') {
                $allowed = array_keys($this->refOptions($f['ref']));
                $pivots[$k] = array_values(array_filter(array_map('intval', is_array($in[$k] ?? null) ? $in[$k] : []), fn($v) => in_array($v, $allowed, true)));
                continue;
            }
            if ($type === 'image') {
                try { $name = Upload::image($_FILES[$k] ?? ['error' => UPLOAD_ERR_NO_FILE], BASE_PATH . '/public/uploads/' . $f['dir']); }
                catch (\RuntimeException $e) { $errors[$k] = "$label : " . $e->getMessage(); continue; }
                if ($name) $data[$col] = '/uploads/' . $f['dir'] . '/' . $name;
                continue;
            }
            if ($type === 'bool') { $data[$col] = (($in[$k] ?? '') === '1' || ($in[$k] ?? '') === 1) ? 1 : 0; continue; }
            if ($type === 'weekdays') {
                $d = array_values(array_unique(array_filter(array_map('intval', is_array($in[$k] ?? null) ? $in[$k] : []), fn($v) => $v >= 1 && $v <= 7)));
                sort($d); $data[$col] = implode(',', $d); continue;
            }
            $raw = is_scalar($in[$k] ?? null) ? trim((string) $in[$k]) : '';
            if ($type === 'password') {
                if ($raw === '') { if (!$existing) $errors[$k] = "$label est obligatoire."; continue; }
                if (mb_strlen($raw) < ($f['minlen'] ?? 8)) { $errors[$k] = "$label : au moins " . ($f['minlen'] ?? 8) . ' caractères.'; continue; }
                $data[$col] = password_hash($raw, PASSWORD_DEFAULT); continue;
            }
            if ($raw === '') {
                if (!empty($f['auto_slug'])) { $raw = slugify((string) ($in[$f['auto_slug']] ?? '')); }
                elseif (!empty($f['required'])) { $errors[$k] = "$label est obligatoire."; continue; }
                else {
                    $data[$col] = match (true) {
                        !empty($f['empty_string']) => '',
                        !empty($f['nullable']) || in_array($type, ['time', 'date', 'select', 'text', 'textarea', 'email', 'tel'], true) => null,
                        default => $f['default'] ?? 0,
                    };
                    if ($type === 'number' && empty($f['nullable'])) $data[$col] = (int) ($f['default'] ?? 0);
                    continue;
                }
            }
            switch ($type) {
                case 'number':
                    if (!preg_match('/^-?\d{1,9}$/', $raw)) { $errors[$k] = "$label doit être un nombre entier."; break; }
                    if (isset($f['min']) && (int) $raw < $f['min']) { $errors[$k] = "$label : minimum {$f['min']}."; break; }
                    if (isset($f['max']) && (int) $raw > $f['max']) { $errors[$k] = "$label : maximum {$f['max']}."; break; }
                    $data[$col] = (int) $raw; break;
                case 'decimal':
                    $raw = str_replace(',', '.', $raw);
                    if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $raw)) { $errors[$k] = "$label n'est pas un nombre valide."; break; }
                    $data[$col] = $raw; break;
                case 'email':
                    if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) { $errors[$k] = "$label n'est pas un email valide."; break; }
                    $data[$col] = !empty($f['lower']) ? mb_strtolower($raw) : $raw; break;
                case 'tel':
                    $norm = normalize_phone($raw);
                    if (!preg_match('/^\d{9,15}$/', $norm)) { $errors[$k] = "$label n'est pas un numéro valide."; break; }
                    $data[$col] = ($f['normalize'] ?? '') === 'phone' ? $norm : $raw; break;
                case 'time':
                    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $raw)) { $errors[$k] = "$label : heure invalide (HH:MM)."; break; }
                    $data[$col] = $raw; break;
                case 'date':
                    $dt = \DateTime::createFromFormat('Y-m-d', $raw);
                    if (!$dt || $dt->format('Y-m-d') !== $raw) { $errors[$k] = "$label : date invalide."; break; }
                    $data[$col] = $raw; break;
                case 'select':
                    $allowed = isset($f['ref']) ? array_keys($this->refOptions($f['ref'])) : array_keys($f['options']);
                    if (!in_array(is_numeric($raw) ? (int) $raw : $raw, $allowed, true) && !in_array($raw, array_map('strval', $allowed), true)) { $errors[$k] = "$label : valeur non autorisée."; break; }
                    $data[$col] = isset($f['ref']) || is_int(array_key_first($f['options'] ?? [])) ? (int) $raw : $raw; break;
                default: // text, textarea
                    if (mb_strlen($raw) > ($type === 'textarea' ? 20000 : 500)) { $errors[$k] = "$label est trop long."; break; }
                    $data[$col] = !empty($f['auto_slug']) || $k === 'slug' ? slugify($raw) : $raw;
            }
        }
        // Slug unique par entreprise : suffixe numérique en cas de doublon
        if (isset($data['slug']) && isset($c['fields']['slug'])) $data['slug'] = $this->uniqueSlug($c, $data['slug'], $existing['id'] ?? 0);
        if (isset($c['fields']['end_date'], $data['start_date'], $data['end_date']) && $data['end_date'] < $data['start_date']) $errors['end_date'] = 'La date de fin précède la date de début.';
        if (isset($data['start_time'], $data['end_time']) && $data['end_time'] <= $data['start_time']) $errors['end_time'] = "L'heure de fin doit suivre l'heure de début.";
        if ($c['type'] === 'users' && !$errors) {
            $roleId = $data['role_id'] ?? ($existing['role_id'] ?? 0);
            $isAgent = DB::val('SELECT name FROM roles WHERE id = ?', [$roleId]) === 'agent';
            if ($isAgent && empty($data['staff_id'] ?? ($existing['staff_id'] ?? null))) $errors['staff_id'] = 'Un compte agent doit être relié à un agent.';
        }
        return [$errors, $data, $pivots];
    }

    private function uniqueSlug(array $c, string $slug, int $selfId): string
    {
        $base = $slug; $i = 2;
        while (DB::val("SELECT id FROM {$c['table']} WHERE slug = ? AND id <> ?" . (!empty($c['company']) ? ' AND company_id = ' . Env::companyId() : ''), [$slug, $selfId])) $slug = $base . '-' . $i++;
        return $slug;
    }

    private function savePivots(array $c, int $id, array $pivots): void
    {
        foreach ($pivots as $k => $ids) {
            $f = $c['fields'][$k];
            DB::exec("DELETE FROM {$f['table']} WHERE {$f['key']} = ?", [$id]);
            foreach ($ids as $o) DB::insert($f['table'], [$f['key'] => $id, $f['other'] => $o]);
        }
    }

    private function listLabels(array $c, array $rows): array
    {
        $out = [];
        foreach ($c['list'] as $col => $def) {
            if (!is_array($def) || $def[1] !== 'ref') continue;
            $ids = array_values(array_unique(array_filter(array_column($rows, $col))));
            if (!$ids) continue;
            $in = implode(',', array_map('intval', $ids));
            $out[$col] = array_column(DB::all("SELECT id, {$def[3]} AS label FROM {$def[2]} WHERE id IN ($in)"), 'label', 'id');
        }
        return $out;
    }

    private function filterQuery(array $c, array $source): string
    {
        $q = [];
        foreach ($c['filters'] ?? [] as $k) if (!empty($source[$k]) && is_numeric($source[$k])) $q[$k] = (int) $source[$k];
        return $q ? '?' . http_build_query($q) : '';
    }

    /** Nom de l'élément parent filtré (ex. « Lavage automobile » pour les formules), pour l'en-tête. */
    private function parentLabel(array $c): ?array
    {
        foreach ($c['filters'] ?? [] as $k) {
            if (!Request::int($k) || empty($c['fields'][$k]['ref'])) continue;
            [$t, $col] = $c['fields'][$k]['ref'];
            $label = DB::val("SELECT $col FROM $t WHERE id = ?", [Request::int($k)]);
            if ($label) return ['label' => $label, 'back' => $c['back'] ?? null, 'filter' => $k, 'id' => Request::int($k)];
        }
        return null;
    }

    private function dbMessage(\PDOException $e): string
    {
        return str_starts_with((string) $e->getCode(), '23') ? 'Cette valeur existe déjà ou est référencée par un élément inexistant.' : 'Erreur lors de l’enregistrement.';
    }
}
