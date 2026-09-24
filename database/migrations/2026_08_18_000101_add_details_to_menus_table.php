<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-13: deskripsi dan foto menu (WebP di disk public) serta soft delete agar menu yang pernah
 * dipesan tetap utuh pada riwayat transaksi (alur 3a).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->string('description', 500)->nullable()->after('name');
            $table->string('photo_path')->nullable()->after('description');
            $table->softDeletes('deleted_at', 6);
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->dropSoftDeletes('deleted_at');
            $table->dropColumn(['description', 'photo_path']);
        });
    }
};
