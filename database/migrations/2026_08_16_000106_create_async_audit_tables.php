<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domain F — Integrasi asinkron & audit (ERD Fase 0).
 * outbox_events: side-effect dikirim setelah commit. audit_logs: append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('customer_session_id')->nullable()->constrained('customer_sessions')->nullOnDelete();
            $table->foreignId('tenant_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key', 150);
            $table->string('channel', 30); // broadcast|mail|database
            $table->string('status', 20)->default('pending'); // pending|sent|failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->timestamps(6);

            $table->unique(['event_key', 'channel']); // idempotent per channel
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key', 150);
            $table->string('aggregate_type', 80);
            $table->string('aggregate_id', 64);
            $table->string('event_type', 100);
            $table->json('payload');
            $table->timestampTz('published_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique('event_key');
            $table->index('published_at');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('canteen_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity', 80);
            $table->string('entity_id', 64)->nullable();
            $table->string('action', 60);
            $table->string('request_id', 64)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('logged_at', 6);
            $table->timestamps(6);

            $table->index(['entity', 'entity_id']);
            $table->index(['tenant_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('notification_deliveries');
    }
};
