<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantOrder;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Modules\Kitchen\Events\NewTenantOrderReceived;
use App\Modules\Kitchen\Events\TenantOrderStatusChanged;
use App\Modules\Kitchen\Services\WaitTimeEstimator;
use App\Modules\Ordering\Data\CheckoutDetails;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Support\Tokens\OpaqueToken;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-15 Proses Antrean Dapur (KDS): kolom Baru/Diproses/Siap, Terima → Diproses, Selesai → Siap
 * Diambil, Diserahkan; pembatalan wajib beralasan (untuk refund pengelola, alur 4a); pesanan baru
 * disiarkan setelah dibayar (pre-order terjadwal baru saat dilepas); estimasi antrean diperbarui;
 * perubahan status memicu notifikasi pelanggan (include UC-12).
 */
class KitchenQueueUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $canteen = Canteen::factory()->create();
        $this->tenant = Tenant::factory()->preOrder()->create(['canteen_id' => $canteen->id, 'display_name' => 'Warung Bu Rina']);
        CommissionScheme::factory()->create(['tenant_id' => $this->tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        Menu::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Nasi Ayam Bakar', 'base_price' => 18000, 'stock_qty' => 50, 'prep_minutes' => 10]);
        $this->member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $this->member->id, 'tenant_id' => $this->tenant->id, 'role' => 'operator']);
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    private function paidOrder(?CarbonInterface $scheduledAt = null, string $note = 'tanpa sambal'): TenantOrder
    {
        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $this->tenant->canteen_id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();
        $menu = Menu::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->firstOrFail();
        app(CartService::class)->add($session, $menu->id, 2, [], $note);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid(), $scheduledAt, new CheckoutDetails($scheduledAt ? 'pickup' : 'dine_in', 'Dewi', '6281234567890'))->order;
        app(PaymentService::class)->confirmSandbox(app(PaymentService::class)->initiate($order));

        return TenantOrder::query()->withoutGlobalScope('tenant')->where('order_id', $order->id)->sole();
    }

    private function board(): Testable
    {
        return Livewire::actingAs($this->member)->test('kitchen::kitchen-board', ['tenantId' => $this->tenant->id]);
    }

    public function test_paid_order_appears_in_new_column_and_moves_through_the_kitchen_flow(): void
    {
        $tenantOrder = $this->paidOrder();

        $board = $this->board()
            ->assertSee('Warung Bu Rina · Dapur')
            ->assertSeeInOrder(['Baru', '#'.$tenantOrder->order->order_number, '2× Nasi Ayam Bakar', '— tanpa sambal', 'Terima →', 'Diproses', 'Siap']);

        $board->call('advance', $tenantOrder->id, 'preparing');
        $tenantOrder->refresh();
        $this->assertSame('preparing', $tenantOrder->status);
        $this->assertNotNull($tenantOrder->accepted_at);
        $board->assertSeeHtml('data-test="timer">⏱')->assertSee('Selesai ✓')->assertDontSee('Terima →');

        $board->call('advance', $tenantOrder->id, 'ready')->assertSee('SIAP DIAMBIL')->assertSee('Diserahkan ✓');
        $this->assertNotNull($tenantOrder->fresh()->ready_at);

        $board->call('advance', $tenantOrder->id, 'completed')->assertDontSee('#'.$tenantOrder->order->order_number);
        $this->assertSame('completed', $tenantOrder->fresh()->status);
    }

    public function test_status_change_is_broadcast_and_customer_is_notified(): void
    {
        $tenantOrder = $this->paidOrder();
        Event::fake([TenantOrderStatusChanged::class]);

        $this->board()->call('advance', $tenantOrder->id, 'preparing');

        Event::assertDispatched(TenantOrderStatusChanged::class, fn (TenantOrderStatusChanged $e): bool => $e->tenantOrder->id === $tenantOrder->id);
    }

    public function test_cancellation_requires_a_reason_and_is_recorded_for_refund(): void
    {
        $tenantOrder = $this->paidOrder();

        $this->board()
            ->call('startCancel', $tenantOrder->id)->assertSee('Alasan pembatalan')
            ->call('confirmCancel')->assertSee('Pilih alasan pembatalan.')
            ->set('cancelReason', 'stok_habis')->call('confirmCancel')->assertHasNoErrors();

        $tenantOrder->refresh();
        $this->assertSame('cancelled', $tenantOrder->status);
        $this->assertSame('stok_habis', $tenantOrder->cancel_reason);
        $this->assertSame('pending', $tenantOrder->refund_status);
        $this->assertTrue(AuditLog::query()->where('action', 'kitchen_cancelled')->exists());
    }

    public function test_new_paid_order_is_broadcast_but_pre_order_waits_until_release(): void
    {
        Event::fake([NewTenantOrderReceived::class]);

        $this->paidOrder();
        Event::assertDispatchedTimes(NewTenantOrderReceived::class, 1);

        $scheduled = $this->paidOrder(now()->addDay()->setTime(12, 0));
        $this->assertSame('scheduled', $scheduled->status);
        Event::assertDispatchedTimes(NewTenantOrderReceived::class, 1);

        $this->travelTo($scheduled->release_at);
        $this->artisan('ordering:release-scheduled')->assertSuccessful();
        Event::assertDispatchedTimes(NewTenantOrderReceived::class, 2);
    }

    public function test_new_order_event_plays_chime_and_accepting_updates_queue_estimate(): void
    {
        $tenantOrder = $this->paidOrder();
        $before = app(WaitTimeEstimator::class)->queueMinutes($this->tenant);

        $this->board()->call('handleNewOrder')->assertDispatched('kitchen-new-order')
            ->call('advance', $tenantOrder->id, 'preparing');

        $this->assertSame(0.0, $before);
        $this->assertSame(5.0, app(WaitTimeEstimator::class)->queueMinutes($this->tenant), 'waktu siap 10 menit ÷ 2 kapasitas paralel');
        $this->assertSame(1, DB::table('notification_deliveries')->where('event_key', 'tenant_order:'.$tenantOrder->id.':preparing')->count());
        $this->assertSame('paid', Order::query()->find($tenantOrder->order_id)->status);
    }
}
