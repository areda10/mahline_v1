<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table): void {
            $table->ulid('category_id');
            $table->ulid('product_id');
            $table->timestamps();

            $table->primary(
                ['category_id', 'product_id'],
                'category_product_primary',
            );

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};