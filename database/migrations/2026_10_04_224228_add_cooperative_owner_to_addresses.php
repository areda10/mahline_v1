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
        if (! Schema::hasColumn('addresses', 'cooperative_id')) {
            Schema::table('addresses', function (Blueprint $table): void {
                $table->foreignUlid('cooperative_id')->nullable()->after('user_id');
            });
        }

        $this->ensureRestrictUpdateForeignKey('user_id', 'addresses_user_id_foreign', 'users');
        $this->ensureRestrictUpdateForeignKey('cooperative_id', 'addresses_cooperative_id_foreign', 'cooperatives');

        $this->ensureIndex('addresses_cooperative_active_idx', ['cooperative_id', 'deleted_at']);

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->ensureTrigger(
            'addresses_exactly_one_owner_before_insert',
            <<<'SQL'
                CREATE TRIGGER addresses_exactly_one_owner_before_insert
                BEFORE INSERT ON addresses
                FOR EACH ROW
                BEGIN
                    IF ((NEW.user_id IS NULL) = (NEW.cooperative_id IS NULL)) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'An address must belong to exactly one user or cooperative.';
                    END IF;
                END
            SQL,
        );

        $this->ensureTrigger(
            'addresses_exactly_one_owner_before_update',
            <<<'SQL'
                CREATE TRIGGER addresses_exactly_one_owner_before_update
                BEFORE UPDATE ON addresses
                FOR EACH ROW
                BEGIN
                    IF ((NEW.user_id IS NULL) = (NEW.cooperative_id IS NULL)) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'An address must belong to exactly one user or cooperative.';
                    END IF;
                END
            SQL,
        );

        $this->ensureGeneratedColumn(
            'active_primary_user_id',
            'CASE WHEN deleted_at IS NULL AND is_primary = 1 THEN user_id ELSE NULL END',
        );
        $this->ensureIndex('addresses_one_primary_per_user_unique', ['active_primary_user_id'], true);

        $this->ensureGeneratedColumn(
            'active_primary_cooperative_id',
            'CASE WHEN deleted_at IS NULL AND is_primary = 1 THEN cooperative_id ELSE NULL END',
        );
        $this->ensureIndex('addresses_one_primary_per_cooperative_unique', ['active_primary_cooperative_id'], true);

        $this->ensureGeneratedColumn(
            'active_cooperative_id',
            'CASE WHEN deleted_at IS NULL THEN cooperative_id ELSE NULL END',
        );
        $this->ensureIndex('addresses_one_active_address_per_cooperative_unique', ['active_cooperative_id'], true);
    }

    private function ensureRestrictUpdateForeignKey(string $column, string $constraint, string $referencedTable): void
    {
        $updateRule = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'addresses')
            ->where('CONSTRAINT_NAME', $constraint)
            ->value('UPDATE_RULE');

        if ($updateRule !== null && strtoupper((string) $updateRule) === 'RESTRICT') {
            return;
        }

        if ($updateRule !== null) {
            DB::statement("ALTER TABLE \`addresses\` DROP FOREIGN KEY \`{$constraint}\`");
        }

        DB::statement(
            "ALTER TABLE \`addresses\`
             ADD CONSTRAINT \`{$constraint}\`
             FOREIGN KEY (\`{$column}\`)
             REFERENCES \`{$referencedTable}\` (\`id\`)
             ON DELETE RESTRICT
             ON UPDATE RESTRICT",
        );
    }

    private function ensureGeneratedColumn(string $column, string $expression): void
    {
        if (Schema::hasColumn('addresses', $column)) {
            return;
        }

        DB::statement(
            "ALTER TABLE \`addresses\`
             ADD COLUMN \`{$column}\` CHAR(26)
             GENERATED ALWAYS AS ({$expression}) STORED",
        );
    }

    private function ensureIndex(string $index, array $columns, bool $unique = false): void
    {
        $exists = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'addresses')
            ->where('INDEX_NAME', $index)
            ->exists();

        if ($exists) {
            return;
        }

        $columnsSql = implode(', ', array_map(
            static fn (string $column): string => "\`{$column}\`",
            $columns,
        ));
        $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';

        DB::statement("ALTER TABLE \`addresses\` ADD {$kind} \`{$index}\` ({$columnsSql})");
    }

    private function ensureTrigger(string $trigger, string $definition): void
    {
        $exists = DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $trigger)
            ->exists();

        if (! $exists) {
            DB::unprepared($definition);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('DROP TRIGGER IF EXISTS addresses_exactly_one_owner_before_insert');
            DB::statement('DROP TRIGGER IF EXISTS addresses_exactly_one_owner_before_update');

            foreach ([
                'addresses_one_primary_per_user_unique',
                'addresses_one_primary_per_cooperative_unique',
                'addresses_one_active_address_per_cooperative_unique',
                'addresses_cooperative_active_idx',
            ] as $index) {
                $this->dropIndexIfExists($index);
            }

            foreach (['active_primary_user_id', 'active_primary_cooperative_id', 'active_cooperative_id'] as $column) {
                if (Schema::hasColumn('addresses', $column)) {
                    Schema::table('addresses', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        $foreignKeyExists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'addresses')
            ->where('CONSTRAINT_NAME', 'addresses_cooperative_id_foreign')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();

        if ($foreignKeyExists) {
            DB::statement('ALTER TABLE \`addresses\` DROP FOREIGN KEY \`addresses_cooperative_id_foreign\`');
        }

        if (Schema::hasColumn('addresses', 'cooperative_id')) {
            Schema::table('addresses', function (Blueprint $table): void {
                $table->dropColumn('cooperative_id');
            });
        }
    }

    private function dropIndexIfExists(string $index): void
    {
        $exists = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'addresses')
            ->where('INDEX_NAME', $index)
            ->exists();

        if ($exists) {
            DB::statement("ALTER TABLE \`addresses\` DROP INDEX \`{$index}\`");
        }
    }
};
