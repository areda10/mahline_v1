<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Users\Factories;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_user(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(
            User::class,
            $user
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_factory_generates_required_identity_fields(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->first_name);
        $this->assertNotEmpty($user->last_name);
        $this->assertNotEmpty($user->display_name);
        $this->assertNotEmpty($user->email);
        $this->assertNotEmpty($user->telephone);
        $this->assertNotEmpty($user->password);
    }

    public function test_factory_generates_consistent_display_name(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            "{$user->first_name} {$user->last_name}",
            $user->display_name
        );
    }

    public function test_factory_uses_active_status_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            UserStatus::Active,
            $user->status
        );
    }

    public function test_factory_uses_french_locale_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            'fr',
            $user->locale
        );
    }

    public function test_factory_uses_utc_timezone_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            'UTC',
            $user->timezone
        );
    }

    public function test_factory_creates_verified_email_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull(
            $user->email_verified_at
        );
    }

    public function test_factory_does_not_create_audit_actors(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->created_by);
        $this->assertNull($user->updated_by);
        $this->assertNull($user->deleted_by);
    }

    public function test_factory_creates_user_without_soft_delete(): void
    {
        $user = User::factory()->create();

        $this->assertNull(
            $user->deleted_at
        );
    }

    public function test_factory_generates_unique_emails(): void
    {
        $users = User::factory()
            ->count(10)
            ->create();

        $emails = $users
            ->pluck('email')
            ->all();

        $this->assertCount(
            10,
            array_unique($emails)
        );
    }

    public function test_factory_make_does_not_persist_user(): void
    {
        $user = User::factory()->make();

        $this->assertFalse(
            $user->exists
        );
    }

    public function test_factory_uses_ulid_primary_key(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->id);
        $this->assertSame(26, strlen($user->id));
    }

    public function test_factory_password_is_hashed_when_persisted(): void
    {
        $user = User::factory()->create();

        $this->assertNotSame(
            'password',
            $user->getRawOriginal('password')
        );

        $this->assertTrue(
            password_verify(
                'password',
                $user->getRawOriginal('password')
            )
        );
    }
}