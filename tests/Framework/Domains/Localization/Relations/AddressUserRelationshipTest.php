<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Localization\Relations;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Localization\Models\Address;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AddressUserRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_defines_an_address_has_one_relationship(): void
    {
        $user = User::factory()->create();

        self::assertInstanceOf(HasOne::class, $user->address());
    }

    public function test_address_defines_a_user_belongs_to_relationship(): void
    {
        $address = Address::factory()->create();

        self::assertInstanceOf(BelongsTo::class, $address->user());
    }

    public function test_user_can_retrieve_its_personal_address(): void
    {
        $address = Address::factory()->create();

        self::assertSame($address->getKey(), $address->user->address->getKey());
    }
}
