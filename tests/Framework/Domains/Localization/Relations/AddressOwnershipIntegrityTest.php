<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Relations;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AddressOwnershipIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_own_multiple_addresses_and_primary_address_is_exposed(): void
    {
        $user = User::factory()->create();
        $primary = Address::factory()->forUser($user)->create();
        $secondary = Address::factory()->forUser($user)->state(['is_primary' => false])->create();

        self::assertCount(2, $user->fresh()->addresses);
        self::assertSame($primary->getKey(), $user->fresh()->primaryAddress->getKey());
        self::assertSame($user->getKey(), $secondary->user->getKey());
        self::assertNull($secondary->cooperative_id);
    }

    public function test_cooperative_address_is_owned_by_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $address = $cooperative->fresh()->address;

        self::assertInstanceOf(Address::class, $address);
        self::assertNull($address->user_id);
        self::assertSame($cooperative->getKey(), $address->cooperative->getKey());
    }

    public function test_address_cannot_be_created_without_exactly_one_owner(): void
    {
        $countryId = Address::factory()->raw()['country_id'];
        $this->expectException(QueryException::class);
        DB::table('addresses')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'street' => 'Rue de Test',
            'city' => 'Casablanca',
            'country_id' => $countryId,
            'user_id' => null,
            'cooperative_id' => null,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_address_cannot_have_two_owners(): void
    {
        $user = User::factory()->create();
        $cooperative = Cooperative::factory()->create();
        $countryId = Address::factory()->raw()['country_id'];

        $this->expectException(QueryException::class);
        DB::table('addresses')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'street' => 'Rue de Test',
            'city' => 'Casablanca',
            'country_id' => $countryId,
            'user_id' => $user->getKey(),
            'cooperative_id' => $cooperative->getKey(),
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_only_one_active_primary_address_per_user_is_allowed_by_database(): void
    {
        $user = User::factory()->create();
        Address::factory()->forUser($user)->create();
        $this->expectException(QueryException::class);
        Address::factory()->forUser($user)->create();
    }
}
