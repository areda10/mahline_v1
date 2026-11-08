<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Password\Rules;

use App\Domains\Identity\Password\Rules\PasswordPolicy;
use InvalidArgumentException;
use Tests\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function test_valid_password_is_accepted(): void
    {
        PasswordPolicy::validate('Mahline@Password12');

        $this->assertTrue(true);
    }

    public function test_password_must_contain_at_least_12_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validate('MahlineP1!');
    }

    public function test_password_with_exactly_12_characters_is_accepted(): void
    {
        PasswordPolicy::validate('MahlinePas1!');

        $this->assertTrue(true);
    }

    public function test_password_may_contain_up_to_128_characters(): void
    {
        $password = 'Aa1!' . str_repeat('b', 124);

        $this->assertSame(128, mb_strlen($password));

        PasswordPolicy::validate($password);

        $this->assertTrue(true);
    }

    public function test_password_must_not_exceed_128_characters(): void
    {
        $password = 'Aa1!' . str_repeat('b', 125);

        $this->assertSame(129, mb_strlen($password));

        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validate($password);
    }

    public function test_password_must_contain_an_uppercase_letter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validate('mahlinepassword12!');
    }

    public function test_password_must_contain_a_lowercase_letter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validate('MAHLINEPASSWORD12!');
    }

    public function test_password_must_contain_a_digit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validate('MahlinePassword!!');
    }

    public function test_password_must_contain_a_special_character(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validate('MahlinePassword12');
    }

    public function test_password_with_special_character_is_accepted(): void
    {
        PasswordPolicy::validate('Mahline@Password12');

        $this->assertTrue(true);
    }

    public function test_password_may_contain_spaces_when_it_has_a_special_character(): void
    {
        PasswordPolicy::validate('Mahline Password12!');

        $this->assertTrue(true);
    }

    public function test_new_password_must_be_different_from_current_password(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PasswordPolicy::validateChange(
            currentPassword: 'MahlinePassword12!',
            newPassword: 'MahlinePassword12!',
        );
    }

    public function test_new_password_different_from_current_password_is_accepted(): void
    {
        PasswordPolicy::validateChange(
            currentPassword: 'MahlinePassword12!',
            newPassword: 'MahlinePassword13!',
        );

        $this->assertTrue(true);
    }
}
