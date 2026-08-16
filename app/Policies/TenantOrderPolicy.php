<?php

namespace App\Policies;

use App\Models\TenantOrder;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class TenantOrderPolicy
{
    private function ownedByActiveTenant(TenantOrder $order): bool
    {
        return $order->tenant_id === app(TenantContext::class)->idOrNull();
    }

    public function view(User $user, TenantOrder $order): bool
    {
        return $this->ownedByActiveTenant($order);
    }

    public function update(User $user, TenantOrder $order): bool
    {
        return $this->ownedByActiveTenant($order);
    }
}
