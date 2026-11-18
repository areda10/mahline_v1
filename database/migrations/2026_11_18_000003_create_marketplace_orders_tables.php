<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->ulidPrimary();
            $table->foreignUlid('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('status', 40)->default('awaiting_cooperatives');
            $table->char('currency', 3)->default('MAD');
            $table->decimal('items_subtotal', 14, 2)->default(0);
            $table->decimal('delivery_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->json('delivery_address_snapshot');
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->auditActorColumns();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'created_at'], 'orders_customer_created_idx');
            $table->index(['status', 'created_at'], 'orders_status_created_idx');
        });

        Schema::create('cooperative_orders', function (Blueprint $table): void {
            $table->ulidPrimary();
            $table->foreignUlid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUlid('cooperative_id')->constrained('cooperatives')->restrictOnDelete();
            $table->string('reference', 50)->unique();
            $table->string('status', 40)->default('awaiting_cooperative');
            $table->decimal('items_subtotal', 14, 2)->default(0);
            $table->decimal('delivery_fee', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->timestamp('response_deadline_at')->nullable();
            $table->timestamp('response_overdue_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignUlid('substituted_by_admin_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('substitution_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('refused_at')->nullable();
            $table->text('refusal_reason')->nullable();
            $table->auditActorColumns();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['order_id', 'cooperative_id'], 'coop_orders_order_coop_unique');
            $table->index(['cooperative_id', 'status', 'created_at'], 'coop_orders_coop_status_idx');
            $table->index(['status', 'response_deadline_at'], 'coop_orders_deadline_idx');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->ulidPrimary();
            $table->foreignUlid('cooperative_order_id')->constrained('cooperative_orders')->restrictOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->json('product_snapshot');
            $table->decimal('quantity', 14, 3);
            $table->string('unit', 40)->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->string('status', 30)->default('active');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->auditActorColumns();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cooperative_order_id', 'status'], 'order_items_suborder_status_idx');
            $table->index(['product_id', 'product_variant_id'], 'order_items_product_variant_idx');
        });

        Schema::create('order_change_proposals', function (Blueprint $table): void {
            $table->ulidPrimary();
            $table->foreignUlid('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignUlid('proposed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('proposal_type', 30);
            $table->decimal('proposed_quantity', 14, 3)->nullable();
            $table->foreignUlid('replacement_product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignUlid('replacement_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->json('replacement_snapshot')->nullable();
            $table->text('reason');
            $table->string('status', 30)->default('pending_customer');
            $table->timestamp('customer_response_deadline_at')->nullable();
            $table->timestamp('customer_responded_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->auditActorColumns();
            $table->timestamps();

            $table->index(['order_item_id', 'status'], 'order_proposals_item_status_idx');
            $table->index(['status', 'customer_response_deadline_at'], 'order_proposals_deadline_idx');
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->ulidPrimary();
            $table->string('subject_type', 40);
            $table->ulid('subject_id');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 50)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'order_history_subject_idx');
            $table->index(['actor_id', 'occurred_at'], 'order_history_actor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_change_proposals');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('cooperative_orders');
        Schema::dropIfExists('orders');
    }
};
