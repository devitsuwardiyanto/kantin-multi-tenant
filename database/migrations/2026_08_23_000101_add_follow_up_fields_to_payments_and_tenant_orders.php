<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan audit Pertemuan 14: tindak lanjut pengelola kantin.
 *  - UC-08 alur 3a: keputusan peninjauan pembayaran (diterima / dana dikembalikan) + peninjau.
 *  - UC-15 alur 4a: pengembalian dana sub-pesanan yang dibatalkan dapur + pengelola pemroses.
 * Kolom keuangan lama tidak diubah; pembalikan saldo memakai entri ledger reversal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('review_outcome', 30)->nullable()->after('settled_at');
            $table->string('review_note', 500)->nullable()->after('review_outcome');
            $table->foreignId('reviewed_by')->nullable()->after('review_note')->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at', 6)->nullable()->after('reviewed_by');
        });

        Schema::table('tenant_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('refund_amount')->nullable()->after('refund_status');
            $table->string('refund_note', 500)->nullable()->after('refund_amount');
            $table->foreignId('refunded_by')->nullable()->after('refund_note')->constrained('users')->nullOnDelete();
            $table->timestampTz('refunded_at', 6)->nullable()->after('refunded_by');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn(['refund_amount', 'refund_note', 'refunded_at']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_outcome', 'review_note', 'reviewed_at']);
        });
    }
};
