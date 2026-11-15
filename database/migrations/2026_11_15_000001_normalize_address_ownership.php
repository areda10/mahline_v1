<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        $hasUnlinkedAddress = DB::table('addresses')
            ->leftJoin('cooperatives', 'cooperatives.address_id', '=', 'addresses.id')
            ->whereNull('cooperatives.id')
            ->exists();

        if ($hasUnlinkedAddress) {
            throw new RuntimeException(
                'Address ownership migration aborted: every legacy address must belong to a cooperative.',
            );
        }

        $hasSharedAddress = DB::table('cooperatives')
            ->select('address_id')
            ->groupBy('address_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasSharedAddress) {
            throw new RuntimeException(
                'Address ownership migration aborted: a legacy address is assigned to multiple cooperatives.',
            );
        }

        $hasNonMoroccanAddress = DB::table('addresses')
            ->join('countries', 'countries.id', '=', 'addresses.country_id')
            ->where(function ($query): void {
                $query->where('countries.code', '<>', 'MA')
                    ->orWhere('countries.is_active', false);
            })
            ->exists();

        if ($hasNonMoroccanAddress) {
            throw new RuntimeException(
                'Address ownership migration aborted: V1 requires every existing address to use active country MA.',
            );
        }

        Schema::table('cooperatives', function (Blueprint $table): void {
            $table->ulid('address_id')->nullable()->change();
        });

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
            ->update([
                'addresses.cooperative_id' => DB::raw('cooperatives.id'),
            ]);

        $hasAddressWithoutStreet = DB::table('addresses')
            ->whereNull('street')
            ->orWhere('street', '')
            ->exists();

        if ($hasAddressWithoutStreet) {
            throw new RuntimeException(
                'Address ownership migration aborted: at least one address has no street.',
            );
        }

        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('street')->nullable(false)->change();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE addresses ADD CONSTRAINT addresses_exactly_one_owner_check '
                .'CHECK ((user_id IS NOT NULL AND cooperative_id IS NULL) '
                .'OR (user_id IS NULL AND cooperative_id IS NOT NULL))',
            );
        }
    }

    public function down(): void
    {
        DB::table('cooperatives')
            ->join('addresses', 'addresses.cooperative_id', '=', 'cooperatives.id')
            ->update([
                'cooperatives.address_id' => DB::raw('addresses.id'),
            ]);

        $hasCooperativeWithoutAddress = DB::table('cooperatives')
            ->whereNull('address_id')
            ->exists();

        if ($hasCooperativeWithoutAddress) {
            throw new RuntimeException(
                'Address ownership rollback aborted: every cooperative must have an address.',
            );
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE addresses DROP CHECK addresses_exactly_one_owner_check',
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

        Schema::table('cooperatives', function (Blueprint $table): void {
            $table->ulid('address_id')->nullable(false)->change();
        });
    }
};
