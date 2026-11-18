<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('price', 12, 2)->nullable()->after('description');
            $table->char('currency', 3)->default('MAD')->after('price');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('price', 12, 2)->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('price');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['price', 'currency']);
        });
    }
};
