<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('super_admin_cooperative')
            ->select('cooperative_id')
            ->groupBy('cooperative_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                sprintf(
                    'Cannot enforce one super_admin per cooperative: cooperative %s has multiple assignments. Resolve the assignments before rerunning this migration.',
                    $duplicate->cooperative_id,
                ),
            );
        }

        Schema::table('super_admin_cooperative', function (Blueprint $table): void {
            $table->unique(
                'cooperative_id',
                'super_admin_cooperative_single_super_admin_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('super_admin_cooperative', function (Blueprint $table): void {
            $table->dropUnique('super_admin_cooperative_single_super_admin_unique');
        });
    }
};
