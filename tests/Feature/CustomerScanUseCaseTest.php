<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Tenant;
use App\Models\TenantOperatingHour;
use App\Modules\Admin\Services\QrTokenService;
use App\Modules\Catalog\Services\TenantOpeningHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * UC-02 Pindai QR Code Meja: sambutan meja + status tenant, token invalid (3a), dan kantin di
 * luar jam operasional (3b).
 */
class CustomerScanUseCaseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Canteen, 1: DiningTable, 2: Tenant, 3: Tenant} */
    private function canteenWithHours(): array
    {
        $canteen = Canteen::factory()->create(['name' => 'Kantin Teknik']);
        $table = DiningTable::factory()->create(['canteen_id' => $canteen->id, 'label' => 'Meja 12', 'zone' => 'Zona A']);
        $rina = Tenant::factory()->create(['canteen_id' => $canteen->id, 'display_name' => 'Warung Bu Rina']);
        $geprek = Tenant::factory()->create(['canteen_id' => $canteen->id, 'display_name' => 'Geprek Juara']);
        foreach (range(0, 6) as $day) {
            (new TenantOperatingHour)->forceFill(['tenant_id' => $rina->id, 'day_of_week' => $day, 'opens_at' => '07:00:00', 'closes_at' => '20:00:00'])->save();
            (new TenantOperatingHour)->forceFill(['tenant_id' => $geprek->id, 'day_of_week' => $day, 'opens_at' => '10:00:00', 'closes_at' => '20:00:00'])->save();
        }

        return [$canteen, $table, $rina, $geprek];
    }

    public function test_scan_during_hours_greets_table_and_lists_tenant_status(): void
    {
        [$canteen, $table] = $this->canteenWithHours();
        $this->travelTo(Carbon::parse('2026-09-24 08:30', 'Asia/Jakarta'));
        $plain = app(QrTokenService::class)->issue($table);

        $scan = $this->get(route('customer.scan', ['token' => $plain]));
        $scan->assertRedirect(route('customer.welcome', ['canteen' => $canteen->slug]));

        $this->withCookie('customer_session', $scan->getCookie('customer_session')->getValue())
            ->get(route('customer.welcome', ['canteen' => $canteen->slug]))
            ->assertOk()
            ->assertSee('Sesi pemesanan aktif')
            ->assertSee('Meja 12')
            ->assertSee('Zona A')
            ->assertSeeInOrder(['Geprek Juara', 'Tutup · Buka pukul 10.00', 'TUTUP', 'Warung Bu Rina', 'Buka · 07.00–20.00'])
            ->assertSee('Lihat semua menu');
    }

    public function test_scan_outside_hours_shows_opening_hours_without_creating_session(): void
    {
        [, $table] = $this->canteenWithHours();
        $this->travelTo(Carbon::parse('2026-09-24 22:00', 'Asia/Jakarta'));
        $plain = app(QrTokenService::class)->issue($table);

        $response = $this->get(route('customer.scan', ['token' => $plain]));

        $response->assertOk()->assertSee('Kantin sedang tutup')->assertSee('07.00–20.00')->assertCookieMissing('customer_session');
        $this->assertSame(0, CustomerSession::query()->count());
    }

    public function test_invalid_token_suggests_contacting_staff_with_generic_404(): void
    {
        $this->get(route('customer.scan', ['token' => 'tidak-ada']))
            ->assertNotFound()
            ->assertSee('hubungi petugas kantin');
    }

    public function test_welcome_without_session_asks_to_scan_and_hides_order_button(): void
    {
        [$canteen] = $this->canteenWithHours();

        $this->get(route('customer.welcome', ['canteen' => $canteen->slug]))
            ->assertOk()->assertSee('Pindai QR meja untuk memulai')->assertDontSee('Lihat semua menu');
    }

    public function test_overnight_hours_and_unconfigured_tenants_count_as_open(): void
    {
        $canteen = Canteen::factory()->create();
        $night = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        (new TenantOperatingHour)->forceFill(['tenant_id' => $night->id, 'day_of_week' => 4, 'opens_at' => '18:00:00', 'closes_at' => '02:00:00'])->save();
        $hours = app(TenantOpeningHours::class);

        $this->assertTrue($hours->isOpen($night, Carbon::parse('2026-09-24 23:30', 'Asia/Jakarta')));
        $this->assertFalse($hours->isOpen($night, Carbon::parse('2026-09-24 12:00', 'Asia/Jakarta')));
        $this->assertTrue($hours->isOpen(Tenant::factory()->create(['canteen_id' => $canteen->id])), 'tanpa jam = buka');
    }
}
