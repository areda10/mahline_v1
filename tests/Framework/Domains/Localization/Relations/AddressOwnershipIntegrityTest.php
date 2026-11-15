<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Relations;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Countries\Models\Country;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AddressOwnershipIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_defines_an_address_relationship(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->getKey(),
            'cooperative_id' => null,
        ]);

        self::assertSame($address->getKey(), $user->address->getKey());
        self::assertSame($user->getKey(), $address->user->getKey());
    }

    public function test_cooperative_defines_an_address_relationship_owned_by_cooperative(): void
    {
        $cooperative = Cooperative::factory()->create();
        $address = $cooperative->address;

        self::assertInstanceOf(Address::class, $address);
        self::assertNull($address->user_id);
        self::assertSame($cooperative->getKey(), $address->cooperative->getKey());
    }

    public function test_two_users_can_have_identical_physical_address_values_without_sharing_the_record(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $addressAttributes = [
            'number' => '12',
            'street' => 'Rue des Fleurs',
            'floor' => null,
            'city' => 'Casablanca',
            'region' => 'Casablanca-Settat',
            'postal_code' => '20000',
            'country_id' => Country::factory()->create()->getKey(),
        ];

        $firstAddress = Address::factory()->create($addressAttributes + [
            'user_id' => $firstUser->getKey(),
            'cooperative_id' => null,
        ]);

        $secondAddress = Address::factory()->create($addressAttributes + [
            'user_id' => $secondUser->getKey(),
            'cooperative_id' => null,
        ]);

        self::assertNotSame($firstAddress->getKey(), $secondAddress->getKey());
        self::assertSame($firstAddress->street, $secondAddress->street);
        self::assertSame($firstAddress->number, $secondAddress->number);
        self::assertSame($firstAddress->city, $secondAddress->city);
        self::assertSame($firstUser->getKey(), $firstAddress->user_id);
        self::assertSame($secondUser->getKey(), $secondAddress->user_id);
    }

    public function test_an_address_cannot_be_created_without_an_owner(): void
    {
        $this->expectException(QueryException::class);

        DB::table('addresses')->insert($this->addressRow([
            'user_id' => null,
            'cooperative_id' => null,
        ]));
    }

    public function test_an_address_cannot_belong_to_a_user_and_a_cooperative_at_the_same_time(): void
    {
        $user = User::factory()->create();
        $cooperative = Cooperative::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('addresses')->insert($this->addressRow([
            'user_id' => $user->getKey(),
            'cooperative_id' => $cooperative->getKey(),
        ]));
    }

    public function test_a_user_can_own_only_one_address(): void
    {
        $user = User::factory()->create();

        Address::factory()->create([
            'user_id' => $user->getKey(),
            'cooperative_id' => null,
        ]);

        $this->expectException(QueryException::class);

        Address::factory()->create([
            'user_id' => $user->getKey(),
            'cooperative_id' => null,
        ]);
    }

    public function test_a_cooperative_can_own_only_one_address(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->expectException(QueryException::class);

        Address::factory()->create([
            'user_id' => null,
            'cooperative_id' => $cooperative->getKey(),
        ]);
    }

    /**
     * @param array{user_id: string|null, cooperative_id: string|null} $owner
     * @return array<string, mixed>
     */
    private function addressRow(array $owner): array
    {
        return [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'number' => null,
            'street' => 'Rue de Test',
            'floor' => null,
            'address_line_1' => 'Rue de Test',
            'address_line_2' => null,
            'postal_code' => '20000',
            'city' => 'Casablanca',
            'region' => null,
            'state' => null,
            'country_id' => Country::factory()->create()->getKey(),
            'latitude' => null,
            'longitude' => null,
            'user_id' => $owner['user_id'],
            'cooperative_id' => $owner['cooperative_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
