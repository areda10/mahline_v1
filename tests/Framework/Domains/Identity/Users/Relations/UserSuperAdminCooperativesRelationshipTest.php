<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Users\Relations;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class UserSuperAdminCooperativesRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_defines_cooperatives_belongs_to_many_relationship(): void
    {
        $user = new User;

        self::assertInstanceOf(
            BelongsToMany::class,
            $user->cooperatives(),
        );
    }

    public function test_user_cooperatives_relationship_uses_expected_pivot(): void
    {
        $user = new User;

        $relation = $user->cooperatives();

        self::assertSame(
            'super_admin_cooperative',
            $relation->getTable(),
        );

        self::assertSame(
            'super_admin_id',
            $relation->getForeignPivotKeyName(),
        );

        self::assertSame(
            'cooperative_id',
            $relation->getRelatedPivotKeyName(),
        );
    }

    public function test_user_can_retrieve_multiple_assigned_cooperatives(): void
    {
        $user = User::factory()->create();

        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();

        $user->cooperatives()->attach([
            $cooperativeA->id,
            $cooperativeB->id,
        ]);

        $user->load('cooperatives');

        self::assertCount(
            2,
            $user->cooperatives,
        );

        self::assertTrue(
            $user->cooperatives->contains(
                fn (Cooperative $cooperative): bool => $cooperative->is($cooperativeA),
            ),
        );

        self::assertTrue(
            $user->cooperatives->contains(
                fn (Cooperative $cooperative): bool => $cooperative->is($cooperativeB),
            ),
        );
    }

    public function test_cooperative_can_be_assigned_to_only_one_super_admin(): void
    {
        $superAdminA = User::factory()->create();
        $superAdminB = User::factory()->create();
        $cooperative = Cooperative::factory()->create();

        $superAdminA->cooperatives()->attach($cooperative->id);

        $this->expectException(QueryException::class);
        $superAdminB->cooperatives()->attach($cooperative->id);
    }

    public function test_user_can_detach_one_cooperative_without_affecting_others(): void
    {
        $user = User::factory()->create();

        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();

        $user->cooperatives()->attach([
            $cooperativeA->id,
            $cooperativeB->id,
        ]);

        $user->cooperatives()->detach($cooperativeA->id);

        $user->load('cooperatives');

        self::assertCount(
            1,
            $user->cooperatives,
        );

        self::assertFalse(
            $user->cooperatives->contains($cooperativeA),
        );

        self::assertTrue(
            $user->cooperatives->contains($cooperativeB),
        );
    }
}
