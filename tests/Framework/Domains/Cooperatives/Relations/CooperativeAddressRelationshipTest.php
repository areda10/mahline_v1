<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Relations;

use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CooperativeAddressRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_defines_an_address_has_one_relationship(): void
    {
        $cooperative = Cooperative::factory()->create();

        self::assertInstanceOf(HasOne::class, $cooperative->address());
    }

    public function test_address_defines_a_cooperative_belongs_to_relationship(): void
    {
        $cooperative = Cooperative::factory()->create();

        self::assertInstanceOf(
            BelongsTo::class,
            $cooperative->address->cooperative(),
        );
    }

    public function test_cooperative_can_retrieve_its_owned_address(): void
    {
        $cooperative = Cooperative::factory()->create();

        self::assertSame(
            $cooperative->getKey(),
            $cooperative->address->cooperative_id,
        );

        self::assertNull($cooperative->address->user_id);
    }

    public function test_address_can_retrieve_its_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        self::assertSame(
            $cooperative->getKey(),
            $cooperative->address->cooperative->getKey(),
        );
    }

    public function test_address_ownership_uses_cooperative_id_as_foreign_key(): void
    {
        $cooperative = Cooperative::factory()->create();

        self::assertSame(
            $cooperative->getKey(),
            $cooperative->address->cooperative_id,
        );
    }
}
