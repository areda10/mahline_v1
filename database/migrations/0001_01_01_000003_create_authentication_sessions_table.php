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
             * ============================================================
             * PRIMARY KEY
             * ============================================================
             *
             * All MAHLINE domain models use ULIDs.
             */
            $table->char('id', 26)->primary();

            /*
             * ============================================================
             * USER
             * ============================================================
             *
             * The authenticated user associated with this session.
             *
             * One user can have multiple historical sessions,
             * but only ONE session can be active at a time.
             */
            $table->char('user_id', 26);

            /*
             * ============================================================
             * LARAVEL SESSION
             * ============================================================
             *
             * Identifier of the Laravel authentication session.
             *
             * It must be globally unique.
             */
            $table->string('session_id', 255)->unique();

            /*
             * ============================================================
             * DEVICE / CLIENT INFORMATION
             * ============================================================
             *
             * These fields allow MAHLINE to keep track of the
             * device/browser used during authentication.
             *
             * Example:
             *
             * Android + Firefox
             * iPhone + Safari
             */
            $table->string('ip_address', 45)->nullable();

            $table->text('user_agent')->nullable();

            $table->string('browser', 100)->nullable();

            $table->string('device', 100)->nullable();

            /*
             * ============================================================
             * AUTHENTICATION LIFECYCLE
             * ============================================================
             */

            /*
             * Date/time at which authentication was established.
             */
            $table->timestamp('authenticated_at');

            /*
             * Last activity detected for this session.
             */
            $table->timestamp('last_activity_at')->nullable();

            /*
             * Date/time at which this session was revoked.
             *
             * NULL = session has not been revoked.
             */
            $table->timestamp('revoked_at')->nullable();

            /*
             * ============================================================
             * REVOCATION
             * ============================================================
             *
             * Examples:
             *
             * - new_login
             * - logout
             * - expired
             * - security
             */
            $table->string('revocation_reason', 100)->nullable();

            /*
             * ============================================================
             * AUDIT ACTORS
             * ============================================================
             *
             * Every MAHLINE table includes audit actor columns
             * according to the database architecture standard.
             */
            $table->char('created_by', 26)->nullable();

            $table->char('updated_by', 26)->nullable();

            /*
             * ============================================================
             * TIMESTAMPS
             * ============================================================
             */
            $table->timestamps();

            /*
             * ============================================================
             * SOFT DELETE
             * ============================================================
             *
             * Historical authentication sessions are normally kept.
             *
             * Soft deletion is available for administrative/data
             * lifecycle operations without physically destroying
             * the record immediately.
             */
            $table->softDeletes();

            /*
             * ============================================================
             * FOREIGN KEY
             * ============================================================
             */
            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            /*
             * ============================================================
             * INDEXES
             * ============================================================
             *
             * We deliberately DO NOT use:
             *
             *     $table->unique('user_id');
             *
             * because that would allow only one row per user,
             * including revoked historical sessions.
             *
             * MAHLINE must preserve authentication history.
             */
            $table->index('user_id');

            /*
             * Used to find the current active session.
             */
            $table->index([
                'user_id',
                'revoked_at',
            ]);

            /*
             * Used for session activity management.
             */
            $table->index('last_activity_at');

            /*
             * Used for revocation/history queries.
             */
            $table->index('revoked_at');
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