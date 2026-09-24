<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-10 Split Payment Otomatis (Pertemuan 11, rantai v3).
 *  - payment_allocations: rincian pemecahan per pembayaran (satu baris per tenant + satu untuk
 *    pengelola kantin) sebagai dasar rekonsiliasi UC-18. recipient_key unik per payment.
 *  - platform_ledger_entries: ledger APPEND-ONLY milik pengelola kantin (komisi, pajak, biaya
 *    layanan, selisih pembulatan, reversal). ledger_entries tetap khusus tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('tenant_order_id')->nullable();
            $table->string('recipient', 10); // tenant|canteen
            $table->string('recipient_key', 40); // tenant:{id} | canteen
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->decimal('commission_rate_snapshot', 6, 4)->nullable();
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('service_fee_amount')->default(0);
            $table->bigInteger('rounding_amount')->default(0);
            $table->bigInteger('amount'); // dana yang dikreditkan ke penerima
            $table->timestamps(6);

            $table->unique(['payment_id', 'recipient_key']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('platform_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('canteen_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 100);
            $table->string('type', 40); // commission_credit|tax_credit|service_fee_credit|rounding_credit|reversal
            $table->bigInteger('amount');
            $table->timestamps(6);

            $table->unique('idempotency_key');
            $table->index(['canteen_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ledger_entries');
        Schema::dropIfExists('payment_allocations');
    }
};
