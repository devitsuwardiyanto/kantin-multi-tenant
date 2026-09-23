<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\Data\PaymentChargeRequest;
use App\Modules\Payments\Data\QrisCharge;

/**
 * Kontrak gateway pembayaran (bebas provider). Modul lain bergantung pada antarmuka ini,
 * bukan implementasi konkret. HANYA SATU implementasi yang di-bind pada satu waktu
 * (mis. FakeQrisGateway untuk sandbox) — memasang beberapa provider sekaligus dilarang.
 */
interface PaymentGateway
{
    /**
     * Membuat tagihan QRIS dinamis untuk sebuah order.
     */
    public function createQrisCharge(PaymentChargeRequest $request): QrisCharge;

    /**
     * Nama provider (untuk pelabelan/audit).
     */
    public function name(): string;

    /**
     * Apakah gateway ini sandbox (mengizinkan simulasi pembayaran tanpa webhook nyata).
     */
    public function isSandbox(): bool;
}
