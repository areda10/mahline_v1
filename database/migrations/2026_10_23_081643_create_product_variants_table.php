<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'product_variants',
            function (Blueprint $table): void {
                $table->ulidPrimary();

                $table->ulid('cooperative_id');
                $table->ulid('product_id');

                $table->string('name', 180);
                $table->string('normalized_label', 180);

                $table->string('sku', 100)->nullable();
                $table->json('attributes')->nullable();

                $table->boolean('is_active')->default(true);

                $table->auditActorColumns();
                $table->timestamps();
                $table->softDeletes();

                // L'unicité du libellé s'applique seulement
                // aux variantes actives et non supprimées.
                $table->string('active_normalized_label', 180)
                    ->nullable()
                    ->storedAs(
                        'CASE WHEN deleted_at IS NULL AND is_active = 1 '
                        .'THEN normalized_label ELSE NULL END',
                    );

                $table->unique(
                    ['product_id', 'active_normalized_label'],
                    'product_variants_active_label_unique',
                );

                // Un SKU renseigné est unique parmi les variantes
                // non supprimées d'une même coopérative.
                $table->string('active_sku', 100)
                    ->nullable()
                    ->storedAs(
                        'CASE WHEN deleted_at IS NULL THEN sku ELSE NULL END',
                    );

                $table->unique(
                    ['cooperative_id', 'active_sku'],
                    'product_variants_cooperative_sku_unique',
                );

                $table->index(
                    ['product_id', 'is_active'],
                    'product_variants_product_active_index',
                );

                $table->foreign(
                    ['cooperative_id', 'product_id'],
                    'product_variants_cooperative_product_foreign',
                )
                    ->references(['cooperative_id', 'id'])
                    ->on('products')
                    ->restrictOnDelete();
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};