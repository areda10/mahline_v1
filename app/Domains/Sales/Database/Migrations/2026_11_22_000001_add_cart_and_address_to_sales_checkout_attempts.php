<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_checkout_attempts', function (Blueprint $table): void {
            $table->ulid('cart_id')->after('customer_id');
            $table->ulid('delivery_address_id')->after('cart_id');
            $table->index(['customer_id', 'cart_id'], 'sales_checkout_customer_cart_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales_checkout_attempts', function (Blueprint $table): void {
            $table->dropIndex('sales_checkout_customer_cart_index');
            $table->dropColumn(['cart_id', 'delivery_address_id']);
        });
    }
};
