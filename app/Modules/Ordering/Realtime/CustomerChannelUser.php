<?php

namespace App\Modules\Ordering\Realtime;

/**
 * Identitas broadcast untuk pelanggan ANONIM (tanpa akun): hanya membawa ID sesi pelanggan
 * yang sudah diverifikasi dari cookie tepercaya. Dipakai otorisasi channel `order.{publicId}`.
 */
final readonly class CustomerChannelUser
{
    public function __construct(public string $customerSessionId) {}

    /** Broadcaster membutuhkan pengenal pengguna untuk respons otorisasi. */
    public function getAuthIdentifier(): string
    {
        return 'customer:'.$this->customerSessionId;
    }
}
