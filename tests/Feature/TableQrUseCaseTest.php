<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Canteen;
use App\Models\DiningTable;
use App\Models\User;
use App\Models\UserCanteenRole;
use App\Modules\Admin\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-22 Kelola Meja & QR Code: QR siap cetak (SVG) dari URL sekali-tampil, regenerasi mencabut
 * QR lama, dan meja dapat dinonaktifkan/diaktifkan kembali.
 */
class TableQrUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private function managerFor(Canteen $canteen): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $user->id, 'canteen_id' => $canteen->id, 'role' => 'manager']);

        return $user;
    }

    public function test_issuing_qr_shows_printable_svg_with_download_link_once(): void
    {
        $canteen = Canteen::factory()->create();
        $table = DiningTable::factory()->create(['canteen_id' => $canteen->id, 'label' => 'Meja 12', 'zone' => 'Zona A']);
        $this->actingAs($this->managerFor($canteen));

        $this->post(route('admin.tables.rotate', $table))->assertRedirect(route('admin.tables.qr', $table));
        $page = $this->followingRedirects()->post(route('admin.tables.rotate', $table));

        $page->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('download="qr-'.$table->code.'.svg"', false)
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee('Meja 12')
            ->assertSee('/q/', false);
        $this->assertStringNotContainsString('/q/'.$table->id.'"', $page->getContent(), 'URL tidak memuat ID meja');

        // Muat ulang: token mentah tidak ditampilkan lagi.
        $this->get(route('admin.tables.qr', $table))->assertOk()->assertDontSee('<svg', false)->assertSee('Tidak ada QR baru');
    }

    public function test_deactivated_table_rejects_scan_until_reactivated(): void
    {
        $canteen = Canteen::factory()->create();
        $table = DiningTable::factory()->create(['canteen_id' => $canteen->id]);
        $plain = app(QrTokenService::class)->issue($table);
        $this->actingAs($this->managerFor($canteen));

        $this->post(route('admin.tables.status', $table), ['status' => 'inactive'])->assertRedirect(route('admin.tables.index'));
        $this->assertSame('inactive', $table->fresh()->status);
        $this->assertTrue(AuditLog::query()->where(['entity' => 'dining_table', 'action' => 'deactivated'])->exists());
        $this->get(route('customer.scan', ['token' => $plain]))->assertNotFound();

        $this->post(route('admin.tables.status', $table), ['status' => 'active']);
        $this->get(route('customer.scan', ['token' => $plain]))->assertRedirect();
    }

    public function test_index_summarises_tables_and_other_canteen_cannot_change_status(): void
    {
        $canteen = Canteen::factory()->create();
        DiningTable::factory()->create(['canteen_id' => $canteen->id, 'status' => 'active']);
        $inactive = DiningTable::factory()->create(['canteen_id' => $canteen->id, 'status' => 'inactive']);
        $this->actingAs($this->managerFor($canteen));

        $this->get(route('admin.tables.index'))->assertOk()->assertSee('2 meja, 1 aktif')->assertSee('Aktifkan kembali');

        $this->actingAs($this->managerFor(Canteen::factory()->create()));
        $this->post(route('admin.tables.status', $inactive), ['status' => 'active'])->assertForbidden();
        $this->post(route('admin.tables.status', $inactive), ['status' => 'deleted'])->assertForbidden();
    }
}
