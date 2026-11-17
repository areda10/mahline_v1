<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Users\Relations;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserCooperativeRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_defines_cooperative_belongs_to_relationship(): void
    {
        $user = new User;

        self::assertInstanceOf(
            BelongsTo::class,
            $user->cooperative(),
        );
    }

    public function test_user_cooperative_relationship_uses_cooperative_id(): void
    {
        $user = new User;

        $relation = $user->cooperative();

        self::assertSame(
            'cooperative_id',
            $relation->getForeignKeyName(),
        );

        self::assertSame(
            'id',
            $relation->getOwnerKeyName(),
        );
    }

    public function test_user_can_retrieve_its_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $user = User::factory()->create([
            'cooperative_id' => $cooperative->id,
        ]);

        self::assertTrue($user->relationLoaded('cooperative') === false);

        self::assertTrue(
            $user->cooperative->is($cooperative),
        );
    }

    public function test_multiple_users_can_belong_to_same_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();

        $users = User::factory()
            ->count(3)
            ->create([
                'cooperative_id' => $cooperative->id,
            ]);

        self::assertCount(3, $users);

        foreach ($users as $user) {
            self::assertTrue(
                $user->cooperative->is($cooperative),
            );
        }
    }

    public function test_user_belongs_to_assigned_cooperative(): void
    {
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();

        $user = User::factory()->create([
            'cooperative_id' => $cooperativeA->id,
        ]);

        self::assertTrue(
            $user->cooperative->is($cooperativeA),
        );

        self::assertFalse(
            $user->cooperative->is($cooperativeB),
        );
    }
}
