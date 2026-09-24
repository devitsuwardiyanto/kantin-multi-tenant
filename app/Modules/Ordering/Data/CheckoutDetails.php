<?php

namespace App\Modules\Ordering\Data;

/**
 * Isian formulir checkout UC-05: mode penyajian dan identitas ringkas pelanggan. Nomor
 * WhatsApp sudah dinormalisasi ke format 62xxxxxxxxxx oleh komponen checkout.
 */
final readonly class CheckoutDetails
{
    public const DINE_IN = 'dine_in';

    public const PICKUP = 'pickup';

    public function __construct(
        public string $serviceMode,
        public string $customerName,
        public string $whatsapp,
    ) {}

    public function isPickup(): bool
    {
        return $this->serviceMode === self::PICKUP;
    }

    /**
     * Normalisasi nomor WhatsApp Indonesia: 0812-3456-7890 / +62 812… → 6281234567890.
     */
    public static function normalizeWhatsapp(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}
