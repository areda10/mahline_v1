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
        Schema::create('login_histories', function (Blueprint $table): void {
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
             * The user concerned by the authentication event.
             *
             * Nullable because an authentication attempt may concern
             * an email address that does not belong to an existing user.
             */
            $table->char('user_id', 26)->nullable();

            /*
             * ============================================================
             * EMAIL
             * ============================================================
             *
             * The email used during the authentication attempt.
             *
             * This allows failed attempts to be recorded even when
             * no corresponding User record exists.
             */
            $table->string('email', 255);

            /*
             * ============================================================
             * AUTHENTICATION RESULT
             * ============================================================
             *
             * Examples:
             *
             * - success
             * - failed
             * - logout
             * - session_revoked
             */
            $table->string('event', 50);

            /*
             * ============================================================
             * FAILURE / REVOCATION REASON
             * ============================================================
             *
             * Examples:
             *
             * - invalid_credentials
             * - inactive_account
             * - unknown_user
             * - logout
             * - new_login
             * - security
             * - expired
             */
            $table->string('reason', 100)->nullable();

            /*
             * ============================================================
             * SESSION
             * ============================================================
             *
             * References the authentication session when one exists.
             *
             * Nullable because failed authentication does not create
             * an authentication session.
             */
            $table->char('authentication_session_id', 26)->nullable();

            /*
             * ============================================================
             * CLIENT INFORMATION
             * ============================================================
             *
             * Used for security auditing and login tracking.
             */
            $table->string('ip_address', 45)->nullable();

            $table->text('user_agent')->nullable();

            $table->string('browser', 100)->nullable();

            $table->string('device', 100)->nullable();

            /*
             * ============================================================
             * AUTHENTICATION DATE
             * ============================================================
             */
            $table->timestamp('occurred_at');

            /*
             * ============================================================
             * AUDIT ACTORS
             * ============================================================
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
             */
            $table->softDeletes();

            /*
             * ============================================================
             * FOREIGN KEYS
             * ============================================================
             */

            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table
                ->foreign('authentication_session_id')
                ->references('id')
                ->on('authentication_sessions')
                ->nullOnDelete();

            /*
             * ============================================================
             * INDEXES
             * ============================================================
             */

            $table->index('user_id');

            $table->index('email');

            $table->index('event');

            $table->index('reason');

            $table->index('authentication_session_id');

            $table->index('occurred_at');

            /*
             * Useful for retrieving a user's authentication history
             * in chronological order.
             */
            $table->index([
                'user_id',
                'occurred_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};