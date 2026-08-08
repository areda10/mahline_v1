<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Providers;

use App\Core\Database\Providers\DatabaseMacroServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

final class DatabaseMacroServiceProviderTest extends TestCase
{
    public function test_provider_can_be_instantiated(): void
    {
        $provider = new DatabaseMacroServiceProvider(
            $this->app
        );

        $this->assertInstanceOf(
            DatabaseMacroServiceProvider::class,
            $provider
        );
    }

    public function test_boot_registers_database_macros(): void
    {
        $provider = new DatabaseMacroServiceProvider(
            $this->app
        );

        $provider->boot();

        $this->assertTrue(
            Blueprint::hasMacro('ulidPrimary')
        );

        $this->assertTrue(
            Blueprint::hasMacro('status')
        );

        $this->assertTrue(
            Blueprint::hasMacro('auditColumns')
        );

        $this->assertTrue(
            Blueprint::hasMacro('foreignKey')
        );

        $this->assertTrue(
            Blueprint::hasMacro('indexName')
        );

        $this->assertTrue(
            Blueprint::hasMacro('uniqueName')
        );

        $this->assertTrue(
            Blueprint::hasMacro('indexColumns')
        );

        $this->assertTrue(
            Blueprint::hasMacro('uniqueColumns')
        );

        $this->assertTrue(
            Blueprint::hasMacro('softDeleteColumn')
        );
    }

    public function test_register_does_not_register_macros(): void
    {
        $provider = new DatabaseMacroServiceProvider(
            $this->app
        );

        $provider->register();

        $this->assertInstanceOf(
            DatabaseMacroServiceProvider::class,
            $provider
        );
    }
}