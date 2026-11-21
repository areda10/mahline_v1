<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_carts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Cross-domain identity reference: deliberately no FK to Identity.
            $table->ulid('customer_id')->nullable()->unique();
            $table->char('guest_token_hash', 64)->nullable()->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->ulid('merged_into_cart_id')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'last_activity_at'], 'sales_carts_guest_expiry_idx');
            $table->index('merged_into_cart_id', 'sales_carts_merged_into_idx');
        });

        Schema::create('sales_cart_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('cart_id')->constrained('sales_carts')->restrictOnDelete();
            // Product and cooperative identifiers are logical references owned by other domains.
            $table->ulid('product_id');
            $table->ulid('product_variant_id')->nullable();
            $table->ulid('cooperative_id');
            $table->string('unit_code', 12);
            $table->decimal('quantity', 15, 3);
            $table->json('initial_product_snapshot');
            $table->json('current_product_snapshot');
            $table->decimal('initial_unit_price', 15, 2);
            $table->decimal('current_unit_price', 15, 2);
            $table->decimal('accepted_unit_price', 15, 2)->nullable();
            $table->char('accepted_price_fingerprint', 64)->nullable();
            $table->char('commercial_fingerprint', 64);
            $table->char('line_fingerprint', 64)->nullable();
            $table->string('status', 24)->default('active');
            $table->string('unavailable_reason', 80)->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'line_fingerprint'], 'sales_cart_items_active_line_unique');
            $table->index(['cart_id', 'status'], 'sales_cart_items_cart_status_idx');
            $table->index(['product_id', 'product_variant_id'], 'sales_cart_items_product_idx');
            $table->index('cooperative_id', 'sales_cart_items_cooperative_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_cart_items');
        Schema::dropIfExists('sales_carts');
    }
};
