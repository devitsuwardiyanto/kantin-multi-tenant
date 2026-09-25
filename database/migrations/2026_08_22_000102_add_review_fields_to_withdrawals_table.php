<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-23 Verifikasi Pencairan Dana (Pertemuan 13, rantai v3): bukti transfer, catatan/alasan
 * penolakan, dan waktu peninjauan. Status baru `investigation` (ledger tidak sesuai) tetap
 * memegang active_tenant_lock sehingga dana tetap tertahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->string('review_note', 500)->nullable()->after('transfer_snapshot');
            $table->string('transfer_proof_path')->nullable()->after('review_note');
            $table->timestampTz('reviewed_at', 6)->nullable()->after('transfer_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropColumn(['review_note', 'transfer_proof_path', 'reviewed_at']);
        });
    }
};
