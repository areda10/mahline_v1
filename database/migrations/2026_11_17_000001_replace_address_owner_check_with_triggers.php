<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Upgrade databases where the previous migration already installed
        // the incompatible CHECK constraint.
        $checkExists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'addresses')
            ->where('CONSTRAINT_NAME', 'addresses_exactly_one_owner_check')
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->exists();

        if ($checkExists) {
            DB::statement('ALTER TABLE addresses DROP CHECK addresses_exactly_one_owner_check');
        }

        $insertTriggerExists = DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', 'addresses_exactly_one_owner_before_insert')
            ->exists();

        if (! $insertTriggerExists) {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER addresses_exactly_one_owner_before_insert
                BEFORE INSERT ON addresses
                FOR EACH ROW
                BEGIN
                    IF ((NEW.user_id IS NULL) = (NEW.cooperative_id IS NULL)) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'An address must belong to exactly one user or cooperative.';
                    END IF;
                END
            SQL);
        }

        $updateTriggerExists = DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', 'addresses_exactly_one_owner_before_update')
            ->exists();

        if (! $updateTriggerExists) {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER addresses_exactly_one_owner_before_update
                BEFORE UPDATE ON addresses
                FOR EACH ROW
                BEGIN
                    IF ((NEW.user_id IS NULL) = (NEW.cooperative_id IS NULL)) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'An address must belong to exactly one user or cooperative.';
                    END IF;
                END
            SQL);
        }
    }

    public function down(): void
    {
        throw new LogicException(
            'This migration is intentionally irreversible: MySQL cannot safely restore the previous CHECK constraint while cooperative_id participates in its foreign key referential action. Keep the ownership triggers in place.',
        );
    }
};
