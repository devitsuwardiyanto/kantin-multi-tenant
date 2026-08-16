<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain C — Katalog & konfigurasi tenant (ERD Fase 0).
 * Isolasi lintas-tenant ditegakkan DB: composite FK (tenant_id, x_id) -> parent(tenant_id, id).
 * Global scope (Modul 4) melengkapi, tetapi constraint DB tetap benteng terakhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_operating_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Minggu .. 6=Sabtu
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps(6);

            $table->unique(['tenant_id', 'day_of_week']);
        });

        Schema::create('commission_schemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('commission_rate', 6, 4); // exact, mis. 0.1500 = 15%
            $table->timestampTz('valid_from', 6);
            $table->timestampTz('valid_to', 6)->nullable(); // null = masih berlaku
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']); // target FK snapshot komisi
            $table->index(['tenant_id', 'valid_from']);
        });

        Schema::create('menu_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'id']); // target composite FK
        });

        Schema::create('modifier_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('min_select')->default(0);
            $table->unsignedSmallInteger('max_select')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']); // target composite FK
        });

        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('category_id');
            $table->string('name', 120);
            $table->unsignedBigInteger('base_price'); // Rupiah
            $table->integer('stock_qty')->default(0);
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('prep_minutes')->default(10);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']); // target composite FK (order_items, pivot)
            $table->index(['tenant_id', 'category_id', 'is_available']);
            // Kategori harus milik tenant yang sama.
            $table->foreign(['tenant_id', 'category_id'])
                ->references(['tenant_id', 'id'])->on('menu_categories')->restrictOnDelete();
        });

        Schema::create('modifier_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('group_id');
            $table->string('name', 120);
            $table->bigInteger('price_delta')->default(0); // boleh negatif
            $table->integer('stock_qty')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']); // target composite FK
            // Group modifier harus milik tenant yang sama (checkpoint Tahap 3).
            $table->foreign(['tenant_id', 'group_id'])
                ->references(['tenant_id', 'id'])->on('modifier_groups')->restrictOnDelete();
        });

        Schema::create('menu_modifier_groups', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('modifier_group_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps(6);

            $table->primary(['menu_id', 'modifier_group_id']);
            $table->foreign(['tenant_id', 'menu_id'])
                ->references(['tenant_id', 'id'])->on('menus')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'modifier_group_id'])
                ->references(['tenant_id', 'id'])->on('modifier_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_modifier_groups');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('commission_schemes');
        Schema::dropIfExists('tenant_operating_hours');
    }
};
