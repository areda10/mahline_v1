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
        Schema::create('users', function (Blueprint $table): void {
            /*
             * Primary Key
             */
            $table->ulidPrimary();

            /*
             * Identity
             */
            $table->string('first_name');
            $table->string('last_name');
            $table->string('display_name');

            /*
             * Contact
             */
            $table->string('email')->unique();
            $table->string('telephone');

            /*
             * Authentication
             */
            $table->string('password');
            $table->rememberToken();

            /*
             * User Status
             */
            $table->status();

            /*
             * Localization
             */
            $table->string('locale')->default('fr');
            $table->string('timezone')->default('UTC');

            /*
             * Email verification
             */
            $table->timestamp('email_verified_at')->nullable();

            /*
             * Audit
             */
            $table->auditColumns();

            /*
             * Soft Delete
             */
            $table->softDeleteColumn();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};