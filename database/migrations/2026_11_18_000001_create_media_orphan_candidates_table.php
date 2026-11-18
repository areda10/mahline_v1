<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_orphan_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 80);
            $table->text('path');
            $table->char('path_hash', 64);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['disk', 'path_hash'], 'media_orphan_disk_hash_unique');
            $table->index(['first_seen_at', 'last_seen_at'], 'media_orphan_age_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_orphan_candidates');
    }
};
