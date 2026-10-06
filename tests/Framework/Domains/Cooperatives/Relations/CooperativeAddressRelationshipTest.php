<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Relations;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Localization\Models\Address;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CooperativeAddressRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_defines_an_address_belongs_to_relationship(): void
    {
        $address = Address::factory()->create();

        $cooperative = Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);

        self::assertInstanceOf(
            BelongsTo::class,
            $cooperative->address(),
        );
    }

    public function test_address_defines_a_cooperative_has_one_relationship(): void
    {
        $address = Address::factory()->create();

        self::assertInstanceOf(
            HasOne::class,
            $address->cooperative(),
        );
    }

    public function test_cooperative_can_retrieve_its_address(): void
    {
        $address = Address::factory()->create();

        $cooperative = Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);

        self::assertSame(
            $address->id,
            $cooperative->address->id,
        );
    }

    public function test_address_can_retrieve_its_cooperative(): void
    {
        $address = Address::factory()->create();

        $cooperative = Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);

        self::assertSame(
            $cooperative->id,
            $address->cooperative->id,
        );
    }

    public function test_cooperative_uses_address_id_as_foreign_key(): void
    {
        $address = Address::factory()->create();

        $cooperative = Cooperative::factory()->create([
            'address_id' => $address->id,
        ]);

        self::assertSame(
            $address->id,
            $cooperative->address_id,
        );
    }
}