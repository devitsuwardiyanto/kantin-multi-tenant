<?php

/**
 * Proses pekerja untuk WithdrawalConcurrencyTest: dijalankan sebagai proses PHP TERPISAH agar dua
 * permintaan benar-benar berjalan bersamaan pada MariaDB (bukan disimulasikan berurutan).
 *
 * php tests/Support/withdrawal-race-worker.php <request|approve> <id> <user_id> <start_at> [amount]
 * Menunggu hingga <start_at> (microtime) agar kedua proses memulai transaksi pada saat yang sama,
 * lalu mencetak hasil sebagai JSON satu baris.
 */

use App\Models\TenantBankAccount;
use App\Models\User;
use App\Models\Withdrawal;
use App\Modules\Payments\Services\WithdrawalService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $action, $id, $userId, $startAt] = $argv;
$amount = (int) ($argv[5] ?? 0);

while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $service = app(WithdrawalService::class);
    $user = User::query()->findOrFail((int) $userId);

    $withdrawal = $action === 'request'
        ? $service->request(TenantBankAccount::query()->withoutGlobalScope('tenant')->findOrFail((int) $id), $amount, $user)
        : $service->approve(Withdrawal::query()->withoutGlobalScope('tenant')->findOrFail((int) $id), $user, 'withdrawal-proofs/race.pdf');

    echo json_encode(['ok' => true, 'status' => $withdrawal->status]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => class_basename($e), 'message' => $e->getMessage()]);
}
