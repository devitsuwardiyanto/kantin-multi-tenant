<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Policy = lapisan kemampuan. Binding membatasi objek; policy membatasi aksi.
 * Defense-in-depth: menu harus milik tenant aktif (selain global scope + composite FK).
 */
class MenuPolicy
{
    private function ownedByActiveTenant(Menu $menu): bool
    {
        return $menu->tenant_id === app(TenantContext::class)->idOrNull();
    }

    public function view(User $user, Menu $menu): bool
    {
        return $this->ownedByActiveTenant($menu);
    }

    public function update(User $user, Menu $menu): bool
    {
        return $this->ownedByActiveTenant($menu);
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $this->ownedByActiveTenant($menu);
    }
}
