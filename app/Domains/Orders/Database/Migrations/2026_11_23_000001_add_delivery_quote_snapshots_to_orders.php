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
            $table->json('delivery_snapshot')->nullable();
        });

        Schema::table('cooperative_orders', function (Blueprint $table): void {
            $table->json('delivery_quote_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_orders', function (Blueprint $table): void {
            $table->dropColumn('delivery_quote_snapshot');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('delivery_snapshot');
        });
    }
};
