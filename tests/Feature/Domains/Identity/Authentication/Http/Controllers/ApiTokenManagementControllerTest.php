<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiTokenManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class);
    }

    public function test_each_role_can_open_personal_api_token_management_page(): void
    {
        foreach (['super_admin_root', 'super_admin', 'cooperative_admin', 'user'] as $roleSlug) {
            $user = $this->actor($roleSlug);
            $route = $roleSlug === 'super_admin_root'
                ? 'root.backoffice.security.api-tokens.index'
                : 'backoffice.security.api-tokens.index';

            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertViewIs('identity.security.api-tokens.index')
                ->assertSee('Mes clés API');
        }
    }

    public function test_owner_can_create_and_revoke_a_token_from_personal_backoffice(): void
    {
        $user = $this->actor('user');

        $this->actingAs($user)
            ->post(route('backoffice.security.api-tokens.store'), [
                'name' => 'web-personal-key',
            ])
            ->assertRedirect(route('backoffice.security.api-tokens.index'))
            ->assertSessionHas('new_api_token')
            ->assertSessionHas('success');

        $token = $user->tokens()->firstOrFail();

        $this->get(route('backoffice.security.api-tokens.index'))
            ->assertOk()
            ->assertSee('web-personal-key');

        $this->delete(route('backoffice.security.api-tokens.destroy', $token))
            ->assertRedirect(route('backoffice.security.api-tokens.index'));

        self::assertNotNull($token->fresh()->revoked_at);
    }

    public function test_root_can_consult_and_revoke_tokens_on_any_user_detail_page(): void
    {
        $root = $this->actor('super_admin_root');
        $target = $this->actor('user');
        $token = $target->createToken('root-managed-key');

        $this->actingAs($root)
            ->get(route('root.backoffice.users.show', $target))
            ->assertOk()
            ->assertViewHas('apiTokens')
            ->assertSee('Clés API de l’utilisateur')
            ->assertSee('root-managed-key');

        $this->delete(route('root.backoffice.users.api-tokens.destroy', [
            'user' => $target,
            'token' => $token->accessToken,
        ]))
            ->assertRedirect(route('root.backoffice.users.show', $target));

        self::assertNotNull($token->accessToken->fresh()->revoked_at);
    }

    public function test_non_root_cannot_open_root_user_token_management_routes(): void
    {
        $user = $this->actor('super_admin');
        $target = $this->actor('user');
        $token = $target->createToken('protected-key');

        $this->actingAs($user)
            ->delete(route('root.backoffice.users.api-tokens.destroy', [
                'user' => $target,
                'token' => $token->accessToken,
            ]))
            ->assertForbidden();

        self::assertNull($token->accessToken->fresh()->revoked_at);
    }

    private function actor(string $roleSlug): User
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => str($roleSlug)->replace('_', ' ')->title()->toString(),
                'is_active' => true,
            ],
        );

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        return $user;
    }
}
