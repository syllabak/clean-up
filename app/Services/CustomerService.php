<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Database, Env};

final class CustomerService
{
    /** Retrouve le client par téléphone ou le crée ; met à jour son identité si elle a changé. */
    public function findOrCreate(string $first, string $last, string $phone, ?string $email): int
    {
        $phone = normalize_phone($phone);
        $c = Database::one('SELECT * FROM customers WHERE company_id = ? AND phone = ?', [Env::companyId(), $phone]);
        if ($c) {
            $upd = [];
            if ($c['deleted_at'] !== null) $upd['deleted_at'] = null;
            if ($email && $email !== $c['email']) $upd['email'] = $email;
            if ($upd) Database::update('customers', (int) $c['id'], $upd);
            return (int) $c['id'];
        }
        return Database::insert('customers', ['company_id' => Env::companyId(), 'first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'email' => $email ?: null, 'created_at' => now()]);
    }

    public function addAddress(int $customerId, int $neighborhoodId, string $text, ?string $instructions): int
    {
        $dup = Database::val('SELECT id FROM customer_addresses WHERE customer_id = ? AND neighborhood_id = ? AND address_text = ? AND deleted_at IS NULL', [$customerId, $neighborhoodId, $text]);
        if ($dup) return (int) $dup;
        return Database::insert('customer_addresses', ['customer_id' => $customerId, 'neighborhood_id' => $neighborhoodId, 'address_text' => $text, 'instructions' => $instructions ?: null, 'created_at' => now()]);
    }
}
