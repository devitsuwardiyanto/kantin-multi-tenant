<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 2 — role/status interim pada users.
 * Catatan: model role penuh (relasi, effective-dated) diformalkan pada Modul 4–5.
 * tenant_id sengaja BELUM ditambahkan di sini; isolasi tenant diperkenalkan Modul 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // 'admin' = pengelola kantin, 'tenant' = operator tenant, null = pelanggan/terdaftar biasa
            $table->string('role')->nullable()->after('email');
            $table->string('status')->default('active')->after('role'); // active|suspended
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'status']);
        });
    }
};
