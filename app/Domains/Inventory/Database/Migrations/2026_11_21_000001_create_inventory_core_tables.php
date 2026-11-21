<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('cooperative_id');
            $table->string('stockable_type', 16);
            $table->ulid('stockable_id');
            $table->string('unit_code', 12);
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('quantity_reserved', 15, 3)->default(0);
            $table->decimal('quantity_reserved_normal', 15, 3)->default(0);
            $table->decimal('quantity_reserved_quarantine', 15, 3)->default(0);
            $table->decimal('quantity_quarantined', 15, 3)->default(0);
            $table->decimal('quantity_quarantine_authorized', 15, 3)->default(0);
            $table->ulid('active_count_session_id')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['cooperative_id', 'stockable_type', 'stockable_id'],
                'inventory_stockable_unique',
            );
            $table->index(['cooperative_id', 'stockable_type'], 'inventory_stock_coop_type_idx');
            $table->index('active_count_session_id', 'inventory_stock_count_lock_idx');
        });

        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('cooperative_order_id')->unique();
            $table->ulid('cooperative_id');
            $table->string('status', 24)->default('active');
            $table->string('idempotency_key', 100)->unique();
            $table->ulid('actor_id')->nullable();
            $table->string('actor_type', 80)->nullable();
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 120)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at'], 'inventory_reservations_expiry_idx');
            $table->index(['cooperative_id', 'status'], 'inventory_reservations_coop_status_idx');
        });

        Schema::create('inventory_reservation_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('reservation_id')
                ->constrained('inventory_reservations')
                ->restrictOnDelete();
            $table->foreignUlid('stock_id')
                ->constrained('inventory_stocks')
                ->restrictOnDelete();
            $table->ulid('order_item_id');
            $table->decimal('quantity', 15, 3);
            $table->decimal('quantity_normal', 15, 3)->default(0);
            $table->decimal('quantity_quarantine', 15, 3)->default(0);
            $table->string('unit_code', 12);
            $table->timestamps();

            $table->unique(['reservation_id', 'order_item_id'], 'inventory_reservation_item_unique');
            $table->index('stock_id', 'inventory_reservation_items_stock_idx');
            $table->index('order_item_id', 'inventory_reservation_items_order_item_idx');
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_id')
                ->constrained('inventory_stocks')
                ->restrictOnDelete();
            $table->ulid('cooperative_id');
            $table->string('movement_type', 40);
            $table->decimal('quantity_delta', 15, 3)->default(0);
            $table->decimal('reserved_delta', 15, 3)->default(0);
            $table->decimal('reserved_normal_delta', 15, 3)->default(0);
            $table->decimal('reserved_quarantine_delta', 15, 3)->default(0);
            $table->decimal('quarantined_delta', 15, 3)->default(0);
            $table->decimal('quarantine_authorized_delta', 15, 3)->default(0);
            $table->json('balance_before');
            $table->json('balance_after');
            $table->string('unit_code', 12);
            $table->string('reason', 500)->nullable();
            $table->ulid('actor_id')->nullable();
            $table->string('actor_type', 80)->nullable();
            $table->string('reference_type', 80)->nullable();
            $table->ulid('reference_id')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['stock_id', 'occurred_at'], 'inventory_movements_stock_date_idx');
            $table->index(['cooperative_id', 'movement_type', 'occurred_at'], 'inventory_movements_coop_type_idx');
            $table->index(['reference_type', 'reference_id'], 'inventory_movements_reference_idx');
            $table->index('idempotency_key', 'inventory_movements_idempotency_idx');
        });

        Schema::create('inventory_idempotency_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('subject_type', 60);
            $table->ulid('subject_id');
            $table->string('operation', 60);
            $table->string('idempotency_key', 100);
            $table->char('request_hash', 64);
            $table->json('result')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['subject_type', 'subject_id', 'operation', 'idempotency_key'],
                'inventory_idempotency_subject_operation_unique',
            );
        });

        Schema::create('inventory_outbox_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_key', 120);
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'available_at'], 'inventory_outbox_pending_idx');
            $table->index('event_key', 'inventory_outbox_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_outbox_messages');
        Schema::dropIfExists('inventory_idempotency_records');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_reservation_items');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_stocks');
    }
};
