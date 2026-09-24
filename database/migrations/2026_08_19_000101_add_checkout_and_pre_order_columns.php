<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-05 Checkout Pesanan + UC-06 Jadwalkan Pre-Order (Pertemuan 9, rantai v3).
 *  - orders.service_mode: dine_in (antar ke meja) | pickup (pesan dulu, ambil sendiri).
 *  - order_items.note: snapshot catatan item dari UC-04 (≤ 200 karakter, sudah tersaring).
 *  - tenant_orders.release_at: kapan pesanan terjadwal dilepas ke antrean dapur
 *    (scheduled_at − estimasi penyiapan); indeks dipakai penjadwal setiap menit.
 *  - tenants.pre_order_enabled + pre_order_slot_capacity: tenant mengaktifkan pre-order dan
 *    membatasi jumlah pesanan per slot 15 menit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('service_mode', 10)->default('dine_in')->after('status');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('note', 200)->nullable()->after('line_total');
        });

        Schema::table('tenant_orders', function (Blueprint $table): void {
            $table->timestampTz('release_at', 6)->nullable()->after('scheduled_at');
            $table->index(['status', 'release_at']);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('pre_order_enabled')->default(false)->after('status');
            $table->unsignedSmallInteger('pre_order_slot_capacity')->default(5)->after('pre_order_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['pre_order_enabled', 'pre_order_slot_capacity']);
        });

        Schema::table('tenant_orders', function (Blueprint $table): void {
            $table->dropIndex(['status', 'release_at']);
            $table->dropColumn('release_at');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('note');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('service_mode');
        });
    }
};
