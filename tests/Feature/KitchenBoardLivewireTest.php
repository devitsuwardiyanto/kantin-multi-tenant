<?php

namespace Tests\Feature;

use App\Events\NewTenantOrderReceived;
use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantOrder;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenBoardLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    /** @return array{0: Tenant, 1: User, 2: Order} */
    private function paidOrderWithMember(): array
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Nasi Goreng', 'base_price' => 20000, 'stock_qty' => 50]);

        $member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $member->id, 'tenant_id' => $tenant->id, 'role' => 'operator']);

        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        app(CartService::class)->add($session, $menu->id, 2);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        $payment = app(PaymentService::class)->initiate($order);
        app(PaymentService::class)->confirmSandbox($payment);

        return [$tenant, $member, $order];
    }

    public function test_non_member_cannot_open_board(): void
    {
        $canteenB = Canteen::factory()->create();
        $tenantB = Tenant::factory()->create(['canteen_id' => $canteenB->id]);
        $outsider = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);

        Livewire::actingAs($outsider)
            ->test('tenant.kitchen-board', ['tenantId' => $tenantB->id])
            ->assertForbidden();
    }

    public function test_member_sees_paid_order_and_advances_status(): void
    {
        [$tenant, $member] = $this->paidOrderWithMember();
        $tenantOrder = TenantOrder::query()->where('tenant_id', $tenant->id)->firstOrFail();

        Livewire::actingAs($member)
            ->test('tenant.kitchen-board', ['tenantId' => $tenant->id])
            ->assertSee('Nasi Goreng')
            ->call('advance', $tenantOrder->id, 'accepted')
            ->assertHasNoErrors();

        $this->assertSame('accepted', $tenantOrder->fresh()->status);
    }

    public function test_new_paid_order_broadcasts_to_tenant_channel(): void
    {
        Event::fake([NewTenantOrderReceived::class]);

        [$tenant] = $this->paidOrderWithMember();

        Event::assertDispatched(NewTenantOrderReceived::class, function (NewTenantOrderReceived $e) use ($tenant): bool {
            return (int) $e->tenantOrder->tenant_id === $tenant->id
                && $e->broadcastOn()[0]->name === 'private-tenant.'.$tenant->id.'.orders';
        });
    }
}
