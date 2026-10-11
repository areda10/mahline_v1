<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cooperative_settings', function (Blueprint $table): void {
            $table->foreign('cooperative_id')
                ->references('id')
                ->on('cooperatives')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_settings', function (Blueprint $table): void {
            $table->dropForeign(['cooperative_id']);
        });
    }
};