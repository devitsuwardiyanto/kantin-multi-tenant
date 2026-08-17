<?php

namespace Tests\Feature;

use App\Events\TenantOrderStatusChanged;
use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\TenantOrder;
use App\Modules\Kitchen\Exceptions\KitchenException;
use App\Modules\Kitchen\Services\KitchenService;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class KitchenServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    private function tenantOrder(): TenantOrder
    {
        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => 15000, 'stock_qty' => 50]);

        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        app(CartService::class)->add($session, $menu->id, 1);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;

        return $order->tenantOrders()->firstOrFail();
    }

    public function test_valid_transitions_advance_and_broadcast(): void
    {
        $tenantOrder = $this->tenantOrder();
        Event::fake([TenantOrderStatusChanged::class]);
        $service = app(KitchenService::class);

        foreach (['accepted', 'preparing', 'ready', 'completed'] as $target) {
            $service->advance($tenantOrder, $target);
            $this->assertSame($target, $tenantOrder->fresh()->status);
        }

        Event::assertDispatched(TenantOrderStatusChanged::class, function (TenantOrderStatusChanged $e) use ($tenantOrder): bool {
            return $e->tenantOrder->id === $tenantOrder->id
                && $e->broadcastOn()[0]->name === 'private-tenant.'.$tenantOrder->tenant_id.'.orders';
        });
        Event::assertDispatchedTimes(TenantOrderStatusChanged::class, 4);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $tenantOrder = $this->tenantOrder(); // pending

        $this->expectException(KitchenException::class);
        app(KitchenService::class)->advance($tenantOrder, 'ready'); // pending -> ready tak sah
    }

    public function test_terminal_state_has_no_transitions(): void
    {
        $this->assertSame([], KitchenService::nextStates('completed'));
        $this->assertSame([], KitchenService::nextStates('cancelled'));
        $this->assertSame(['accepted', 'cancelled'], KitchenService::nextStates('pending'));
    }
}
