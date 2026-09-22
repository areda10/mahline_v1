<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('known_devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->ulid('user_id');

            $table->string('device', 100);

            $table->timestamps();

            $table->unique(
                ['user_id', 'device'],
                'known_devices_user_id_device_unique',
            );

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('known_devices');
    }
};
