<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Users\Models;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserPasswordTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_user_password_is_hashed_when_created_through_factory(): void
    {
        $plainPassword = 'MahlineTestPassword123!';

        $user = User::factory()->create([
            'password' => $plainPassword,
        ]);

        $this->assertNotSame(
            $plainPassword,
            $user->password
        );

        $this->assertTrue(
            Hash::check(
                $plainPassword,
                $user->password
            )
        );
    }

    public function test_user_password_is_never_stored_as_plain_text(): void
    {
        $plainPassword = 'MahlineTestPassword123!';

        $user = User::factory()->create([
            'password' => $plainPassword,
        ]);

        $this->assertNotSame(
            $plainPassword,
            $user->getRawOriginal('password')
        );
    }

    public function test_user_password_can_be_verified_after_persistence(): void
    {
        $plainPassword = 'MahlineTestPassword123!';

        $user = User::factory()->create([
            'password' => $plainPassword,
        ]);

        $user->refresh();

        $this->assertTrue(
            Hash::check(
                $plainPassword,
                $user->password
            )
        );
    }

    public function test_user_password_hash_is_not_replaced_when_unrelated_attribute_is_updated(): void
    {
        $plainPassword = 'MahlineTestPassword123!';

        $user = User::factory()->create([
            'password' => $plainPassword,
        ]);

        $originalHash = $user->password;

        $user->update([
            'first_name' => 'Updated',
        ]);

        $user->refresh();

        $this->assertSame(
            $originalHash,
            $user->password
        );

        $this->assertTrue(
            Hash::check(
                $plainPassword,
                $user->password
            )
        );
    }

    public function test_user_password_can_be_changed(): void
    {
        $oldPassword = 'MahlineOldPassword123!';
        $newPassword = 'MahlineNewPassword456!';

        $user = User::factory()->create([
            'password' => $oldPassword,
        ]);

        $user->password = Hash::make($newPassword);
        $user->save();
        $user->refresh();

        $this->assertFalse(
            Hash::check(
                $oldPassword,
                $user->password
            )
        );

        $this->assertTrue(
            Hash::check(
                $newPassword,
                $user->password
            )
        );
    }
}
