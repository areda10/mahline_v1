<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_thresholds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_id')->unique()->constrained('inventory_stocks')->restrictOnDelete();
            $table->decimal('low_stock_threshold', 15, 3);
            $table->ulid('updated_by');
            $table->timestamps();
        });

        Schema::create('inventory_threshold_histories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_id')->constrained('inventory_stocks')->restrictOnDelete();
            $table->decimal('old_threshold', 15, 3)->nullable();
            $table->decimal('new_threshold', 15, 3);
            $table->ulid('actor_id');
            $table->string('reason', 500);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['stock_id', 'occurred_at'], 'inventory_threshold_history_stock_date_idx');
        });

        Schema::create('inventory_alert_episodes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_id')->constrained('inventory_stocks')->restrictOnDelete();
            $table->string('alert_type', 24);
            $table->decimal('available_quantity_at_trigger', 15, 3);
            $table->decimal('threshold_at_trigger', 15, 3)->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
            $table->index(['stock_id', 'alert_type', 'cleared_at'], 'inventory_alert_episode_active_idx');
            $table->index(['triggered_at', 'alert_type'], 'inventory_alert_episode_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_alert_episodes');
        Schema::dropIfExists('inventory_threshold_histories');
        Schema::dropIfExists('inventory_stock_thresholds');
    }
};
