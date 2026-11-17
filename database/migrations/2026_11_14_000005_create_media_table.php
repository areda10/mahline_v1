<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->ulidPrimary();
            $table->string('mediable_type');
            $table->char('mediable_id', 26);
            $table->string('collection')->default('default');
            $table->string('category');
            $table->string('disk');
            $table->text('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->string('extension', 10);
            $table->unsignedBigInteger('size');
            $table->string('visibility')->default('private');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();

            $table->auditActorColumns();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['mediable_type', 'mediable_id', 'collection', 'deleted_at'],
                'media_mediable_lookup_index',
            );
            $table->index(
                ['mediable_type', 'mediable_id', 'category', 'deleted_at'],
                'media_mediable_category_deleted_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
