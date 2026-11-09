<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Password;

use App\Domains\Identity\Password\Rules\PasswordPolicy;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_unicode_and_spaces_when_all_character_classes_are_present(): void
    {
        PasswordPolicy::validate('Été Mahline 2026!');
        $this->assertTrue(true);
    }

    public function test_it_rejects_a_password_without_a_special_character(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validate('MahlineSecure2026');
    }

    public function test_it_rejects_a_password_containing_the_users_first_name(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Reda',
            'last_name' => 'Example',
            'email' => 'reda@example.test',
        ]);

        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validate('RedaSecure2026!', $user);
    }
}
