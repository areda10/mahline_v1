<?php

declare(strict_types=1);

namespace Tests\Framework\Security\Services;

use App\Core\Security\Services\PasswordService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class PasswordServiceTest extends TestCase
{
    private PasswordService $passwordService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passwordService = new PasswordService();
    }

    public function test_it_hashes_a_password(): void
    {
        $password = 'MahlineTestPassword123!';

        $hashedPassword = $this->passwordService->hash($password);

        $this->assertIsString($hashedPassword);
        $this->assertNotSame($password, $hashedPassword);
        $this->assertNotEmpty($hashedPassword);
    }

    public function test_hashed_password_can_be_verified(): void
    {
        $password = 'MahlineTestPassword123!';

        $hashedPassword = $this->passwordService->hash($password);

        $this->assertTrue(
            $this->passwordService->verify(
                $password,
                $hashedPassword
            )
        );
    }

    public function test_invalid_password_cannot_be_verified(): void
    {
        $password = 'MahlineTestPassword123!';
        $wrongPassword = 'WrongPassword123!';

        $hashedPassword = $this->passwordService->hash($password);

        $this->assertFalse(
            $this->passwordService->verify(
                $wrongPassword,
                $hashedPassword
            )
        );
    }

    public function test_same_password_generates_different_hashes(): void
    {
        $password = 'MahlineTestPassword123!';

        $firstHash = $this->passwordService->hash($password);
        $secondHash = $this->passwordService->hash($password);

        $this->assertNotSame($firstHash, $secondHash);

        $this->assertTrue(
            $this->passwordService->verify(
                $password,
                $firstHash
            )
        );

        $this->assertTrue(
            $this->passwordService->verify(
                $password,
                $secondHash
            )
        );
    }

    public function test_it_detects_a_hash_that_needs_rehashing(): void
    {
        $password = 'MahlineTestPassword123!';

        $hashedPassword = Hash::make($password);

        $result = $this->passwordService->needsRehash($hashedPassword);

        $this->assertIsBool($result);
    }

    public function test_plain_password_is_never_equal_to_hash(): void
    {
        $password = 'MahlineTestPassword123!';

        $hashedPassword = $this->passwordService->hash($password);

        $this->assertNotSame($password, $hashedPassword);
    }
}

