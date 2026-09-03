<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Users\Models;


use App\Core\Foundation\Models\BaseModel;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Tests\TestCase;

final class UserTest extends TestCase
{
    public function test_user_extends_base_model(): void
    {
        $this->assertInstanceOf(
            BaseModel::class,
            new User()
        );
    }

    public function test_user_implements_authenticatable_contract(): void
    {
        $this->assertTrue(
            is_a(
                User::class,
                AuthenticatableContract::class,
                true
            )
        );
    }

    public function test_user_uses_authenticatable_trait(): void
    {
        $this->assertContains(
            Authenticatable::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_uses_soft_deletes_trait(): void
    {
        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(User::class)
        );
    }

    public function test_user_uses_users_table(): void
    {
        $user = new User();

        $this->assertSame(
            'users',
            $user->getTable()
        );
    }

    public function test_user_uses_ulid_configuration_from_base_model(): void
    {
        $user = new User();

        $this->assertSame(
            'string',
            $user->getKeyType()
        );

        $this->assertFalse(
            $user->getIncrementing()
        );
    }

    public function test_user_uses_timestamps(): void
    {
        $user = new User();

        $this->assertTrue(
            $user->usesTimestamps()
        );
    }

    public function test_user_casts_status_to_user_status_enum(): void
    {
        $user = new User();

        $casts = $user->getCasts();

        $this->assertArrayHasKey(
            'status',
            $casts
        );

        $this->assertSame(
            UserStatus::class,
            $casts['status']
        );
    }

    public function test_user_casts_email_verified_at_to_datetime(): void
    {
        $user = new User();

        $this->assertSame(
            'datetime',
            $user->getCasts()['email_verified_at']
        );
    }

    public function test_user_casts_password_as_hashed(): void
    {
        $user = new User();

        $this->assertSame(
            'hashed',
            $user->getCasts()['password']
        );
    }

    public function test_user_hides_sensitive_attributes(): void
    {
        $user = new User();

        $hidden = $user->getHidden();

        $this->assertContains(
            'password',
            $hidden
        );

        $this->assertContains(
            'remember_token',
            $hidden
        );
    }

    public function test_user_contains_expected_fillable_attributes(): void
    {
        $user = new User();

        $expected = [
            'first_name',
            'last_name',
            'display_name',
            'email',
            'telephone',
            'password',
            'status',
            'locale',
            'timezone',
        ];

        $this->assertSame(
            $expected,
            $user->getFillable()
        );
    }

    public function test_user_does_not_include_audit_columns_in_fillable(): void
    {
        $user = new User();

        $fillable = $user->getFillable();

        $this->assertNotContains('created_by', $fillable);
        $this->assertNotContains('updated_by', $fillable);
        $this->assertNotContains('deleted_by', $fillable);
        $this->assertNotContains('created_at', $fillable);
        $this->assertNotContains('updated_at', $fillable);
        $this->assertNotContains('deleted_at', $fillable);
    }
}