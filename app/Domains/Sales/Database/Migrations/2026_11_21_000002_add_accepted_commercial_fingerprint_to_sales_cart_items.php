<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;\nuse Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_cart_items', function (Blueprint $table): void {
            $table->char('accepted_commercial_fingerprint', 64)->nullable()->after('accepted_price_fingerprint');
        });

        DB::table('sales_cart_items')
            ->whereNull('accepted_commercial_fingerprint')
            ->update(['accepted_commercial_fingerprint' => DB::raw('commercial_fingerprint')]);
    }

    public function down(): void
    {
        Schema::table('sales_cart_items', function (Blueprint $table): void {
            $table->dropColumn('accepted_commercial_fingerprint');
        });
    }
};
