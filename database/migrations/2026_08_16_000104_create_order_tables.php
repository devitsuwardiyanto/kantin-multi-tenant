<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain D — Order induk & snapshot (ERD Fase 0).
 * orders = platform-scoped (TANPA tenant_id) karena satu checkout bisa lintas tenant.
 * tenant_orders menyimpan tenant_id + snapshot komisi/harga (historis, tidak berubah).
 * checkout_key = idempotency; tracking_token_hash = BINARY(32).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id');
            $table->string('order_number', 30);
            $table->foreignId('canteen_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('customer_session_id')->nullable()->constrained('customer_sessions')->nullOnDelete();
            $table->string('checkout_key', 100); // idempotency checkout
            $table->binary('tracking_token_hash', 32);
            $table->string('status', 30)->default('pending'); // pending|awaiting_payment|paid|cancelled|expired
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('service_fee_amount')->default(0);
            $table->unsignedBigInteger('grand_total_amount')->default(0);
            $table->json('customer_snapshot')->nullable();
            $table->json('table_snapshot')->nullable();
            $table->timestampTz('placed_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique('public_id');
            $table->unique('order_number');
            $table->unique('checkout_key');
            $table->unique('tracking_token_hash');
            $table->index(['canteen_id', 'status']);
        });

        Schema::create('tenant_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('commission_id');
            $table->string('status', 30)->default('pending'); // pending|accepted|preparing|ready|completed|cancelled
            $table->timestampTz('scheduled_at', 6)->nullable(); // pre-order
            $table->decimal('commission_rate_snapshot', 6, 4);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('service_fee_amount')->default(0);
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('net_amount');
            $table->timestamps(6);

            $table->unique(['order_id', 'tenant_id']);
            $table->unique(['tenant_id', 'id']); // target composite FK order_items
            $table->index(['tenant_id', 'status']);
            $table->foreign(['tenant_id', 'commission_id'])
                ->references(['tenant_id', 'id'])->on('commission_schemes')->restrictOnDelete();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('tenant_order_id');
            $table->unsignedBigInteger('menu_id');
            $table->string('name_snapshot', 120);
            $table->unsignedBigInteger('unit_price_snapshot');
            $table->unsignedSmallInteger('prep_minutes_snapshot')->default(0);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('modifier_total')->default(0);
            $table->unsignedBigInteger('line_total');
            $table->timestamps(6);

            $table->unique(['tenant_id', 'id']); // target composite FK modifiers
            $table->index(['tenant_id', 'tenant_order_id']);
            $table->foreign(['tenant_id', 'tenant_order_id'])
                ->references(['tenant_id', 'id'])->on('tenant_orders')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'menu_id'])
                ->references(['tenant_id', 'id'])->on('menus')->restrictOnDelete();
        });

        Schema::create('order_item_modifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('modifier_group_id'); // snapshot ref
            $table->unsignedBigInteger('modifier_option_id'); // snapshot ref
            $table->string('group_name_snapshot', 120);
            $table->string('option_name_snapshot', 120);
            $table->bigInteger('price_delta_snapshot');
            $table->timestamps(6);

            $table->index(['tenant_id', 'order_item_id']);
            $table->foreign(['tenant_id', 'order_item_id'])
                ->references(['tenant_id', 'id'])->on('order_items')->cascadeOnDelete();
        });

        Schema::create('menu_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('idempotency_key', 100);
            $table->string('type', 20); // sale|restock|adjustment
            $table->integer('quantity_delta');
            $table->timestamps(6);

            $table->unique('idempotency_key');
            $table->index(['tenant_id', 'menu_id']);
            $table->foreign(['tenant_id', 'menu_id'])
                ->references(['tenant_id', 'id'])->on('menus')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_stock_movements');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('tenant_orders');
        Schema::dropIfExists('orders');
    }
};
