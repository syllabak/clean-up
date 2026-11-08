<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Database as DB, Env};

/** Indicateurs de gestion : acquisition par canal (CAC, ROI), résultat (CA − dépenses), fidélité des clients. */
final class BusinessReportService
{
    public const EXPENSE_CATEGORIES = ['salaires' => 'Salaires et primes', 'carburant' => 'Carburant et déplacements', 'produits' => 'Produits et consommables', 'materiel' => 'Matériel et équipement',
        'marketing' => 'Marketing et publicité', 'loyer' => 'Loyer et charges', 'assurance' => 'Assurance', 'taxes' => 'Impôts et taxes', 'autre' => 'Autres'];

    /** Montant encaissé : montant saisi sur la réservation terminée, à défaut le montant estimé. */
    private const REVENUE = "COALESCE(b.declared_amount, b.total)";

    public static function range(?string $from, ?string $to): array
    {
        $ok = fn($d) => is_string($d) && ($t = \DateTime::createFromFormat('Y-m-d', $d)) && $t->format('Y-m-d') === $d;
        $from = $ok($from) ? $from : date('Y-m-01');
        $to = $ok($to) ? $to : date('Y-m-d');
        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    /** Une ligne par canal, plus « Non renseigné ». */
    public function acquisition(string $from, string $to): array
    {
        $cid = Env::companyId();
        $a = $from . ' 00:00:00'; $z = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
        $rows = [];
        foreach (DB::all('SELECT id, name, kind FROM acquisition_channels WHERE company_id = ? AND deleted_at IS NULL ORDER BY sort, name', [$cid]) as $c) $rows[(int) $c['id']] = $c + ['leads' => 0, 'won' => 0, 'customers' => 0, 'bookings' => 0, 'revenue' => 0, 'spend' => 0];
        $rows[0] = ['id' => 0, 'name' => 'Non renseigné', 'kind' => '', 'leads' => 0, 'won' => 0, 'customers' => 0, 'bookings' => 0, 'revenue' => 0, 'spend' => 0];
        $put = function (string $sql, array $p, string $k) use (&$rows) {
            foreach (DB::all($sql, $p) as $r) { $id = (int) $r['ch']; if (!isset($rows[$id])) $id = 0; $rows[$id][$k] += (int) $r['n']; }
        };
        $put('SELECT COALESCE(channel_id,0) AS ch, COUNT(*) AS n FROM leads WHERE company_id = ? AND deleted_at IS NULL AND created_at >= ? AND created_at < ? GROUP BY ch', [$cid, $a, $z], 'leads');
        $put("SELECT COALESCE(channel_id,0) AS ch, COUNT(*) AS n FROM leads WHERE company_id = ? AND deleted_at IS NULL AND status = 'won' AND created_at >= ? AND created_at < ? GROUP BY ch", [$cid, $a, $z], 'won');
        $put('SELECT COALESCE(channel_id,0) AS ch, COUNT(*) AS n FROM customers WHERE company_id = ? AND deleted_at IS NULL AND created_at >= ? AND created_at < ? GROUP BY ch', [$cid, $a, $z], 'customers');
        $put("SELECT COALESCE(b.channel_id,0) AS ch, COUNT(*) AS n FROM bookings b WHERE b.company_id = ? AND b.status <> 'cancelled' AND b.scheduled_date >= ? AND b.scheduled_date <= ? GROUP BY ch", [$cid, $from, $to], 'bookings');
        $put("SELECT COALESCE(b.channel_id,0) AS ch, COALESCE(SUM(" . self::REVENUE . "),0) AS n FROM bookings b WHERE b.company_id = ? AND b.status = 'completed' AND b.scheduled_date >= ? AND b.scheduled_date <= ? GROUP BY ch", [$cid, $from, $to], 'revenue');
        $put('SELECT COALESCE(channel_id,0) AS ch, COALESCE(SUM(amount),0) AS n FROM expenses WHERE company_id = ? AND deleted_at IS NULL AND expense_date >= ? AND expense_date <= ? GROUP BY ch', [$cid, $from, $to], 'spend');
        $out = [];
        foreach ($rows as $r) {
            if (!array_filter([$r['leads'], $r['customers'], $r['bookings'], $r['revenue'], $r['spend']])) continue;
            $r['lead_rate'] = $r['leads'] ? (int) round($r['won'] / $r['leads'] * 100) : null;
            $r['cac'] = $r['spend'] > 0 && $r['customers'] > 0 ? (int) round($r['spend'] / $r['customers']) : null;
            $r['roi'] = $r['spend'] > 0 ? (int) round(($r['revenue'] - $r['spend']) / $r['spend'] * 100) : null;
            $out[] = $r;
        }
        usort($out, fn($x, $y) => $y['revenue'] <=> $x['revenue']);
        return $out;
    }

    /** Résultat de la période : chiffre d'affaires, dépenses par catégorie, bénéfice. */
    public function profit(string $from, string $to): array
    {
        $cid = Env::companyId();
        $revenue = (int) DB::val("SELECT COALESCE(SUM(" . self::REVENUE . "),0) FROM bookings b WHERE b.company_id = ? AND b.status = 'completed' AND b.scheduled_date >= ? AND b.scheduled_date <= ?", [$cid, $from, $to]);
        $pipeline = (int) DB::val("SELECT COALESCE(SUM(total),0) FROM bookings WHERE company_id = ? AND status IN ('pending','confirmed','in_progress') AND scheduled_date >= ? AND scheduled_date <= ?", [$cid, $from, $to]);
        $byCat = array_column(DB::all('SELECT category, SUM(amount) AS n FROM expenses WHERE company_id = ? AND deleted_at IS NULL AND expense_date >= ? AND expense_date <= ? GROUP BY category', [$cid, $from, $to]), 'n', 'category');
        $expenses = (int) array_sum($byCat);
        return ['revenue' => $revenue, 'to_come' => $pipeline, 'expenses' => $expenses, 'profit' => $revenue - $expenses, 'margin' => $revenue > 0 ? (int) round(($revenue - $expenses) / $revenue * 100) : null, 'by_category' => array_map('intval', $byCat)];
    }

    /** Résultat des $n derniers mois (le plus ancien d'abord). */
    public function monthly(int $n = 6): array
    {
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $m = date('Y-m', strtotime('first day of -' . $i . ' month'));
            $p = $this->profit($m . '-01', date('Y-m-t', strtotime($m . '-01')));
            $out[] = ['month' => $m] + $p;
        }
        return $out;
    }

