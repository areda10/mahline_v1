<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Authentication sessions are stored as security history.
     *
     * MAHLINE policy:
     *
     * ONE USER → MULTIPLE ACTIVE WEB SESSIONS
     *
     * A user may have multiple active sessions at the same time.
     * A new login must not revoke previously active sessions.
     * Each session can be revoked independently.
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
             * AUTHENTICATED USER
             * ============================================================
             *
             * The user who owns this authentication session.
             *
             * A user can have multiple active sessions:
             *
             *     Session A → active
             *     Session B → active
             *     Session C → revoked
             *
             * SessionService manages each session independently.
             */
            $table->char('user_id', 26);

            /*
             * ============================================================
             * LARAVEL SESSION IDENTIFIER
             * ============================================================
             *
             * Identifier of the Laravel application session.
             *
             * It must be unique because two authentication-session
             * records must never reference the same Laravel session.
             */
            $table->string('session_id', 255)->unique();

            /*
             * ============================================================
             * CLIENT INFORMATION
             * ============================================================
             *
             * These fields allow MAHLINE to identify the client used
             * during authentication.
             *
             * Example:
             *
             *     IP       : 192.168.1.10
             *     Browser  : Firefox
             *     Device   : Android
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
             * Date/time at which authentication succeeded.
             */
            $table->timestamp('authenticated_at');

            /*
             * Last activity detected for this authentication session.
             *
             * Nullable because it may not yet have been updated after
             * the initial authentication.
             */
            $table->timestamp('last_activity_at')->nullable();

            /*
             * Date/time at which the session was revoked.
             *
             * NULL = currently active.
             *
             * NOT NULL = revoked.
             */
            $table->timestamp('revoked_at')->nullable();

            /*
             * ============================================================
             * REVOCATION INFORMATION
             * ============================================================
             *
             * Examples:
             *
             *     logout
             *     expired
             *     security
             */
            $table->string('revocation_reason', 100)->nullable();

            /*
             * ============================================================
             * AUDIT ACTORS
             * ============================================================
             *
             * MAHLINE audit standard:
             * all domain tables contain audit actor columns.
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
             * Authentication history must normally remain available.
             *
             * Soft deletion allows technical deletion without
             * physically destroying the historical record.
             */
            $table->softDeletes();

            /*
             * ============================================================
             * FOREIGN KEY
             * ============================================================
             *
             * If a user is permanently removed, all associated
             * authentication sessions are removed as well.
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
             * IMPORTANT:
             *
             * We intentionally DO NOT use:
             *
             *     $table->unique('user_id');
             *
             * because a user must be allowed to have historical
             * authentication sessions.
             *
             * Example:
             *
             *     User 01
             *       ├── Session A → active
             *       ├── Session B → active
             *       └── Session C → revoked
             *
             * Multiple active sessions per user are allowed.
             */
            $table->index('user_id');

            /*
             * Used to find all active sessions belonging to a user.
             */
            $table->index([
                'user_id',
                'revoked_at',
            ]);

            /*
             * Used for activity-related queries.
             */
            $table->index('last_activity_at');

            /*
             * Used for revocation/security queries.
             */
            $table->index('revoked_at');

            /*
             * Used for historical authentication queries.
             */
            $table->index('authenticated_at');
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