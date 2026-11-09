<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_password_histories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->string('password_hash');
            $table->timestamp('created_at');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_password_histories');
    }
};
