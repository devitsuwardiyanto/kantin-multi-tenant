<?php

namespace App\Modules\Ordering\Contracts;

use App\Modules\Ordering\Exceptions\NotificationFailedException;

/**
 * UC-12: kontrak saluran notifikasi pelanggan (bebas provider). Hanya SATU implementasi di-bind
 * (sandbox: FakeWhatsAppGateway); provider WhatsApp nyata cukup mengganti binding.
 */
interface NotificationGateway
{
    /**
     * Kirim pesan ke nomor WhatsApp (format 62…). Mengembalikan ID pesan dari provider.
     *
     * @throws NotificationFailedException
     */
    public function send(string $whatsapp, string $message): string;

    public function channel(): string;
}
