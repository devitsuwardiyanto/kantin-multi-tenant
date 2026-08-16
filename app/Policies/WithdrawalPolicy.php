<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Tenancy\TenantContext;

class WithdrawalPolicy
{
    private function ownedByActiveTenant(Withdrawal $withdrawal): bool
    {
        return $withdrawal->tenant_id === app(TenantContext::class)->idOrNull();
    }

    public function view(User $user, Withdrawal $withdrawal): bool
    {
        return $this->ownedByActiveTenant($withdrawal);
    }

    public function update(User $user, Withdrawal $withdrawal): bool
    {
        return $this->ownedByActiveTenant($withdrawal);
    }
}
