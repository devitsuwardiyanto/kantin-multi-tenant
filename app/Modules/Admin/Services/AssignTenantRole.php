<?php

namespace App\Modules\Admin\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use DomainException;

/**
 * Penugasan/pencabutan role tenant. Owner terakhir dilindungi.
 */
final class AssignTenantRole
{
    public function __construct(private AuditLogger $audit) {}

    public function assign(Tenant $tenant, User $user, string $role): UserTenantRole
    {
        $membership = UserTenantRole::firstOrCreate([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => $role,
        ]);

        $this->audit->record('user_tenant_role', $membership->id, 'assigned',
            null, ['user_id' => $user->id, 'role' => $role], $tenant->id, $tenant->canteen_id);

        return $membership;
    }

    public function remove(Tenant $tenant, User $user, string $role): void
    {
        if ($role === 'owner') {
            $owners = UserTenantRole::query()
                ->where('tenant_id', $tenant->id)->where('role', 'owner')->count();
            if ($owners <= 1) {
                throw new DomainException('Tidak boleh menghapus owner terakhir tenant.');
            }
        }

        UserTenantRole::query()
            ->where('user_id', $user->id)->where('tenant_id', $tenant->id)->where('role', $role)
            ->delete();

        $this->audit->record('user_tenant_role', null, 'removed',
            ['user_id' => $user->id, 'role' => $role], null, $tenant->id, $tenant->canteen_id);
    }
}
