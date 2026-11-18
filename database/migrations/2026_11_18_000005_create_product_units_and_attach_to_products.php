<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table): void {
            $table->ulidPrimary();
            $table->string('code', 12)->unique();
            $table->string('label', 80);
            $table->string('dimension', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->ulid('unit_id')->nullable()->after('currency');
            $table->foreign('unit_id')->references('id')->on('product_units')->restrictOnDelete();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->ulid('unit_id')->nullable()->after('price');
            $table->foreign('unit_id')->references('id')->on('product_units')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
        Schema::dropIfExists('product_units');
    }
};
