<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Database, Env, ValidationException};

/**
 * Calcule prix et durée côté serveur à partir des tarifs configurés en base.
 * Le client ne transmet jamais un prix : seulement ses choix (formule, options, champs).
 *
 * subtotal =
 *   zone « surface »   : round((base + suppléments) × multiplicateurs) × m²  + options
 *   zone « quantité »  : (round((base + suppléments) × multiplicateurs) + options) × quantité
 *   sinon              : round((base + suppléments) × multiplicateurs) + options
 */
final class PricingService
{
    public function quote(int $serviceId, array $input, ?int $neighborhoodId = null): array
    {
        $svc = Database::one("SELECT * FROM services WHERE id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL", [$serviceId, Env::companyId()]);
        if (!$svc) throw new ValidationException(['service_id' => "Ce service n'existe pas."]);
        if ($svc['availability_status'] !== 'available') throw new ValidationException(['service_id' => "Ce service n'est pas réservable pour le moment."]);

        $errors = [];
        $details = [];

        // Formule
        $formulas = Database::all('SELECT * FROM service_prices WHERE service_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, id', [$serviceId]);
        $formula = null;
        if ($formulas) {
            $fid = (int) ($input['formula_id'] ?? 0);
            foreach ($formulas as $f) if ((int) $f['id'] === $fid) $formula = $f;
            if (!$formula) $errors['formula_id'] = 'Choisissez une formule.';
        }
        $base = (int) ($formula['amount'] ?? $svc['base_price']);
        $duration = (int) $svc['base_duration'] + (int) ($formula['duration_minutes'] ?? 0);
        if ($formula) $details[] = ['Formule', $formula['name']];

        // Champs dynamiques
        $delta = 0; $mult = 1.0; $areaVal = null; $qtyVal = null; $lines = [];
        $fields = Database::all('SELECT * FROM service_fields WHERE service_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY sort, id', [$serviceId]);
        $values = is_array($input['fields'] ?? null) ? $input['fields'] : [];
        foreach ($fields as $f) {
            $raw = $values[$f['field_key']] ?? '';
            $raw = is_scalar($raw) ? trim((string) $raw) : '';
            if ($raw === '') {
                if ($f['required']) $errors['fields.' . $f['field_key']] = $f['label'] . ' est obligatoire.';
                continue;
            }
            if ($f['type'] === 'select') {
                $c = Database::one('SELECT * FROM service_field_choices WHERE id = ? AND field_id = ? AND active = 1 AND deleted_at IS NULL', [(int) $raw, $f['id']]);
                if (!$c) { $errors['fields.' . $f['field_key']] = $f['label'] . ' : choix invalide.'; continue; }
                $delta += (int) $c['price_delta'];
                $mult *= (float) $c['multiplier'];
                $duration += (int) $c['duration_delta'];
                $details[] = [$f['label'], $c['label']];
            } elseif ($f['type'] === 'number') {
                if (!preg_match('/^\d+$/', $raw)) { $errors['fields.' . $f['field_key']] = $f['label'] . ' doit être un nombre entier.'; continue; }
                $n = (int) $raw;
                $min = $f['min_value'] !== null ? (int) $f['min_value'] : 0;
                $max = $f['max_value'] !== null ? (int) $f['max_value'] : 100000;
                if ($n < $min || $n > $max) { $errors['fields.' . $f['field_key']] = $f['label'] . " doit être compris entre $min et $max."; continue; }
                if ($f['role'] === 'area') $areaVal = $n;
                if ($f['role'] === 'quantity') $qtyVal = $n;
                $details[] = [$f['label'], (string) $n];
            } else {
                $details[] = [$f['label'], mb_substr($raw, 0, 200)];
            }
        }

        // Options supplémentaires
        $optTotal = 0; $optDuration = 0;
        $optIds = array_filter(array_map('intval', is_array($input['options'] ?? null) ? $input['options'] : []));
        $chosenOptions = [];
        foreach (array_unique($optIds) as $oid) {
            $o = Database::one('SELECT * FROM service_options WHERE id = ? AND service_id = ? AND active = 1 AND deleted_at IS NULL', [$oid, $serviceId]);
            if (!$o) { $errors['options'] = 'Option invalide.'; continue; }
            $optTotal += (int) $o['price'];
            $optDuration += (int) $o['duration_minutes'];
            $chosenOptions[] = $o['name'];
        }
        if ($chosenOptions) $details[] = ['Options', implode(', ', $chosenOptions)];

        if ($errors) throw new ValidationException($errors);

        $core = (int) round(($base + $delta) * $mult);
        $unitDuration = $duration + $optDuration;
        if ($areaVal !== null) {
            $factor = $areaVal;
            $subtotal = $core * $areaVal + $optTotal;
            $totalDuration = $unitDuration + max(0, $areaVal - 1) * (int) $svc['duration_per_unit'];
            $unitPrice = $core;
        } elseif ($qtyVal !== null) {
            $factor = $qtyVal;
            $subtotal = ($core + $optTotal) * $qtyVal;
            $totalDuration = $unitDuration * $qtyVal;
            $unitPrice = $core + $optTotal;
        } else {
            $factor = 1;
            $subtotal = $core + $optTotal;
            $totalDuration = $unitDuration;
            $unitPrice = $subtotal;
        }
        $totalDuration = max(15, $totalDuration);

        $travel = 0;
        if ($neighborhoodId) {
            $travel = (int) Database::val('SELECT travel_fee FROM neighborhoods WHERE id = ? AND company_id = ?', [$neighborhoodId, Env::companyId()]);
        }

        return [
            'service' => $svc, 'formula' => $formula, 'details' => $details,
            'quantity' => $factor, 'unit_price' => $unitPrice, 'subtotal' => $subtotal,
            'travel_fee' => $travel, 'total' => $subtotal + $travel, 'duration' => $totalDuration,
            'billing_unit' => $svc['billing_unit'],
        ];
    }
}
