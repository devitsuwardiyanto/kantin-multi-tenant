<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\Tenant;
use App\Models\TenantBankAccount;
use App\Models\User;
use App\Models\UserCanteenRole;
use App\Models\UserTenantRole;
use App\Models\Withdrawal;
use App\Modules\Payments\Services\WithdrawalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Temuan audit Pertemuan 14 — kebutuhan mutu UC-20 dan UC-23: “uji konkurensi wajib membuktikan
 * dua permintaan bersamaan tidak dapat membuat saldo negatif atau hold ganda” dan “aman terhadap
 * dua persetujuan yang terjadi bersamaan”. Dua PROSES PHP terpisah menjalankan layanan pada
 * saat yang sama terhadap MariaDB nyata; data di-commit (bukan transaksi test) agar kedua
 * proses saling melihat dan saling menunggu kunci baris. Tabel dibersihkan sebelum & sesudah.
 */
class WithdrawalConcurrencyTest extends TestCase
{
    private Tenant $tenant;

    private TenantBankAccount $account;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ledger_entries')) {
            $this->artisan('migrate');
        }
        $this->cleanDatabase();
        config(['services.withdrawal.minimum' => 100000]);

        $canteen = Canteen::factory()->create();
        $this->tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        $this->owner = User::factory()->create(['role' => 'tenant', 'status' => 'active', 'email_verified_at' => now()]);
        UserTenantRole::create(['user_id' => $this->owner->id, 'tenant_id' => $this->tenant->id, 'role' => 'owner']);
        $reviewer = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        UserCanteenRole::create(['user_id' => $reviewer->id, 'canteen_id' => $canteen->id, 'role' => 'manager']);

        $this->account = (new TenantBankAccount)->forceFill(['tenant_id' => $this->tenant->id, 'bank_code' => 'BCA', 'account_holder' => 'Rina S.', 'account_last4' => '6721', 'account_number_cipher' => 'x', 'status' => 'verified', 'is_primary' => true]);
        $this->account->save();

        // Saldo tersedia Rp150.000 yang konsisten dengan ledger (satu entri penjualan).
        DB::table('ledger_entries')->insert(['tenant_id' => $this->tenant->id, 'idempotency_key' => 'seed:race', 'type' => 'sale_credit', 'available_delta' => 150000, 'held_delta' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenant_balances')->insert(['tenant_id' => $this->tenant->id, 'available_amount' => 150000, 'held_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        // Data test ini di-commit; bersihkan agar test lain (RefreshDatabase) mulai dari tabel kosong.
        $this->cleanDatabase();

        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        Schema::disableForeignKeyConstraints();
        // Hanya tabel di skema basis data test aktif (getTables() juga memuat skema lain yang terlihat).
        foreach (Schema::getTables(DB::getDatabaseName()) as $table) {
            $table = $table['name'];
            if ($table !== 'migrations') {
                DB::table($table)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Menjalankan dua proses pekerja yang mulai tepat bersamaan; mengembalikan hasil JSON keduanya.
     *
     * @param  list<list<string>>  $arguments
     * @return list<array{ok: bool, status?: string, error?: string, message?: string}>
     */
    private function race(array $arguments): array
    {
        $db = config('database.connections.'.config('database.default'));
        $env = [
            'APP_ENV' => 'testing', 'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => (string) config('database.default'), 'DB_HOST' => (string) $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => (string) $db['database'], 'DB_USERNAME' => (string) $db['username'], 'DB_PASSWORD' => (string) $db['password'],
            'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null', 'SESSION_DRIVER' => 'array',
            'WITHDRAWAL_MINIMUM' => '100000',
        ];
        $startAt = (string) (microtime(true) + 1.5);

        $processes = array_map(function (array $args) use ($env, $startAt): Process {
            $process = new Process([PHP_BINARY, base_path('tests/Support/withdrawal-race-worker.php'), $args[0], $args[1], $args[2], $startAt, $args[3] ?? '0'], base_path(), $env, null, 60);
            $process->start();

            return $process;
        }, $arguments);

        return array_map(function (Process $process): array {
            $process->wait();
            $result = json_decode(trim($process->getOutput()), true);
            $this->assertIsArray($result, 'keluaran pekerja: '.$process->getOutput().$process->getErrorOutput());

            return $result;
        }, $processes);
    }

    public function test_two_simultaneous_requests_create_one_hold_and_no_negative_balance(): void
    {
        $results = $this->race([
            ['request', (string) $this->account->id, (string) $this->owner->id, '100000'],
            ['request', (string) $this->account->id, (string) $this->owner->id, '100000'],
        ]);

        $this->assertSame(1, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame('WithdrawalException', collect($results)->firstWhere('ok', false)['error'], json_encode($results));

        $balance = DB::table('tenant_balances')->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame([50000, 100000], [(int) $balance->available_amount, (int) $balance->held_amount]);
        $this->assertSame(1, DB::table('ledger_entries')->where('type', 'hold')->count(), 'tidak ada hold ganda');
        $this->assertSame(1, Withdrawal::query()->withoutGlobalScope('tenant')->count());
        $this->assertTrue(app(WithdrawalService::class)->ledgerMatches($this->tenant->id));
    }

    public function test_two_simultaneous_approvals_pay_out_exactly_once(): void
    {
        $withdrawal = app(WithdrawalService::class)->request($this->account, 100000, $this->owner);
        $reviewer = User::query()->where('role', 'admin')->firstOrFail();

        $results = $this->race([
            ['approve', (string) $withdrawal->id, (string) $reviewer->id],
            ['approve', (string) $withdrawal->id, (string) $reviewer->id],
        ]);

        $this->assertSame(['paid', 'paid'], array_column($results, 'status'), 'persetujuan kedua idempoten');
        $this->assertSame(1, DB::table('ledger_entries')->where('type', 'withdrawal_debit')->count(), 'dana hanya dicairkan satu kali');
        $balance = DB::table('tenant_balances')->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame([50000, 0], [(int) $balance->available_amount, (int) $balance->held_amount]);
        $this->assertSame(1, DB::table('audit_logs')->where(['entity' => 'withdrawal', 'action' => 'paid'])->count());
        $this->assertTrue(app(WithdrawalService::class)->ledgerMatches($this->tenant->id));
    }
}
