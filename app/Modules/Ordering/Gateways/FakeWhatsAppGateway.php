<?php

namespace App\Modules\Ordering\Gateways;

use App\Modules\Ordering\Contracts\NotificationGateway;
use App\Modules\Ordering\Exceptions\NotificationFailedException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Gateway WhatsApp TIRUAN (sandbox): pesan ditulis ke log aplikasi, tidak dikirim ke provider
 * nyata. `unavailable` menirukan provider yang gagal untuk demo/pengujian retry.
 */
final class FakeWhatsAppGateway implements NotificationGateway
{
    public function __construct(private bool $unavailable = false) {}

    public function send(string $whatsapp, string $message): string
    {
        if ($this->unavailable) {
            throw new NotificationFailedException('Gateway WhatsApp sandbox tidak merespons.');
        }

        $id = 'FAKE-WA-'.strtoupper(Str::random(12));
        Log::info('WhatsApp (sandbox) terkirim.', ['id' => $id, 'to' => '…'.substr($whatsapp, -4), 'message' => $message]);

        return $id;
    }

    public function channel(): string
    {
        return 'whatsapp';
    }
}
