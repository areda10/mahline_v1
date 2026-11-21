<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('checkout_idempotency_key', 100)->nullable()->unique();
            $table->char('checkout_request_hash', 64)->nullable();
            $table->json('inventory_excluded_cooperative_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['checkout_idempotency_key']);
            $table->dropColumn([
                'checkout_idempotency_key',
                'checkout_request_hash',
                'inventory_excluded_cooperative_ids',
            ]);
        });
    }
};
