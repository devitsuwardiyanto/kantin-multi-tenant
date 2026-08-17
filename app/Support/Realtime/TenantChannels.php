<?php

namespace App\Support\Realtime;

use App\Models\User;
use App\Models\UserTenantRole;

/**
 * Sumber tunggal penamaan & otorisasi channel realtime per tenant. Channel order tenant
 * bersifat PRIVAT: hanya anggota tenant (UserTenantRole) yang boleh mendengarkan — mencegah
 * kebocoran antrean dapur lintas tenant.
 */
final class TenantChannels
{
    public static function orders(int $tenantId): string
    {
        return 'tenant.'.$tenantId.'.orders';
    }

    public static function canAccessOrders(User $user, int $tenantId): bool
    {
        return UserTenantRole::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->exists();
    }
}
