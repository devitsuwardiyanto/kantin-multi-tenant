<?php

namespace App\Policies;

use App\Models\DiningTable;
use App\Models\User;
use App\Models\UserCanteenRole;

class DiningTablePolicy
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

    public function update(User $user, DiningTable $table): bool
    {
        return $this->managesCanteen($user, $table->canteen_id);
    }

    public function rotateQr(User $user, DiningTable $table): bool
    {
        return $this->managesCanteen($user, $table->canteen_id);
    }
}
