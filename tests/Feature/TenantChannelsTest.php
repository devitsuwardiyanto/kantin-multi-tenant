<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Support\Realtime\TenantChannels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_tenant_members_can_access_orders_channel(): void
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $member->id, 'tenant_id' => $tenant->id, 'role' => 'operator']);
        $outsider = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);

        $this->assertSame('tenant.'.$tenant->id.'.orders', TenantChannels::orders($tenant->id));
        $this->assertTrue(TenantChannels::canAccessOrders($member, $tenant->id));
        $this->assertFalse(TenantChannels::canAccessOrders($outsider, $tenant->id), 'non-anggota tak boleh mendengarkan antrean tenant');
    }
}
