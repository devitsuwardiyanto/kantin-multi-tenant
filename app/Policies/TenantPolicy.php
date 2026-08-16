<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserCanteenRole;

/**
 * Keputusan administratif dipusatkan di policy (bukan string role tersebar).
 * Aktor harus mengelola canteen milik tenant terkait.
 */
class TenantPolicy
{
    private function managesCanteen(User $user, int $canteenId): bool
    {
        return UserCanteenRole::query()
            ->where('user_id', $user->id)
            ->where('canteen_id', $canteenId)
            ->whereIn('role', ['owner', 'manager', 'finance'])
            ->exists();
    }

    public function viewAny(User $user): bool
    {
        return UserCanteenRole::query()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return UserCanteenRole::query()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'manager'])
            ->exists();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->managesCanteen($user, $tenant->canteen_id);
    }

    public function scheduleCommission(User $user, Tenant $tenant): bool
    {
        return $this->managesCanteen($user, $tenant->canteen_id);
    }

    public function manageBank(User $user, Tenant $tenant): bool
    {
        return $this->managesCanteen($user, $tenant->canteen_id);
    }

    public function assignRole(User $user, Tenant $tenant): bool
    {
        return $this->managesCanteen($user, $tenant->canteen_id);
    }
}