    public function customers(string $from, string $to): array
    {
        $cid = Env::companyId();
        $a = $from . ' 00:00:00'; $z = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
        $total = (int) DB::val('SELECT COUNT(*) FROM customers WHERE company_id = ? AND deleted_at IS NULL', [$cid]);
        $new = (int) DB::val('SELECT COUNT(*) FROM customers WHERE company_id = ? AND deleted_at IS NULL AND created_at >= ? AND created_at < ?', [$cid, $a, $z]);
        $buyers = (int) DB::val("SELECT COUNT(DISTINCT customer_id) FROM bookings WHERE company_id = ? AND status = 'completed'", [$cid]);
        $repeat = (int) DB::val("SELECT COUNT(*) FROM (SELECT customer_id FROM bookings WHERE company_id = ? AND status = 'completed' GROUP BY customer_id HAVING COUNT(*) >= 2) t", [$cid]);
        $avg = (int) round((float) DB::val("SELECT COALESCE(AVG(" . self::REVENUE . "),0) FROM bookings b WHERE b.company_id = ? AND b.status = 'completed' AND b.scheduled_date >= ? AND b.scheduled_date <= ?", [$cid, $from, $to]));
        $top = DB::all("SELECT c.id, c.first_name, c.last_name, COUNT(*) AS n, SUM(" . self::REVENUE . ") AS revenue FROM bookings b JOIN customers c ON c.id = b.customer_id
            WHERE b.company_id = ? AND b.status = 'completed' GROUP BY c.id, c.first_name, c.last_name ORDER BY revenue DESC LIMIT 10", [$cid]);
        $cut = date('Y-m-d', strtotime('-90 day'));
        $dormant = DB::all("SELECT c.id, c.first_name, c.last_name, c.phone, MAX(b.scheduled_date) AS last_date, COUNT(*) AS n FROM customers c JOIN bookings b ON b.customer_id = c.id AND b.status = 'completed'
            WHERE c.company_id = ? AND c.deleted_at IS NULL GROUP BY c.id, c.first_name, c.last_name, c.phone HAVING MAX(b.scheduled_date) < ? ORDER BY last_date DESC LIMIT 10", [$cid, $cut]);
        return ['total' => $total, 'new' => $new, 'buyers' => $buyers, 'repeat' => $repeat, 'repeat_rate' => $buyers ? (int) round($repeat / $buyers * 100) : null, 'avg_basket' => $avg, 'top' => $top, 'dormant' => $dormant];
    }
}
