<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->timestamp('revoked_at')->nullable()->after('expires_at');
            $table->index('revoked_at', 'personal_access_tokens_revoked_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropIndex('personal_access_tokens_revoked_at_index');
            $table->dropColumn('revoked_at');
        });
    }
};
