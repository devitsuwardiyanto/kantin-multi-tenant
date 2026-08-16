<?php

namespace App\Jobs;

use App\Models\Menu;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Contoh job tenant-scoped: membawa tenant_id (bukan model context statis), membentuk
 * context di handle(), lalu MEMBERSIHKANNYA agar worker tidak membawa tenant sebelumnya.
 */
class CountTenantMenus implements ShouldQueue
{
    use Queueable;

    public ?int $result = null;

    public function __construct(public int $tenantId) {}

    public function handle(TenantContext $context): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $context->set($tenant);

        try {
            // Query memakai global scope tenant aktif.
            $this->result = Menu::query()->count();
        } finally {
            $context->clear();
        }
    }
}
