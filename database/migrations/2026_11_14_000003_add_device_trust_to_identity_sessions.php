<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('known_devices', function (Blueprint $table): void {
            $table->dropUnique('known_devices_user_id_device_unique');

            $table->string('browser', 100)->nullable()->after('device');
            $table->char('device_token_hash', 64)->nullable()->unique();
            $table->string('status', 20)->default('non_trusted')->index();
            $table->timestamp('pending_until')->nullable()->index();
            $table->timestamp('trusted_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->index(['user_id', 'status'], 'known_devices_user_status_index');
        });

        Schema::table('authentication_sessions', function (Blueprint $table): void {
            $table->ulid('known_device_id')->nullable()->after('user_id');
            $table->foreign('known_device_id')
                ->references('id')
                ->on('known_devices')
                ->restrictOnDelete();
            $table->index(
                ['known_device_id', 'revoked_at'],
                'authentication_sessions_device_revoked_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('authentication_sessions', function (Blueprint $table): void {
            $table->dropForeign(['known_device_id']);
            $table->dropIndex('authentication_sessions_device_revoked_index');
            $table->dropColumn('known_device_id');
        });

        Schema::table('known_devices', function (Blueprint $table): void {
            $table->dropUnique(['device_token_hash']);
            $table->dropIndex('known_devices_status_index');
            $table->dropIndex('known_devices_pending_until_index');
            $table->dropIndex('known_devices_user_status_index');
            $table->dropColumn([
                'browser',
                'device_token_hash',
                'status',
                'pending_until',
                'trusted_at',
                'last_seen_at',
                'revoked_at',
            ]);

            $table->unique(
                ['user_id', 'device'],
                'known_devices_user_id_device_unique',
            );
        });
    }
};
