<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_product', function (Blueprint $table): void {
            $table->ulid('catalog_id');
            $table->ulid('product_id');

            $table->timestamps();

            $table->primary(
                ['catalog_id', 'product_id'],
                'catalog_product_primary',
            );

            $table->foreign('catalog_id', 'catalog_product_catalog_id_foreign')
                ->references('id')
                ->on('catalogs')
                ->cascadeOnDelete();

            $table->foreign('product_id', 'catalog_product_product_id_foreign')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_product');
    }
};
