<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_quarantines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_id')
                ->constrained('inventory_stocks')
                ->restrictOnDelete();
            $table->ulid('cooperative_id');
            $table->string('source_type', 60);
            $table->ulid('source_id')->nullable();
            $table->decimal('quantity_received', 15, 3);
            $table->decimal('quantity_remaining', 15, 3);
            $table->decimal('quantity_authorized', 15, 3)->default(0);
            $table->string('status', 32)->default('pending_inspection');
            $table->string('inspection_result', 32)->default('pending');
            $table->string('reason', 500);
            $table->string('inspection_reason', 500)->nullable();
            $table->ulid('received_by')->nullable();
            $table->ulid('inspected_by')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();

            $table->index(['stock_id', 'status'], 'inventory_quarantine_stock_status_idx');
            $table->index(['cooperative_id', 'received_at'], 'inventory_quarantine_coop_date_idx');
            $table->index(['source_type', 'source_id'], 'inventory_quarantine_source_idx');
        });

        Schema::create('inventory_quarantine_authorizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('quarantine_id')
                ->constrained('inventory_quarantines')
                ->restrictOnDelete();
            $table->foreignUlid('stock_id')
                ->constrained('inventory_stocks')
                ->restrictOnDelete();
            $table->decimal('quantity_authorized', 15, 3);
            $table->decimal('quantity_remaining', 15, 3);
            $table->decimal('quantity_reserved', 15, 3)->default(0);
            $table->string('status', 20)->default('active');
            $table->ulid('authorized_by');
            $table->string('reason', 500);
            $table->timestamp('authorized_at');
            $table->ulid('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['stock_id', 'status'], 'inventory_quarantine_auth_stock_status_idx');
            $table->index(['quarantine_id', 'status'], 'inventory_quarantine_auth_batch_status_idx');
        });

        Schema::create('inventory_reservation_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('reservation_item_id')
                ->constrained('inventory_reservation_items')
                ->restrictOnDelete();
            $table->string('allocation_type', 32);
            $table->foreignUlid('quarantine_authorization_id')
                ->nullable()
                ->constrained('inventory_quarantine_authorizations')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();

            $table->index(['reservation_item_id', 'allocation_type'], 'inventory_reservation_alloc_type_idx');
            $table->index('quarantine_authorization_id', 'inventory_reservation_alloc_auth_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservation_allocations');
        Schema::dropIfExists('inventory_quarantine_authorizations');
        Schema::dropIfExists('inventory_quarantines');
    }
};
