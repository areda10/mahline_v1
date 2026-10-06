<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Relations;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Cooperatives\Models\CooperativeSettings;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CooperativeSettingsRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_has_one_settings(): void
    {
        $cooperative = Cooperative::factory()->create();

        $relation = $cooperative->settings();

        $this->assertInstanceOf(
            HasOne::class,
            $relation,
        );

        $this->assertSame(
            'cooperative_id',
            $relation->getForeignKeyName(),
        );

        $this->assertInstanceOf(
            CooperativeSettings::class,
            $relation->getRelated(),
        );
    }

    public function test_cooperative_settings_belongs_to_cooperative(): void
    {
        $settings = CooperativeSettings::factory()->make();

        $relation = $settings->cooperative();

        $this->assertInstanceOf(
            BelongsTo::class,
            $relation,
        );

        $this->assertSame(
            'cooperative_id',
            $relation->getForeignKeyName(),
        );

        $this->assertInstanceOf(
            Cooperative::class,
            $relation->getRelated(),
        );
    }

    public function test_cooperative_can_retrieve_its_settings(): void
    {
        $cooperative = Cooperative::factory()->create();

        $settings = CooperativeSettings::factory()->create([
            'cooperative_id' => $cooperative->id,
        ]);

        $cooperative->load('settings');

        $this->assertTrue(
            $cooperative->settings->is($settings),
        );
    }

    public function test_settings_can_retrieve_its_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $settings = CooperativeSettings::factory()->create([
            'cooperative_id' => $cooperative->id,
        ]);

        $settings->load('cooperative');

        $this->assertTrue(
            $settings->cooperative->is($cooperative),
        );
    }

    public function test_cooperative_has_only_one_settings_record(): void
    {
        $cooperative = Cooperative::factory()->create();

        CooperativeSettings::factory()->create([
            'cooperative_id' => $cooperative->id,
        ]);

        $this->assertDatabaseCount(
            'cooperative_settings',
            1,
        );

        $this->assertCount(
            1,
            $cooperative->settings()->get(),
        );
    }

    public function test_settings_relation_uses_cooperative_id(): void
    {
        $cooperative = Cooperative::factory()->create();

        $settings = CooperativeSettings::factory()->create([
            'cooperative_id' => $cooperative->id,
        ]);

        $this->assertSame(
            $cooperative->id,
            $settings->cooperative_id,
        );

        $this->assertTrue(
            $settings->cooperative->is($cooperative),
        );
    }
}