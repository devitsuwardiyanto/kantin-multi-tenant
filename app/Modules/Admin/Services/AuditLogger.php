<?php

namespace App\Modules\Admin\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Menulis jejak audit append-only untuk aksi administratif. Field sensitif
 * (nomor rekening, password) dibuang dari diff sebelum disimpan.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(string $entity, ?int $entityId, string $action, ?array $before = null, ?array $after = null, ?int $tenantId = null, ?int $canteenId = null): AuditLog
    {
        return AuditLog::create([
            'actor_id' => Auth::id(),
            'canteen_id' => $canteenId,
            'tenant_id' => $tenantId,
            'entity' => $entity,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'action' => $action,
            'request_id' => request()->header('X-Request-Id') ?? (string) Str::uuid(),
            'before' => $before !== null ? $this->sanitize($before) : null,
            'after' => $after !== null ? $this->sanitize($after) : null,
            'logged_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        foreach (['account_number_cipher', 'account_number', 'password', 'password_hash'] as $key) {
            unset($data[$key]);
        }

        return $data;
    }
}
