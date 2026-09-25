<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\ReportExport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Reporting\Exceptions\ReportExportException;
use App\Modules\Reporting\Jobs\GenerateSalesReportExport;
use App\Modules\Reporting\Notifications\ReportExportReady;
use App\Modules\Reporting\Services\ReportExportService;
use App\Support\Tokens\OpaqueToken;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/**
 * UC-17 Ekspor Laporan: tombol Ekspor (.xlsx/PDF) mengantrikan job; berkas berisi kop tenant +
 * data sesuai rentang; tautan unduh bertanda tangan, milik tenant aktif, kedaluwarsa 24 jam;
 * rentang > 12 bulan ditolak; volume besar → surel (alur 2a).
 */
class ReportExportUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        // Job dijalankan langsung (CI memakai QUEUE_CONNECTION=redis tanpa worker).
        config(['queue.default' => 'sync']);
        Storage::fake('local');

        $canteen = Canteen::factory()->create(['name' => 'Kantin Teknik']);
        $this->tenant = Tenant::factory()->create(['canteen_id' => $canteen->id, 'display_name' => 'Warung Bu Rina']);
        CommissionScheme::factory()->create(['tenant_id' => $this->tenant->id, 'commission_rate' => 0.10, 'valid_from' => now()->subYear(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Nasi Ayam Bakar', 'base_price' => 18000, 'stock_qty' => 50]);

        $session = new CustomerSession;
        $session->forceFill(['canteen_id' => $canteen->id, 'session_token_hash' => OpaqueToken::issue(32)['hash'], 'status' => 'active', 'expires_at' => now()->addHours(4)])->save();
        app(CartService::class)->add($session, $menu->id, 2);
        $order = app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
        app(PaymentService::class)->confirmSandbox(app(PaymentService::class)->initiate($order));

        $this->member = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $this->member->id, 'tenant_id' => $this->tenant->id, 'role' => 'owner']);
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    private function request(string $format): ReportExport
    {
        $today = CarbonImmutable::now('Asia/Jakarta');

        return app(ReportExportService::class)->request($this->tenant, $this->member, $format, $today->startOfMonth(), $today);
    }

    public function test_export_button_queues_job_and_shows_progress(): void
    {
        Queue::fake();

        Livewire::actingAs($this->member)
            ->test('reporting::sales-report', ['tenantId' => $this->tenant->id])
            ->call('export', 'xlsx')
            ->assertHasNoErrors()
            ->assertSee('Ekspor XLSX sedang dibuat.')
            ->assertSee('Sedang dibuat…');

        Queue::assertPushed(GenerateSalesReportExport::class);
        $this->assertSame('queued', ReportExport::query()->sole()->status);
    }

    public function test_xlsx_contains_tenant_header_and_filtered_rows(): void
    {
        $export = $this->request('xlsx')->fresh();

        $this->assertSame('ready', $export->status);
        $this->assertTrue($export->expires_at->between(now()->addHours(23), now()->addHours(24)->addMinute()));

        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, Storage::disk('local')->get($export->path));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertStringContainsString('LAPORAN PENJUALAN — Warung Bu Rina', $sheet);
        $this->assertStringContainsString('Kantin Teknik', $sheet);
        $this->assertStringContainsString('2× Nasi Ayam Bakar', $sheet);
        $this->assertStringContainsString('<v>36000</v>', $sheet);
    }

    public function test_pdf_is_generated_with_dompdf(): void
    {
        $export = $this->request('pdf')->fresh();

        $this->assertSame('ready', $export->status);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($export->path));
    }

    public function test_signed_download_link_is_tenant_scoped_and_expires_after_24_hours(): void
    {
        $export = $this->request('xlsx')->fresh();
        $url = app(ReportExportService::class)->downloadUrl($export);

        $this->actingAs($this->member)->get($url)
            ->assertOk()
            ->assertDownload('laporan-penjualan-'.$export->date_from->toDateString().'_'.$export->date_to->toDateString().'.xlsx');

        // Tanpa tanda tangan → ditolak.
        $this->actingAs($this->member)->get(route('tenant.reports.download', ['tenant' => $this->tenant->slug, 'export' => $export->id]))->assertForbidden();

        // Tautan milik tenant lain (walau bertanda tangan sah) → 404.
        $other = Tenant::factory()->create(['canteen_id' => $this->tenant->canteen_id]);
        UserTenantRole::create(['user_id' => $this->member->id, 'tenant_id' => $other->id, 'role' => 'owner']);
        $foreign = URL::temporarySignedRoute('tenant.reports.download', now()->addHour(), ['tenant' => $other->slug, 'export' => $export->id]);
        $this->actingAs($this->member)->get($foreign)->assertNotFound();

        // Setelah 24 jam tanda tangan kedaluwarsa.
        $this->travel(25)->hours();
        $this->actingAs($this->member)->get($url)->assertForbidden();
    }

    public function test_range_longer_than_twelve_months_is_rejected(): void
    {
        $this->expectException(ReportExportException::class);
        $this->expectExceptionMessage('Rentang ekspor maksimal 12 bulan.');

        app(ReportExportService::class)->request($this->tenant, $this->member, 'pdf', CarbonImmutable::parse('2025-01-01'), CarbonImmutable::parse('2026-01-01'));
    }

    public function test_large_volume_export_is_sent_by_mail(): void
    {
        Notification::fake();
        config(['services.report_export.mail_threshold' => 0]);

        Livewire::actingAs($this->member)
            ->test('reporting::sales-report', ['tenantId' => $this->tenant->id])
            ->call('export', 'pdf')
            ->assertSee('tautannya dikirim ke surel Anda');

        Notification::assertSentTo($this->member, ReportExportReady::class);
    }

    public function test_prune_command_deletes_expired_files(): void
    {
        $export = $this->request('xlsx')->fresh();
        $this->travel(25)->hours();

        $this->artisan('reporting:prune-exports')->assertSuccessful();

        Storage::disk('local')->assertMissing($export->path);
        $this->assertSame('expired', $export->fresh()->status);
    }
}
