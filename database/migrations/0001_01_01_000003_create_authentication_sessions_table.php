<?php

declare(strict_types=1);

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
        Schema::create('authentication_sessions', function (Blueprint $table): void {
            /*
             * Primary key.
             *
             * All MAHLINE domain models use ULIDs.
             */
            $table->char('id', 26)->primary();

            /*
             * Authenticated user.
             *
             * One user can have only one active authentication
             * session. The active session is identified by the
             * unique user_id constraint below.
             */
            $table->char('user_id', 26);

            /*
             * Laravel session identifier.
             */
            $table->string('session_id', 255)->unique();

            /*
             * Authentication lifecycle.
             */
            $table->timestamp('authenticated_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            /*
             * Revocation reason.
             *
             * Examples:
             * - new_login
             * - logout
             * - expired
             * - security
             */
            $table->string('revocation_reason', 100)->nullable();

            /*
             * Audit actors.
             */
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();

            /*
             * Timestamps.
             */
            $table->timestamps();

            /*
             * Soft deletion.
             */
            $table->softDeletes();

            /*
             * Foreign key.
             */
            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            /*
             * ONE USER → ONE AUTHENTICATED SESSION.
             *
             * A new login must replace/revoke the existing session
             * before the new session becomes active.
             */
            $table->unique('user_id');

            /*
             * Query indexes.
             */
            $table->index('revoked_at');
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authentication_sessions');
    }
};
