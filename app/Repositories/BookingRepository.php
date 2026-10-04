<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\{Database, Env};

final class BookingRepository
{
    private const SELECT = "SELECT b.*, c.first_name AS customer_first_name, c.last_name AS customer_last_name, c.phone AS customer_phone, c.email AS customer_email,
        s.name AS service_name, s.slug AS service_slug, n.name AS neighborhood_name, n.avg_travel_minutes, cm.name AS commune_name, ci.name AS city_name, rg.name AS region_name,
        t.name AS team_name,
        (SELECT st.name FROM booking_assignments ba JOIN staff st ON st.id = ba.staff_id WHERE ba.booking_id = b.id AND ba.active = 1 ORDER BY ba.id DESC LIMIT 1) AS staff_name,
        (SELECT ba.staff_id FROM booking_assignments ba WHERE ba.booking_id = b.id AND ba.active = 1 ORDER BY ba.id DESC LIMIT 1) AS staff_id
        FROM bookings b
        JOIN customers c ON c.id = b.customer_id JOIN services s ON s.id = b.service_id
        JOIN neighborhoods n ON n.id = b.neighborhood_id JOIN communes cm ON cm.id = n.commune_id
        JOIN cities ci ON ci.id = cm.city_id JOIN regions rg ON rg.id = ci.region_id
        LEFT JOIN teams t ON t.id = b.team_id";

    public function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE b.id = ? AND b.company_id = ?', [$id, Env::companyId()]);
    }

    public function findByReference(string $ref): ?array
    {
        return Database::one(self::SELECT . ' WHERE b.reference = ? AND b.company_id = ?', [$ref, Env::companyId()]);
    }

    /** @param array $f filtres : status, team_id, staff_id, neighborhood_id, service_id, from, to, q */
    public function search(array $f, int $limit = 200, int $offset = 0): array
    {
        [$where, $p] = $this->where($f);
        return Database::all(self::SELECT . " WHERE $where ORDER BY b.scheduled_date DESC, b.start_time DESC LIMIT $limit OFFSET $offset", $p);
    }

    public function count(array $f): int
    {
        [$where, $p] = $this->where($f);
        return (int) Database::val("SELECT COUNT(*) FROM bookings b JOIN customers c ON c.id = b.customer_id WHERE $where", $p);
    }

    /** Planning : par ordre chronologique. */
    public function between(string $from, string $to, array $f = []): array
    {
        $f['from'] = $from; $f['to'] = $to;
        [$where, $p] = $this->where($f);
        return Database::all(self::SELECT . " WHERE $where ORDER BY b.scheduled_date, b.start_time", $p);
    }

    public function items(int $bookingId): array { return Database::all('SELECT * FROM booking_items WHERE booking_id = ? ORDER BY id', [$bookingId]); }
    public function history(int $bookingId): array
    {
        return Database::all('SELECT h.*, u.name AS user_name FROM booking_status_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.booking_id = ? ORDER BY h.id', [$bookingId]);
    }
    public function notes(int $bookingId): array
    {
        return Database::all('SELECT n.*, u.name AS user_name FROM booking_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.booking_id = ? ORDER BY n.id DESC', [$bookingId]);
    }
    public function photos(int $bookingId): array { return Database::all('SELECT * FROM booking_photos WHERE booking_id = ? ORDER BY id', [$bookingId]); }

    private function where(array $f): array
    {
        $w = ['b.company_id = ?']; $p = [Env::companyId()];
        foreach (['status', 'team_id', 'neighborhood_id', 'service_id'] as $k) {
            if (!empty($f[$k])) { $w[] = "b.$k = ?"; $p[] = $f[$k]; }
        }
        if (!empty($f['staff_id'])) { $w[] = 'EXISTS (SELECT 1 FROM booking_assignments ba WHERE ba.booking_id = b.id AND ba.active = 1 AND ba.staff_id = ?)'; $p[] = (int) $f['staff_id']; }
        if (!empty($f['from'])) { $w[] = 'b.scheduled_date >= ?'; $p[] = $f['from']; }
        if (!empty($f['to'])) { $w[] = 'b.scheduled_date <= ?'; $p[] = $f['to']; }
        if (!empty($f['q'])) {
            $like = '%' . $f['q'] . '%';
            $w[] = '(b.reference LIKE ? OR c.last_name LIKE ? OR c.first_name LIKE ? OR c.phone LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        return [implode(' AND ', $w), $p];
    }
}
