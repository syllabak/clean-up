<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Database, Env};

final class ServiceController extends Controller
{
    public function index(): void
    {
        $cid = Env::companyId();
        $cats = Database::all('SELECT * FROM service_categories WHERE company_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort', [$cid]);
        // Services sans catégorie : regroupés en fin de liste pour ne jamais disparaître du site
        if (Database::val('SELECT COUNT(*) FROM services WHERE company_id = ? AND category_id IS NULL AND active = 1 AND deleted_at IS NULL', [$cid])) {
            $cats[] = ['id' => 0, 'name' => 'Autres services'];
        }
        foreach ($cats as &$c) {
            $c['services'] = $c['id']
                ? Database::all("SELECT * FROM services WHERE category_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort", [$c['id']])
                : Database::all("SELECT * FROM services WHERE company_id = ? AND category_id IS NULL AND active = 1 AND deleted_at IS NULL ORDER BY sort", [$cid]);
            foreach ($c['services'] as &$s) $s['from_price'] = self::fromPrice($s);
        }
        $this->view('public/services', ['title' => 'Nos services', 'cats' => $cats]);
    }

    public function show(string $slug): void
    {
        $s = Database::one('SELECT * FROM services WHERE company_id = ? AND slug = ? AND active = 1 AND deleted_at IS NULL', [Env::companyId(), $slug]);
        if (!$s) abort(404);
        $this->view('public/service', [
            'title' => $s['name'], 's' => $s, 'from_price' => self::fromPrice($s),
            'formulas' => Database::all('SELECT * FROM service_prices WHERE service_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort', [$s['id']]),
            'options' => Database::all('SELECT * FROM service_options WHERE service_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort', [$s['id']]),
            'images' => Database::all('SELECT * FROM service_images WHERE service_id = ? ORDER BY sort', [$s['id']]),
        ]);
    }

    /** Prix « à partir de » : formule la moins chère (ou prix de base) + plus petit supplément obligatoire. */
    public static function fromPrice(array $s): int
    {
        $min = Database::val('SELECT MIN(amount) FROM service_prices WHERE service_id = ? AND active = 1 AND deleted_at IS NULL', [$s['id']]);
        $base = $min !== null ? (int) $min : (int) $s['base_price'];
        $deltas = Database::all("SELECT f.id, (SELECT MIN(c.price_delta) FROM service_field_choices c WHERE c.field_id = f.id AND c.active = 1 AND c.deleted_at IS NULL) AS d
            FROM service_fields f WHERE f.service_id = ? AND f.type = 'select' AND f.active = 1 AND f.deleted_at IS NULL", [$s['id']]);
        return $base + (int) array_sum(array_column($deltas, 'd'));
    }
}
