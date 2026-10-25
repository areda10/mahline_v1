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

            // NULL = catégorie globale MAHLINE.
            $table->ulid('cooperative_id')->nullable();

            // NULL = catégorie racine.
            $table->ulid('parent_id')->nullable();

            $table->string('name', 150);
            $table->string('slug', 180);
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->auditActorColumns();
            $table->timestamps();
            $table->softDeletes();

            // Clés calculées pour garantir l'unicité des slugs actifs
            // séparément pour MAHLINE et chaque coopérative.
            $table->string('scope_key', 26)
                ->storedAs("COALESCE(cooperative_id, 'GLOBAL')");

            $table->string('active_slug', 180)
                ->nullable()
                ->storedAs(
                    'CASE WHEN deleted_at IS NULL THEN slug ELSE NULL END'
                );

            $table->unique(
                ['scope_key', 'active_slug'],
                'categories_scope_active_slug_unique',
            );

            $table->index(
                ['cooperative_id', 'is_active', 'sort_order'],
                'categories_scope_active_sort_index',
            );

            $table->index('parent_id', 'categories_parent_index');

            // Conserve le macro MAHLINE si cette migration doit inclure
            // les colonnes d'audit standard du projet.
            // Appelle auditActorColumns() ici si ce macro fait partie
            // du contrat obligatoire de ton projet.

            $table->foreign('cooperative_id')
                ->references('id')
                ->on('cooperatives')
                ->restrictOnDelete();

            $table->foreign('parent_id')
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