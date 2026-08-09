<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Providers;

use App\Core\Database\Macros\BlueprintMacros;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

final class DatabaseMacroServiceProviderTest extends TestCase
{
    public function test_database_macro_service_provider_registers_blueprint_macros(): void
    {
        BlueprintMacros::register();

        $this->assertTrue(Blueprint::hasMacro('ulidPrimary'));
        $this->assertTrue(Blueprint::hasMacro('auditColumns'));
        $this->assertTrue(Blueprint::hasMacro('auditActorColumns'));
        $this->assertTrue(Blueprint::hasMacro('foreignKey'));
        $this->assertTrue(Blueprint::hasMacro('indexName'));
        $this->assertTrue(Blueprint::hasMacro('uniqueName'));
        $this->assertTrue(Blueprint::hasMacro('indexColumns'));
        $this->assertTrue(Blueprint::hasMacro('uniqueColumns'));
        $this->assertTrue(Blueprint::hasMacro('softDeleteColumn'));
        $this->assertTrue(Blueprint::hasMacro('status'));
    }

    public function test_blueprint_macro_registration_is_idempotent(): void
    {
        BlueprintMacros::register();
        BlueprintMacros::register();

        $this->assertTrue(Blueprint::hasMacro('auditActorColumns'));
    }
}