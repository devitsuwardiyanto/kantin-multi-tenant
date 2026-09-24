<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\TenantOperatingHour;
use App\Modules\Ordering\Data\CheckoutDetails;
use App\Modules\Ordering\Exceptions\CheckoutException;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use App\Support\Tokens\OpaqueToken;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-05 Checkout Pesanan: ringkasan + nomor meja + mode penyajian + identitas ringkas (nama,
 * WhatsApp), order induk "menunggu pembayaran" dengan tenant_orders & snapshot (termasuk catatan
 * item), batal & kembali ke keranjang bila item tidak tersedia (alur 4a), tenant wajib buka.
 */
class CheckoutUseCaseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Canteen, 1: Menu, 2: Menu, 3: CustomerSession} */
    private function scenario(): array
    {
        $canteen = Canteen::factory()->create(['tax_rate' => 0.1000, 'service_fee_rate' => 0.0200]);
        $warung = $this->tenant($canteen, 'Warung Bu Rina');
        $kopi = $this->tenant($canteen, 'Kopi Serambi');
        $ayam = Menu::factory()->create(['tenant_id' => $warung->id, 'name' => 'Nasi Ayam', 'base_price' => 18000, 'stock_qty' => 10]);
        $esKopi = Menu::factory()->create(['tenant_id' => $kopi->id, 'name' => 'Es Kopi', 'base_price' => 18000, 'stock_qty' => 10]);

        $table = DiningTable::factory()->create(['canteen_id' => $canteen->id, 'label' => 'Meja 12']);
        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'dining_table_id' => $table->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        return [$canteen, $ayam, $esKopi, $session];
    }

    private function tenant(Canteen $canteen, string $name): Tenant
    {
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id, 'display_name' => $name]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);

        return $tenant;
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

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    public function test_checkout_page_shows_summary_table_service_mode_and_identity_fields(): void
    {
        [$canteen, $ayam, $esKopi, $session] = $this->scenario();
        app(CartService::class)->add($session, $ayam->id, 1);
        app(CartService::class)->add($session, $esKopi->id, 2);
        $this->bindSession($session);

        Livewire::test('ordering::checkout', ['canteenSlug' => $canteen->slug])
            ->assertSee('3 item · 2 tenant')
            ->assertSee('Makan di tempat')
            ->assertSee('Diantar ke Meja 12')
            ->assertSee('Pesan dulu / Pick-up')
            ->assertSee('Nomor WhatsApp')
            ->assertSeeInOrder(['Warung Bu Rina · 1 item', 'Rp18.000', 'Kopi Serambi · 2 item', 'Rp36.000'])
            ->assertSeeInOrder(['Pajak + layanan', 'Rp6.480', 'Total', 'Rp60.480']);
    }

    public function test_confirm_creates_order_awaiting_payment_with_snapshots_identity_and_note(): void
    {
        [$canteen, $ayam, $esKopi, $session] = $this->scenario();
        app(CartService::class)->add($session, $ayam->id, 1, [], 'Tanpa sambal');
        app(CartService::class)->add($session, $esKopi->id, 2);
        $this->bindSession($session);

        Livewire::test('ordering::checkout', ['canteenSlug' => $canteen->slug])
            ->set('customerName', 'Dewi Lestari')
            ->set('whatsapp', '0812-3456-7890')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertRedirect(route('customer.order.show', ['canteen' => $canteen->slug], false));

        $order = Order::query()->with('tenantOrders')->sole();
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame('dine_in', $order->service_mode);
        $this->assertSame('Dewi Lestari', $order->customer_snapshot['name']);
        $this->assertSame('6281234567890', $order->customer_snapshot['whatsapp']);
        $this->assertSame('Meja 12', $order->table_snapshot['label']);
        $this->assertSame(60480, $order->grand_total_amount);
        $this->assertCount(2, $order->tenantOrders);
        $this->assertSame(['pending', 'pending'], $order->tenantOrders->pluck('status')->all());
        $this->assertSame('Tanpa sambal', OrderItem::query()->withoutGlobalScope('tenant')->where('menu_id', $ayam->id)->value('note'));
        $this->assertSame(0, Redis::exists('cart:'.$session->id));
    }

    public function test_identity_is_required_and_whatsapp_must_be_valid(): void
    {
        [$canteen, $ayam, , $session] = $this->scenario();
        app(CartService::class)->add($session, $ayam->id, 1);
        $this->bindSession($session);

        Livewire::test('ordering::checkout', ['canteenSlug' => $canteen->slug])
            ->call('confirm')
            ->assertHasErrors(['customerName' => 'required', 'whatsapp' => 'required'])
            ->set('customerName', 'Dewi')
            ->set('whatsapp', '12345')
            ->call('confirm')
            ->assertHasErrors(['whatsapp' => 'regex']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_unavailable_item_cancels_checkout_and_returns_to_cart(): void
    {
        [$canteen, $ayam, $esKopi, $session] = $this->scenario();
        app(CartService::class)->add($session, $ayam->id, 1);
        app(CartService::class)->add($session, $esKopi->id, 1);
        $this->bindSession($session);

        $component = Livewire::test('ordering::checkout', ['canteenSlug' => $canteen->slug])
            ->set('customerName', 'Dewi')
            ->set('whatsapp', '081234567890');

        // Menu habis setelah halaman checkout dibuka (alur 4a).
        Menu::query()->withoutGlobalScope('tenant')->whereKey($esKopi->id)->update(['is_available' => false]);

        $component->call('confirm')
            ->assertRedirect(route('customer.home', ['canteen' => $canteen->slug], false));

        $this->assertSame(0, Order::query()->count(), 'tidak ada pesanan parsial');
        $this->assertSame(10, $ayam->refresh()->stock_qty, 'stok tidak terpotong');
        $this->assertSame('Ada item yang tidak dapat dipesan. Perbarui keranjang lalu coba lagi.', session('checkout_error'));

        Livewire::test('ordering::cart', ['canteenSlug' => $canteen->slug])
            ->assertSee('Menu telah habis.');
    }

    public function test_dine_in_checkout_rejected_while_a_tenant_is_closed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-18 09:00', 'Asia/Jakarta'));
        [, $ayam, , $session] = $this->scenario();
        foreach (range(0, 6) as $day) {
            (new TenantOperatingHour)->forceFill([
                'tenant_id' => $ayam->tenant_id, 'day_of_week' => $day, 'opens_at' => '10:00:00', 'closes_at' => '21:00:00',
            ])->save();
        }
        app(CartService::class)->add($session, $ayam->id, 1);

        try {
            app(CheckoutService::class)->checkout($session, 'uc05-closed', null, new CheckoutDetails('dine_in', 'Dewi', '6281234567890'));
            $this->fail('checkout harus ditolak saat tenant tutup');
        } catch (CheckoutException $e) {
            $this->assertStringContainsString('Warung Bu Rina sedang tutup', $e->getMessage());
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_page_redirects_back_when_cart_is_empty(): void
    {
        [$canteen, , , $session] = $this->scenario();
        $this->bindSession($session);

        Livewire::test('ordering::checkout', ['canteenSlug' => $canteen->slug])
            ->assertRedirect(route('customer.home', ['canteen' => $canteen->slug], false));
    }
}
