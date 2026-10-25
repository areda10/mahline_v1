<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->ulidPrimary();
            $table->ulid('product_id');

            $table->string('label', 180);
            $table->string('normalized_label', 180);
            $table->string('sku', 100)->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->auditActorColumns();
            $table->timestamps();
            $table->softDeletes();

            $table->string('active_normalized_label', 180)
                ->nullable()
                ->storedAs(
                    'CASE WHEN deleted_at IS NULL THEN normalized_label ELSE NULL END'
                );

            $table->unique(
                ['product_id', 'active_normalized_label'],
                'variants_product_active_label_unique',
            );

            $table->index(
                ['product_id', 'is_active'],
                'variants_product_active_index',
            );

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};