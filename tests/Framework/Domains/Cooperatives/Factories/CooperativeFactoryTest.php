<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Factories;

use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use Database\Factories\CooperativeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CooperativeFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_factory_creates_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->assertInstanceOf(
            Cooperative::class,
            $cooperative,
        );

        $this->assertDatabaseHas('cooperatives', [
            'id' => $cooperative->id,
        ]);
    }

    public function test_cooperative_factory_is_bound_to_cooperative_model(): void
    {
        $factory = CooperativeFactory::new();

        $this->assertSame(
            Cooperative::class,
            $factory->modelName(),
        );
    }

    public function test_factory_generates_required_attributes(): void
    {
        $cooperative = Cooperative::factory()->make();

        $this->assertNotEmpty($cooperative->name);
        $this->assertNotEmpty($cooperative->rib);
        $this->assertNotEmpty($cooperative->address_id);
        $this->assertNotEmpty($cooperative->phone);
        $this->assertNotEmpty($cooperative->email);
        $this->assertNotEmpty($cooperative->ice);
    }

    public function test_factory_generates_valid_address_ulid(): void
    {
        $cooperative = Cooperative::factory()->make();

        $this->assertTrue(
            Str::isUlid($cooperative->address_id),
        );
    }

    public function test_factory_uses_pending_status_by_default(): void
    {
        $cooperative = Cooperative::factory()->make();

        $this->assertSame(
            CooperativeStatus::PENDING,
            $cooperative->status,
        );
    }

    public function test_factory_can_create_active_cooperative(): void
    {
        $cooperative = Cooperative::factory()
            ->active()
            ->make();

        $this->assertSame(
            CooperativeStatus::ACTIVE,
            $cooperative->status,
        );
    }

    public function test_factory_can_create_suspended_cooperative(): void
    {
        $cooperative = Cooperative::factory()
            ->suspended()
            ->make();

        $this->assertSame(
            CooperativeStatus::SUSPENDED,
            $cooperative->status,
        );
    }

    public function test_factory_can_create_inactive_cooperative(): void
    {
        $cooperative = Cooperative::factory()
            ->inactive()
            ->make();

        $this->assertSame(
            CooperativeStatus::INACTIVE,
            $cooperative->status,
        );
    }

    public function test_factory_can_create_archived_cooperative(): void
    {
        $cooperative = Cooperative::factory()
            ->archived()
            ->make();

        $this->assertSame(
            CooperativeStatus::ARCHIVED,
            $cooperative->status,
        );
    }

    public function test_factory_optional_attributes_can_be_null(): void
    {
        $cooperative = Cooperative::factory()->make([
            'tax_id' => null,
            'rc' => null,
        ]);

        $this->assertNull($cooperative->tax_id);
        $this->assertNull($cooperative->rc);
    }
}