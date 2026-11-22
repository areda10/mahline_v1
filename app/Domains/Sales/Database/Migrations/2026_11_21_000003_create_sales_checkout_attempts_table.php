<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_checkout_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Customer and order are cross-domain logical references.
            $table->ulid('customer_id');
            $table->char('idempotency_key_hash', 64);
            $table->ulid('order_id');
            $table->json('ordered_cooperative_ids');
            $table->json('excluded_cooperative_ids');
            $table->json('response_snapshot');
            $table->timestamps();

            $table->unique(['customer_id', 'idempotency_key_hash'], 'sales_checkout_customer_key_unique');
            $table->unique('order_id', 'sales_checkout_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_checkout_attempts');
    }
};
