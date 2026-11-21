<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('cooperative_id');
            $table->string('status', 24)->default('in_progress');
            $table->ulid('created_by');
            $table->ulid('validated_by')->nullable();
            $table->ulid('cancelled_by')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['cooperative_id', 'status'], 'inventory_count_sessions_coop_status_idx');
            $table->index(['status', 'updated_at'], 'inventory_count_sessions_status_updated_idx');
        });

        Schema::create('inventory_count_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')
                ->constrained('inventory_count_sessions')
                ->restrictOnDelete();
            $table->foreignUlid('stock_id')
                ->constrained('inventory_stocks')
                ->restrictOnDelete();
            $table->decimal('expected_quantity', 15, 3);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('difference_quantity', 15, 3)->nullable();
            $table->string('difference_reason', 500)->nullable();
            $table->string('status', 20)->default('pending_count');
            $table->ulid('counted_by')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->ulid('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'stock_id'], 'inventory_count_session_stock_unique');
            $table->index(['stock_id', 'status'], 'inventory_count_items_stock_status_idx');
        });

        Schema::create('inventory_count_participants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')
                ->constrained('inventory_count_sessions')
                ->restrictOnDelete();
            $table->ulid('user_id');
            $table->string('participant_role', 40);
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['session_id', 'user_id'], 'inventory_count_participant_unique');
        });

        Schema::create('inventory_count_item_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('count_item_id')
                ->constrained('inventory_count_items')
                ->restrictOnDelete();
            $table->string('event_type', 24);
            $table->decimal('old_quantity', 15, 3)->nullable();
            $table->decimal('new_quantity', 15, 3)->nullable();
            $table->string('reason', 500)->nullable();
            $table->ulid('actor_id');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['count_item_id', 'occurred_at'], 'inventory_count_item_events_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_item_events');
        Schema::dropIfExists('inventory_count_participants');
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_count_sessions');
    }
};
