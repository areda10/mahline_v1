<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->ulidPrimary();

            $table->string('name', 150);
            $table->string('slug', 180);

            $table->text('description')->nullable();

            $table->ulid('parent_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                'slug',
                'categories_slug_unique',
            );

            $table->index(
                ['is_active', 'sort_order'],
                'categories_active_sort_index',
            );

            $table->index(
                'parent_id',
                'categories_parent_id_index',
            );

            $table->foreign(
                'parent_id',
                'categories_parent_id_foreign',
            )
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};