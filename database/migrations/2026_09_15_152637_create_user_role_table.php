<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role', function (Blueprint $table): void {
            $table->ulid('user_id');
            $table->ulid('role_id');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role');
    }
};