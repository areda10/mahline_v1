<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize every existing API token to the fixed MAHLINE lifetime.
     */
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunk(500, function ($tokens): void {
                foreach ($tokens as $token) {
                    $expiresAt = CarbonImmutable::parse($token->created_at)
                        ->addDays(90);

                    DB::table('personal_access_tokens')
                        ->where('id', $token->id)
                        ->update(['expires_at' => $expiresAt]);
                }
            });
    }

    public function down(): void
    {
        // Expiration dates are security data; rolling them back to nullable
        // would reintroduce permanent API credentials.
    }
};
