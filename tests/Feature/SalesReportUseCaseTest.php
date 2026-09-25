<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Reporting\Services\TenantSalesReport;
use App\Support\Tokens\OpaqueToken;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-16 Lihat Laporan Penjualan: omset, jumlah & rata-rata transaksi, menu terlaris, distribusi
 * per jam (WIB) untuk SATU tenant pada rentang tanggal; pesanan belum lunas, dibatalkan, dan
 * milik tenant lain tidak dihitung; rentang kosong → keadaan kosong + saran rentang (2a).
 */
class SalesReportUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Canteen $canteen;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canteen = Canteen::factory()->create();
        $this->tenant = Tenant::factory()->create(['canteen_id' => $this->canteen->id, 'display_name' => 'Warung Bu Rina']);
        CommissionScheme::factory()->create(['tenant_id' => $this->tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subYear(), 'valid_to' => null]);
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    /** Pesanan lunas dengan waktu bayar tertentu (WIB). */
    private function sale(Tenant $tenant, string $menuName, int $price, int $qty, string $paidAtWib, bool $paid = true): Order
    {
        $menu = Menu::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('name', $menuName)->first()
            ?? Menu::factory()->create(['tenant_id' => $tenant->id, 'name' => $menuName, 'base_price' => $price, 'stock_qty' => 500]);

        $session = new CustomerSession;
        $session->forceFill(['canteen_id' => $tenant->canteen_id, 'session_token_hash' => OpaqueToken::issue(32)['hash'], 'status' => 'active', 'expires_at' => now()->addHours(4)])->save();
        app(CartService::class)->add($session, $menu->id, $qty);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        $payment = app(PaymentService::class)->initiate($order);

        if ($paid) {
            app(PaymentService::class)->confirmSandbox($payment);
            $utc = CarbonImmutable::parse($paidAtWib, 'Asia/Jakarta')->utc();
            DB::table('payments')->where('order_id', $order->id)->update(['settled_at' => $utc->format('Y-m-d H:i:s')]);
        }

        return $order;
    }

    private function report(string $from, string $to): array
    {
        return app(TenantSalesReport::class)->summary($this->tenant->id, CarbonImmutable::parse($from), CarbonImmutable::parse($to));
    }

    public function test_report_aggregates_revenue_transactions_average_and_median_for_the_tenant_only(): void
    {
        $this->sale($this->tenant, 'Nasi Ayam Bakar', 18000, 1, '2026-07-01 11:15');
        $this->sale($this->tenant, 'Nasi Ayam Bakar', 18000, 2, '2026-07-02 12:05');
        $this->sale($this->tenant, 'Es Teh Manis', 5000, 1, '2026-07-03 12:40');
        $this->sale($this->tenant, 'Es Teh Manis', 5000, 1, '2026-07-03 13:10', paid: false); // belum lunas

        $other = Tenant::factory()->create(['canteen_id' => $this->canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $other->id, 'commission_rate' => 0.10, 'valid_from' => now()->subYear(), 'valid_to' => null]);
        $this->sale($other, 'Bakso', 20000, 3, '2026-07-02 12:00');

        $report = $this->report('2026-07-01', '2026-07-18');

        $this->assertSame(59000, $report['revenue']);
        $this->assertSame(3, $report['transactions']);
        $this->assertSame(19666, $report['average']);
        $this->assertSame(18000, $report['median']);
        $this->assertSame(18, $report['days']);
        $this->assertSame([['name' => 'Nasi Ayam Bakar', 'quantity' => 3], ['name' => 'Es Teh Manis', 'quantity' => 1]], $report['top_menus']);
    }

    public function test_hourly_distribution_uses_wib_and_marks_busy_window(): void
    {
        $this->sale($this->tenant, 'Nasi Ayam Bakar', 18000, 1, '2026-07-01 07:30');
        foreach (['11:05', '11:40', '12:10', '12:20', '13:05'] as $time) {
            $this->sale($this->tenant, 'Nasi Ayam Bakar', 18000, 1, '2026-07-01 '.$time);
        }
        $this->sale($this->tenant, 'Nasi Ayam Bakar', 18000, 1, '2026-07-01 16:45');

        $report = $this->report('2026-07-01', '2026-07-01');

        $this->assertSame([7 => 1, 8 => 0, 9 => 0, 10 => 0, 11 => 2, 12 => 2, 13 => 1, 14 => 0, 15 => 0, 16 => 1], $report['hourly']);
        $this->assertSame(['from' => 11, 'to' => 13, 'share' => 71], $report['busy']);
    }

    public function test_cancelled_tenant_orders_are_excluded_and_previous_period_is_compared(): void
    {
        $this->sale($this->tenant, 'Nasi Ayam Bakar', 20000, 1, '2026-06-20 12:00'); // periode lalu
        $cancelled = $this->sale($this->tenant, 'Nasi Ayam Bakar', 20000, 1, '2026-07-05 12:00');
        DB::table('tenant_orders')->where('order_id', $cancelled->id)->update(['status' => 'cancelled']);
        $this->sale($this->tenant, 'Nasi Ayam Bakar', 20000, 2, '2026-07-06 12:00');

        $report = $this->report('2026-07-01', '2026-07-18');

        $this->assertSame(40000, $report['revenue']);
        $this->assertSame(1, $report['transactions']);
        $this->assertSame(100.0, $report['change_percent']); // 40.000 vs 20.000 (18 hari sebelumnya)
    }

    public function test_empty_range_shows_empty_state_with_suggested_month(): void
    {
        $this->sale($this->tenant, 'Nasi Ayam Bakar', 18000, 1, '2026-05-14 12:00');
        $member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $member->id, 'tenant_id' => $this->tenant->id, 'role' => 'owner']);

        $this->assertSame('2026-05-14', $this->report('2026-07-01', '2026-07-18')['latest_sale_date']);

        Livewire::actingAs($member)
            ->test('reporting::sales-report', ['tenantId' => $this->tenant->id])
            ->set('from', '2026-07-01')
            ->set('to', '2026-07-18')
            ->assertSee('Belum ada penjualan pada rentang ini.')
            ->assertSee('Lihat Mei 2026')
            ->call('useRange', '2026-05-01', '2026-05-31')
            ->assertSee('Rp18.000')
            ->assertSee('Nasi Ayam Bakar');
    }

    public function test_page_renders_for_member_and_is_forbidden_for_outsider(): void
    {
        $member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $member->id, 'tenant_id' => $this->tenant->id, 'role' => 'owner']);

        $this->actingAs($member)->get(route('tenant.reports', ['tenant' => $this->tenant->slug]))
            ->assertOk()
            ->assertSeeLivewire('reporting::sales-report')
            ->assertSee('Ekspor PDF');

        $outsider = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        Livewire::actingAs($outsider)->test('reporting::sales-report', ['tenantId' => $this->tenant->id])->assertForbidden();
    }

    public function test_invalid_range_is_rejected(): void
    {
        $member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $member->id, 'tenant_id' => $this->tenant->id, 'role' => 'owner']);

        Livewire::actingAs($member)
            ->test('reporting::sales-report', ['tenantId' => $this->tenant->id])
            ->set('from', '2026-07-18')
            ->set('to', '2026-07-01')
            ->assertSee('Rentang tanggal tidak valid.');
    }
}
