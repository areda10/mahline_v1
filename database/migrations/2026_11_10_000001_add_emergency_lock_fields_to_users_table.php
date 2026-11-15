<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('emergency_locked_at')->nullable()->after('must_change_password');
            $table->string('emergency_lock_reason', 500)->nullable()->after('emergency_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['emergency_locked_at', 'emergency_lock_reason']);
        });
    }
};
