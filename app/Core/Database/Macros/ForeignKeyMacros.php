<?php

declare(strict_types=1);

namespace App\Core\Database\Macros;

use App\Core\Enums\Database\ForeignKeyAction;
use Illuminate\Database\Schema\Blueprint;

final class ForeignKeyMacros
{
    /**
     * Register MAHLINE foreign-key Blueprint macros.
     */
    public static function register(): void
    {
        if (! Blueprint::hasMacro('foreignKey')) {
            Blueprint::macro(
                'foreignKey',
                function (
                    string $column,
                    string $referencedTable,
                    string $referencedColumn = 'id',
                    ForeignKeyAction $onDelete = ForeignKeyAction::NO_ACTION,
                    ?ForeignKeyAction $onUpdate = null,
                ): void {
                    /** @var Blueprint $this */

                    $foreignKey = $this->foreign($column)
                        ->references($referencedColumn)
                        ->on($referencedTable);

                    $deleteAction = match ($onDelete) {
                        ForeignKeyAction::CASCADE => 'cascade',
                        ForeignKeyAction::RESTRICT => 'restrict',
                        ForeignKeyAction::SET_NULL => 'set null',
                        ForeignKeyAction::NO_ACTION => 'no action',
                    };

                    $foreignKey->onDelete($deleteAction);

                    if ($onUpdate !== null) {
                        $updateAction = match ($onUpdate) {
                            ForeignKeyAction::CASCADE => 'cascade',
                            ForeignKeyAction::RESTRICT => 'restrict',
                            ForeignKeyAction::SET_NULL => 'set null',
                            ForeignKeyAction::NO_ACTION => 'no action',
                        };

                        $foreignKey->onUpdate($updateAction);
                    }
                }
            );
        }
    }
}