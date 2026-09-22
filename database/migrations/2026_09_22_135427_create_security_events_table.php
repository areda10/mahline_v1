<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('user_id')->nullable();

            $table->string('event', 100);
            $table->string('reason', 255)->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('device', 100)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index('user_id');
            $table->index('event');
            $table->index('occurred_at');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};