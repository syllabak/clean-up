<?php
declare(strict_types=1);
namespace App\Core;

final class Audit
{
    public static function log(string $action, string $entity = '', ?int $entityId = null, array $data = [], ?int $userId = null): void
    {
        try {
            Database::insert('audit_logs', [
                'company_id' => Env::companyId(),
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
                'ip' => PHP_SAPI === 'cli' ? 'cli' : Request::ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            error_log('Audit: ' . $e->getMessage());
        }
    }
}
