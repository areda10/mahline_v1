<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('variants_product_active_label_unique');
            $table->dropColumn('active_normalized_label');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('active_normalized_label', 180)
                ->nullable()
                ->storedAs(
                    'CASE WHEN is_active = 1 AND deleted_at IS NULL THEN normalized_label ELSE NULL END'
                );

            $table->unique(
                ['product_id', 'active_normalized_label'],
                'variants_product_active_label_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('variants_product_active_label_unique');
            $table->dropColumn('active_normalized_label');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('active_normalized_label', 180)
                ->nullable()
                ->storedAs(
                    'CASE WHEN deleted_at IS NULL THEN normalized_label ELSE NULL END'
                );

            $table->unique(
                ['product_id', 'active_normalized_label'],
                'variants_product_active_label_unique',
            );
        });
    }
};
