<?php

namespace App\Modules\Ordering\Jobs;

use App\Modules\Ordering\Contracts\NotificationGateway;
use App\Modules\Ordering\Exceptions\NotificationFailedException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * UC-12 langkah 3–5: kirim satu notifikasi dari antrean dan catat statusnya di
 * notification_deliveries. Gagal → dicoba ulang maksimal 3 kali dengan jeda bertingkat
 * (exponential backoff); setelah itu ditandai failed tanpa mengganggu pesanan (4a/4b).
 */
class SendCustomerNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId)
    {
        $this->afterCommit();
    }

    /**
     * Jeda percobaan ulang (detik): 5 → 25 (eksponensial).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 25];
    }

    public function handle(NotificationGateway $gateway): void
    {
        $delivery = DB::table('notification_deliveries')->where('id', $this->deliveryId)->first();
        if ($delivery === null || $delivery->status !== 'pending') {
            return;
        }

        $attempt = (int) $delivery->attempts + 1;
        DB::table('notification_deliveries')->where('id', $this->deliveryId)->update(['attempts' => $attempt, 'updated_at' => now()]);

        /** @var array{to: string, message: string} $payload */
        $payload = json_decode((string) $delivery->payload, true);

        try {
            $providerId = $gateway->send($payload['to'], $payload['message']);
        } catch (NotificationFailedException $e) {
            // Kegagalan TIDAK dilempar ke pemanggil: coba ulang dengan jeda, lalu catat gagal.
            if ($attempt < $this->tries) {
                $this->release($this->backoff()[$attempt - 1]);

                return;
            }

            $this->failed($e);

            return;
        }

        DB::table('notification_deliveries')->where('id', $this->deliveryId)->update([
            'status' => 'sent',
            'payload' => json_encode($payload + ['provider_id' => $providerId, 'sent_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('notification_deliveries')->where('id', $this->deliveryId)->update(['status' => 'failed', 'updated_at' => now()]);
        Log::warning('Notifikasi pelanggan gagal setelah percobaan ulang.', ['delivery_id' => $this->deliveryId, 'error' => $exception?->getMessage()]);
    }
}
