<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_safety_holds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('cooperative_order_id')
                ->constrained('cooperative_orders')
                ->restrictOnDelete();
            $table->ulid('cooperative_id');
            $table->string('source_type', 60);
            $table->ulid('source_id');
            $table->string('reason', 500);
            $table->string('status', 20)->default('active');
            $table->ulid('raised_by_id')->nullable();
            $table->string('raised_by_type', 80)->nullable();
            $table->ulid('resolved_by_id')->nullable();
            $table->string('resolved_by_type', 80)->nullable();
            $table->string('resolution', 30)->nullable();
            $table->string('resolution_reason', 500)->nullable();
            $table->timestamp('raised_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['cooperative_order_id', 'source_type', 'source_id'],
                'order_safety_hold_source_unique',
            );
            $table->index(['cooperative_order_id', 'status'], 'order_safety_hold_status_idx');
            $table->index(['cooperative_id', 'status'], 'order_safety_hold_coop_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_safety_holds');
    }
};
