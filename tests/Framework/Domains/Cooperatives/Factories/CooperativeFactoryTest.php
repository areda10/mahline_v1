<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Factories;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use Database\Factories\CooperativeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CooperativeFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_factory_creates_a_cooperative_with_its_owned_primary_address(): void
    {
        $cooperative = Cooperative::factory()->create();
        self::assertInstanceOf(Cooperative::class, $cooperative);
        self::assertDatabaseHas('cooperatives', ['id' => $cooperative->getKey()]);
        self::assertNotNull($cooperative->fresh()->address);
        self::assertSame($cooperative->getKey(), $cooperative->address->cooperative_id);
        self::assertTrue($cooperative->address->is_primary);
    }

    public function test_cooperative_factory_is_bound_to_cooperative_model(): void
    {
        self::assertSame(Cooperative::class, CooperativeFactory::new()->modelName());
    }

    public function test_factory_generates_required_cooperative_attributes_without_address_foreign_key(): void
    {
        $cooperative = Cooperative::factory()->make();
        self::assertNotEmpty($cooperative->name);
        self::assertNotEmpty($cooperative->rib);
        self::assertNotEmpty($cooperative->phone);
        self::assertNotEmpty($cooperative->email);
        self::assertNotEmpty($cooperative->ice);
        self::assertNull($cooperative->getAttribute('address_id'));
    }

    public function test_factory_uses_pending_status_by_default(): void
    {
        self::assertSame(CooperativeStatus::PENDING, Cooperative::factory()->make()->status);
    }

    public function test_factory_can_create_other_statuses(): void
    {
        self::assertSame(CooperativeStatus::ACTIVE, Cooperative::factory()->active()->make()->status);
        self::assertSame(CooperativeStatus::SUSPENDED, Cooperative::factory()->suspended()->make()->status);
        self::assertSame(CooperativeStatus::INACTIVE, Cooperative::factory()->inactive()->make()->status);
        self::assertSame(CooperativeStatus::ARCHIVED, Cooperative::factory()->archived()->make()->status);
    }

    public function test_optional_attributes_can_be_null(): void
    {
        $cooperative = Cooperative::factory()->make(['tax_id' => null, 'rc' => null]);
        self::assertNull($cooperative->tax_id);
        self::assertNull($cooperative->rc);
    }
}
