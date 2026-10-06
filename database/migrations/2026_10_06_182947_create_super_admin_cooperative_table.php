<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admin_cooperative', function (Blueprint $table): void {
            $table->ulidPrimary();

            $table->foreignUlid('super_admin_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignUlid('cooperative_id')
                ->constrained('cooperatives')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->unique([
                'super_admin_id',
                'cooperative_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admin_cooperative');
    }
};