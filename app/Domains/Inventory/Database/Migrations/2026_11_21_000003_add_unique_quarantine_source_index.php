<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_quarantines', function (Blueprint $table): void {
            $table->unique(
                ['stock_id', 'source_type', 'source_id'],
                'inventory_quarantine_source_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_quarantines', function (Blueprint $table): void {
            $table->dropUnique('inventory_quarantine_source_unique');
        });
    }
};
