<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-15 Proses Antrean Dapur + UC-09 Lacak Status (Pertemuan 12, rantai v3): waktu tiap tahap
 * dapur (timer kartu KDS, "dibayar/siap pukul") dan pembatalan oleh tenant beserta alasannya
 * untuk proses pengembalian dana oleh pengelola (refund_status = pending → P13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_orders', function (Blueprint $table): void {
            $table->timestampTz('accepted_at', 6)->nullable()->after('release_at');
            $table->timestampTz('ready_at', 6)->nullable()->after('accepted_at');
            $table->timestampTz('completed_at', 6)->nullable()->after('ready_at');
            $table->timestampTz('cancelled_at', 6)->nullable()->after('completed_at');
            $table->string('cancel_reason', 120)->nullable()->after('cancelled_at');
            $table->string('refund_status', 20)->nullable()->after('cancel_reason'); // pending|refunded
        });
    }

    public function down(): void
    {
        Schema::table('tenant_orders', function (Blueprint $table): void {
            $table->dropColumn(['accepted_at', 'ready_at', 'completed_at', 'cancelled_at', 'cancel_reason', 'refund_status']);
        });
    }
};
