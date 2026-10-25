<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogs', function (Blueprint $table): void {
            $table->ulidPrimary();

            $table->ulid('cooperative_id');
            $table->string('name', 180);
            $table->string('slug', 200);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->auditActorColumns();

            $table->timestamps();
            $table->softDeletes();

            $table->string('active_slug', 200)
                ->nullable()
                ->storedAs(
                    'CASE WHEN deleted_at IS NULL THEN slug ELSE NULL END'
                );

            $table->unique(
                ['cooperative_id', 'active_slug'],
                'catalogs_cooperative_active_slug_unique'
            );

            $table->index(
                ['cooperative_id', 'is_active'],
                'catalogs_cooperative_active_index'
            );

            $table->foreign('cooperative_id')
                ->references('id')
                ->on('cooperatives')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogs');
    }
};
