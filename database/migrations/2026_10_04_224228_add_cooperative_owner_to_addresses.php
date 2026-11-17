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
        Schema::table('addresses', function (Blueprint $table): void {
            $table->foreignUlid('cooperative_id')
                ->nullable()
                ->after('user_id')
                ->constrained('cooperatives')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->index(['cooperative_id', 'deleted_at'], 'addresses_cooperative_active_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE addresses ADD CONSTRAINT addresses_exactly_one_owner_check '
                .'CHECK ((user_id IS NOT NULL AND cooperative_id IS NULL) '
                .'OR (user_id IS NULL AND cooperative_id IS NOT NULL))',
            );

            DB::statement(
                'ALTER TABLE addresses ADD COLUMN active_primary_user_id CHAR(26) '
                .'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL AND is_primary = 1 '
                .'THEN user_id ELSE NULL END) STORED',
            );
            DB::statement(
                'ALTER TABLE addresses ADD UNIQUE INDEX addresses_one_primary_per_user_unique (active_primary_user_id)',
            );
            DB::statement(
                'ALTER TABLE addresses ADD COLUMN active_primary_cooperative_id CHAR(26) '
                .'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL AND is_primary = 1 '
                .'THEN cooperative_id ELSE NULL END) STORED',
            );
            DB::statement(
                'ALTER TABLE addresses ADD UNIQUE INDEX addresses_one_primary_per_cooperative_unique (active_primary_cooperative_id)',
            );
            DB::statement(
                'ALTER TABLE addresses ADD COLUMN active_cooperative_id CHAR(26) '
                .'GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN cooperative_id ELSE NULL END) STORED',
            );
            DB::statement(
                'ALTER TABLE addresses ADD UNIQUE INDEX addresses_one_active_address_per_cooperative_unique (active_cooperative_id)',
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE addresses DROP CHECK addresses_exactly_one_owner_check');
            DB::statement('ALTER TABLE addresses DROP INDEX addresses_one_primary_per_user_unique');
            DB::statement('ALTER TABLE addresses DROP INDEX addresses_one_primary_per_cooperative_unique');
            DB::statement('ALTER TABLE addresses DROP INDEX addresses_one_active_address_per_cooperative_unique');
            DB::statement('ALTER TABLE addresses DROP COLUMN active_primary_user_id');
            DB::statement('ALTER TABLE addresses DROP COLUMN active_primary_cooperative_id');
            DB::statement('ALTER TABLE addresses DROP COLUMN active_cooperative_id');
        }

        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropIndex('addresses_cooperative_active_idx');
            $table->dropForeign(['cooperative_id']);
            $table->dropColumn('cooperative_id');
        });
    }
};
