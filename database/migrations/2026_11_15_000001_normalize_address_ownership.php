<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('number')->nullable()->after('id');
            $table->string('street')->nullable()->after('number');
            $table->string('floor')->nullable()->after('street');
            $table->string('region')->nullable()->after('city');

            $table->foreignUlid('user_id')
                ->nullable()
                ->after('country_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignUlid('cooperative_id')
                ->nullable()
                ->after('user_id')
                ->constrained('cooperatives')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->unique('user_id', 'addresses_user_id_unique');
            $table->unique('cooperative_id', 'addresses_cooperative_id_unique');
        });

        DB::table('addresses')->update([
            'street' => DB::raw('address_line_1'),
            'region' => DB::raw('state'),
        ]);

        DB::table('addresses')
            ->join('cooperatives', 'cooperatives.address_id', '=', 'addresses.id')
            ->whereNull('addresses.cooperative_id')
            ->update([
                'addresses.cooperative_id' => DB::raw('cooperatives.id'),
            ]);

        $orphanedAddressExists = DB::table('addresses')
            ->whereNull('user_id')
            ->whereNull('cooperative_id')
            ->exists();

        if ($orphanedAddressExists) {
            throw new RuntimeException(
                'Address ownership migration aborted: at least one existing address has no user or cooperative owner.'
            );
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE addresses ADD CONSTRAINT addresses_exactly_one_owner_check '
                .'CHECK ((user_id IS NOT NULL AND cooperative_id IS NULL) '
                .'OR (user_id IS NULL AND cooperative_id IS NOT NULL))'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE addresses DROP CHECK addresses_exactly_one_owner_check'
            );
        }

        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropUnique('addresses_user_id_unique');
            $table->dropUnique('addresses_cooperative_id_unique');
            $table->dropForeign(['user_id']);
            $table->dropForeign(['cooperative_id']);
            $table->dropColumn([
                'number',
                'street',
                'floor',
                'region',
                'user_id',
                'cooperative_id',
            ]);
        });
    }
};
