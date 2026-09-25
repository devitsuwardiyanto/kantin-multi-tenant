<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-17 Ekspor Laporan (Pertemuan 13, rantai v3): permintaan ekspor laporan penjualan yang
 * diproses job queue. Berkas disimpan privat; tautan unduh bertanda tangan kedaluwarsa 24 jam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('format', 10); // xlsx|pdf
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status', 20)->default('queued'); // queued|ready|failed
            $table->unsignedInteger('row_count')->default(0);
            $table->boolean('notify_by_mail')->default(false);
            $table->string('path')->nullable();
            $table->timestampTz('expires_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->index(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
