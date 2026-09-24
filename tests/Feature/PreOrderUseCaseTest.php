<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantOperatingHour;
use App\Models\TenantOrder;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Modules\Ordering\Data\CheckoutDetails;
use App\Modules\Ordering\Exceptions\PreOrderException;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\Services\PreOrderScheduler;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use App\Support\Tokens\OpaqueToken;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-06 Jadwalkan Pre-Order: slot 15 menit ≥ 15 menit dari sekarang dalam jam operasional,
 * tenant wajib mengaktifkan pre-order, slot penuh/di luar jam ditolak dengan slot terdekat
 * (alur 2a), scheduled_at + status "scheduled", pelepasan ke dapur pada release_at hanya untuk
 * pesanan yang sudah dibayar.
 */
class PreOrderUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Canteen $canteen;

    private Tenant $tenant;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        // Sabtu 18 Juli 2026, 11.20 WIB.
        $this->travelTo(CarbonImmutable::parse('2026-07-18 11:20', 'Asia/Jakarta'));

        $this->canteen = Canteen::factory()->create();
        $this->tenant = Tenant::factory()->preOrder(slotCapacity: 1)->create(['canteen_id' => $this->canteen->id, 'display_name' => 'Warung Bu Rina']);
        CommissionScheme::factory()->create(['tenant_id' => $this->tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        foreach (range(0, 6) as $day) {
            (new TenantOperatingHour)->forceFill([
                'tenant_id' => $this->tenant->id, 'day_of_week' => $day, 'opens_at' => '10:00:00', 'closes_at' => '21:00:00',
            ])->save();
        }
        $this->menu = Menu::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 18000, 'stock_qty' => 20, 'prep_minutes' => 12]);
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    private function sessionWithCart(): CustomerSession
    {
        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $this->canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();
        app(CartService::class)->add($session, $this->menu->id, 1);

        return $session;
    }

    private function bindSession(CustomerSession $session): void
    {
        $this->app->instance(ResolveCustomerSession::class, new class($session) extends ResolveCustomerSession
        {
            public function __construct(private CustomerSession $stub) {}

            public function current(Request $request): ?CustomerSession
            {
                return $this->stub;
            }
        });
    }

    private function wib(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-07-18 '.$time, 'Asia/Jakarta');
    }

    private function preOrder(CustomerSession $session, CarbonImmutable $at): Order
    {
        return app(CheckoutService::class)->checkout($session, (string) Str::uuid(), $at, new CheckoutDetails('pickup', 'Dewi', '6281234567890'))->order;
    }

    /** @param list<CarbonImmutable> $slots */
    private function labels(array $slots): array
    {
        return array_map(fn (CarbonImmutable $slot): string => $slot->setTimezone('Asia/Jakarta')->format('H.i'), $slots);
    }

    public function test_slots_follow_operating_hours_and_minimum_lead_time(): void
    {
        $slots = collect(app(PreOrderScheduler::class)->slots(collect([$this->tenant]), $this->wib('00:00')))->keyBy('label');

        $this->assertSame('10.00', $slots->keys()->first());
        $this->assertSame('20.45', $slots->keys()->last(), 'slot terakhir sebelum tutup 21.00');
        $this->assertSame('past', $slots['11.30']['state'], '11.30 < 11.20 + 15 menit');
        $this->assertSame('available', $slots['11.45']['state']);
    }

    public function test_customer_schedules_pickup_and_order_is_held_as_scheduled(): void
    {
        $session = $this->sessionWithCart();
        $this->bindSession($session);

        Livewire::test('ordering::checkout', ['canteenSlug' => $this->canteen->slug])
            ->call('chooseMode', 'pickup')
            ->assertSee('Hari ini · Sab 18 Jul')
            ->call('chooseSlot', $this->wib('12:00')->toIso8601String())
            ->assertSee('Mulai dimasak otomatis')
            ->assertSee('± 11.48')
            ->set('customerName', 'Dewi Lestari')
            ->set('whatsapp', '081234567890')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertRedirect(route('customer.order.show', ['canteen' => $this->canteen->slug], false));

        $order = Order::query()->sole();
        $tenantOrder = $order->tenantOrders()->sole();
        $this->assertSame('pickup', $order->service_mode);
        $this->assertSame('scheduled', $tenantOrder->status);
        $this->assertTrue($tenantOrder->scheduled_at->equalTo($this->wib('12:00')));
        $this->assertTrue($tenantOrder->release_at->equalTo($this->wib('11:48')), 'waktu ambil − 12 menit penyiapan');
    }

    public function test_time_too_soon_or_outside_hours_is_rejected_with_nearest_slots(): void
    {
        $session = $this->sessionWithCart();

        try {
            $this->preOrder($session, $this->wib('11:30'));
            $this->fail('kurang dari 15 menit harus ditolak');
        } catch (PreOrderException $e) {
            $this->assertSame('Waktu ambil minimal 15 menit dari sekarang.', $e->getMessage());
            $this->assertSame(['11.45', '12.00'], $this->labels($e->alternatives));
        }

        try {
            $this->preOrder($session, $this->wib('21:30'));
            $this->fail('di luar jam operasional harus ditolak');
        } catch (PreOrderException $e) {
            $this->assertSame('Waktu 21.30 di luar jam operasional tenant.', $e->getMessage());
            $this->assertSame(['20.30', '20.45'], $this->labels($e->alternatives));
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_full_slot_is_rejected_and_nearest_available_slots_are_offered(): void
    {
        $this->preOrder($this->sessionWithCart(), $this->wib('12:15'));
        $session = $this->sessionWithCart();
        $this->bindSession($session);

        $slots = collect(app(PreOrderScheduler::class)->slots(collect([$this->tenant]), $this->wib('00:00')))->keyBy('label');
        $this->assertSame('full', $slots['12.15']['state']);

        Livewire::test('ordering::checkout', ['canteenSlug' => $this->canteen->slug])
            ->call('chooseMode', 'pickup')
            ->assertSee('PENUH')
            ->set('pickupSlot', $this->wib('12:15')->toIso8601String())
            ->set('customerName', 'Budi')
            ->set('whatsapp', '081298765432')
            ->call('confirm')
            ->assertHasErrors('pickupSlot')
            ->assertSee('Slot 12.15 penuh untuk Warung Bu Rina.')
            ->assertSet('alternatives', [$this->wib('12:00')->toIso8601String(), $this->wib('12:30')->toIso8601String()]);

        $this->assertSame(1, Order::query()->count());
    }

    public function test_pickup_requires_tenant_to_enable_pre_order(): void
    {
        $this->tenant->forceFill(['pre_order_enabled' => false])->save();
        $session = $this->sessionWithCart();
        $this->bindSession($session);

        Livewire::test('ordering::checkout', ['canteenSlug' => $this->canteen->slug])
            ->assertSee('Belum didukung semua tenant')
            ->call('chooseMode', 'pickup')
            ->assertSet('serviceMode', 'dine_in');

        $this->expectException(PreOrderException::class);
        $this->expectExceptionMessage('Warung Bu Rina belum menerima pre-order.');
        $this->preOrder($session, $this->wib('12:00'));
    }

    public function test_scheduler_releases_only_paid_orders_when_release_time_arrives(): void
    {
        $paid = $this->preOrder($this->sessionWithCart(), $this->wib('12:00'));
        $unpaid = $this->preOrder($this->sessionWithCart(), $this->wib('12:30'));
        $paid->forceFill(['status' => 'paid'])->save();

        $this->travelTo($this->wib('11:47'));
        $this->artisan('ordering:release-scheduled')->assertSuccessful();
        $this->assertSame('scheduled', $paid->tenantOrders()->sole()->status, 'belum waktunya (release 11.48)');

        $this->travelTo($this->wib('11:48'));
        $this->artisan('ordering:release-scheduled')->expectsOutputToContain('1 pesanan terjadwal dilepas')->assertSuccessful();
        $this->assertSame('pending', $paid->tenantOrders()->sole()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'pre_order_released')->exists());

        $this->travelTo($this->wib('12:30'));
        $this->artisan('ordering:release-scheduled')->assertSuccessful();
        $this->assertSame('scheduled', TenantOrder::query()->withoutGlobalScope('tenant')->where('order_id', $unpaid->id)->value('status'), 'belum dibayar tetap ditahan');
    }

    public function test_tenant_member_enables_pre_order_and_sets_slot_capacity(): void
    {
        $user = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $user->id, 'tenant_id' => $this->tenant->id, 'role' => 'operator']);
        $this->tenant->forceFill(['pre_order_enabled' => false])->save();

        $this->actingAs($user)->get(route('tenant.pre-order-settings', $this->tenant))->assertOk()->assertSee('Terima pre-order');

        Livewire::actingAs($user)->test('ordering::pre-order-settings', ['tenantId' => $this->tenant->id])
            ->set('enabled', true)->set('slotCapacity', 3)
            ->call('save')->assertHasNoErrors()->assertSee('Tersimpan.');

        $this->tenant->refresh();
        $this->assertTrue($this->tenant->pre_order_enabled);
        $this->assertSame(3, $this->tenant->pre_order_slot_capacity);

        $outsider = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        Livewire::actingAs($outsider)->test('ordering::pre-order-settings', ['tenantId' => $this->tenant->id])->assertForbidden();
    }
}
