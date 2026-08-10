<?php

declare(strict_types=1);

namespace Tests\Framework\Security\Hashing;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class HashingConfigurationTest extends TestCase
{
    public function test_default_hash_driver_is_bcrypt(): void
    {
        $this->assertSame(
            'bcrypt',
            config('hashing.driver')
        );
    }

    public function test_bcrypt_rounds_are_twelve(): void
    {
        $this->assertSame(
            12,
            (int) config('hashing.bcrypt.rounds')
        );
    }

    public function test_hash_verify_is_enabled(): void
    {
        $this->assertTrue(
            (bool) config('hashing.bcrypt.verify')
        );
    }

    public function test_rehash_on_login_is_enabled(): void
    {
        $this->assertTrue(
            (bool) config('hashing.rehash_on_login')
        );
    }

    public function test_hash_facade_uses_configured_driver(): void
    {
        $password = 'MahlineTestPassword123!';

        $hash = Hash::make($password);

        $this->assertIsString($hash);
        $this->assertNotSame($password, $hash);

        $this->assertTrue(
            Hash::check($password, $hash)
        );
    }

    public function test_bcrypt_hash_is_generated(): void
    {
        $password = 'MahlineTestPassword123!';

        $hash = Hash::make($password);

        $this->assertStringStartsWith(
            '$2',
            $hash
        );
    }

    public function test_hash_needs_rehash_returns_boolean(): void
    {
        $password = 'MahlineTestPassword123!';

        $hash = Hash::make($password);

        $this->assertIsBool(
            Hash::needsRehash($hash)
        );
    }
}
