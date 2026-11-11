<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Http\Controllers;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authorization\Models\Permission;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ApiTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_can_manage_its_own_api_tokens(): void
    {
        foreach (['super_admin_root', 'super_admin', 'cooperative_admin', 'user'] as $roleSlug) {
            $user = $this->actor($roleSlug);

            $created = $this->actingAs($user, 'sanctum')
                ->postJson(route('identity.tokens.store'), ['name' => $roleSlug.'-key']);

            $created
                ->assertCreated()
                ->assertJsonPath('data.name', $roleSlug.'-key')
                ->assertJsonPath('data.abilities', [])
                ->assertJsonStructure(['plain_text_token']);

            self::assertNotNull($created->json('data.expires_at'));

            $this->getJson(route('identity.tokens.index'))
                ->assertOk()
                ->assertJsonFragment(['name' => $roleSlug.'-key']);

            $this->deleteJson(route('identity.tokens.destroy', $created->json('data.id')))
                ->assertOk();
        }
    }

    public function test_personal_token_listing_and_revoke_are_owner_scoped(): void
    {
        $owner = $this->actor('user');
        $other = $this->actor('cooperative_admin');

        $owned = $owner->createToken('owned-key');
        $foreign = $other->createToken('foreign-key');

        $this->actingAs($owner, 'sanctum')
            ->getJson(route('identity.tokens.index'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'owned-key'])
            ->assertJsonMissing(['name' => 'foreign-key'])
            ->assertJsonMissing(['token' => $owned->accessToken->token]);

        $this->deleteJson(route('identity.tokens.destroy', $foreign->accessToken))
            ->assertNotFound();

        $this->deleteJson(route('identity.tokens.destroy', $owned->accessToken))
            ->assertOk();

        self::assertNotNull($owned->accessToken->fresh()->revoked_at);
        self::assertNull($foreign->accessToken->fresh()->revoked_at);
    }

    public function test_owner_can_revoke_all_api_tokens_without_revoking_web_sessions(): void
    {
        $owner = $this->actor('user');
        $first = $owner->createToken('first-key');
        $second = $owner->createToken('second-key');

        $session = AuthenticationSession::query()->create([
            'user_id' => $owner->getKey(),
            'session_id' => 'api-owner-global-revoke-session',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson(route('identity.tokens.destroy-all'))
            ->assertOk()
            ->assertJsonPath('revoked_count', 2);

        self::assertNotNull($first->accessToken->fresh()->revoked_at);
        self::assertNotNull($second->accessToken->fresh()->revoked_at);
        self::assertNull($session->fresh()->revoked_at);
    }

    public function test_only_root_can_manage_other_users_api_tokens(): void
    {
        $root = $this->actor('super_admin_root');
        $this->grantPermission($root, 'users.view');
        $superAdmin = $this->actor('super_admin');
        $target = $this->actor('user');
        $token = $target->createToken('target-key');

        $this->authenticateWithBearerToken($superAdmin, ['users.view']);

        $this->getJson(route('identity.root.users.tokens.index', $target))
            ->assertForbidden();

        $this->authenticateWithBearerToken($root, ['users.view']);

        $this->getJson(route('identity.root.users.tokens.index', $target))
            ->assertOk()
            ->assertJsonFragment(['name' => 'target-key']);

        $this->deleteJson(route('identity.root.users.tokens.destroy', [
            'user' => $target,
            'token' => $token->accessToken,
        ]))->assertOk();

        self::assertNotNull($token->accessToken->fresh()->revoked_at);
    }

    public function test_root_can_revoke_all_tokens_of_any_user_without_revoking_web_sessions(): void
    {
        $root = $this->actor('super_admin_root');
        $this->grantPermission($root, 'users.view');
        $target = $this->actor('cooperative_admin');
        $first = $target->createToken('root-first-key');
        $second = $target->createToken('root-second-key');

        $session = AuthenticationSession::query()->create([
            'user_id' => $target->getKey(),
            'session_id' => 'root-api-token-global-revoke-session',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->authenticateWithBearerToken($root, ['users.view']);

        $this->deleteJson(route('identity.root.users.tokens.destroy-all', $target))
            ->assertOk()
            ->assertJsonPath('revoked_count', 2);

        self::assertNotNull($first->accessToken->fresh()->revoked_at);
        self::assertNotNull($second->accessToken->fresh()->revoked_at);
        self::assertNull($session->fresh()->revoked_at);
    }

    public function test_api_token_creation_rejects_whitespace_only_names(): void
    {
        $user = $this->actor('user');

        $this->actingAs($user, 'sanctum')
            ->postJson(route('identity.tokens.store'), ['name' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_token_secret_is_returned_once_and_never_written_to_audit(): void
    {
        $user = $this->actor('user');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(route('identity.tokens.store'), ['name' => 'one-time-secret']);

        $response->assertCreated();

        $secret = $response->json('plain_text_token');
        self::assertIsString($secret);
        self::assertNotSame('', $secret);

        $auditRows = DB::table('audit_events')->get();
        self::assertCount(1, $auditRows);
        self::assertStringNotContainsString($secret, json_encode($auditRows));

        $storedToken = PersonalAccessToken::query()->firstOrFail();
        self::assertStringNotContainsString($secret, $storedToken->token);
    }

    public function test_wildcard_token_ability_does_not_bypass_audit_policy(): void
    {
        $user = $this->actor('user');
        $token = $user->createToken('wildcard-audit-attempt', ['*']);

        $this->withToken($token->plainTextToken)
            ->getJson(route('audit.events.index'))
            ->assertForbidden();
    }

    public function test_wildcard_token_ability_does_not_bypass_root_role_middleware(): void
    {
        $superAdmin = $this->actor('super_admin');
        $target = $this->actor('user');
        $token = $superAdmin->createToken('wildcard-root-attempt', ['*']);

        $this->withToken($token->plainTextToken)
            ->getJson(route('identity.root.users.tokens.index', $target))
            ->assertForbidden();
    }

    public function test_root_wildcard_ability_does_not_bypass_users_view_permission(): void
    {
        $root = $this->actor('super_admin_root');
        $target = $this->actor('user');
        $token = $root->createToken('root-wildcard-without-permission', ['*']);

        $this->withToken($token->plainTextToken)
            ->getJson(route('identity.root.users.tokens.index', $target))
            ->assertForbidden();
    }

    public function test_root_can_list_and_revoke_tokens_for_every_role_without_exposing_secrets(): void
    {
        $root = $this->actor('super_admin_root');
        $this->grantPermission($root, 'users.view');

        foreach (['super_admin', 'cooperative_admin', 'user'] as $roleSlug) {
            $cooperative = Cooperative::factory()->create();
            $target = $this->actor($roleSlug);
            $target->forceFill([
                'cooperative_id' => $cooperative->getKey(),
            ])->save();

            $created = $target->createToken($roleSlug.'-root-managed-key');
            $storedHash = $created->accessToken->token;

            $this->authenticateWithBearerToken($root, ['users.view']);

            $listing = $this->getJson(route('identity.root.users.tokens.index', $target));

            $listing
                ->assertOk()
                ->assertJsonFragment(['name' => $roleSlug.'-root-managed-key'])
                ->assertJsonMissing(['token' => $storedHash])
                ->assertJsonMissing(['plain_text_token' => $created->plainTextToken]);

            $this->deleteJson(route('identity.root.users.tokens.destroy', [
                'user' => $target,
                'token' => $created->accessToken,
            ]))->assertOk();

            self::assertNotNull($created->accessToken->fresh()->revoked_at);
        }
    }

    public function test_active_api_token_quotas_are_enforced_per_role(): void
    {
        $limits = [
            'super_admin_root' => 10,
            'super_admin' => 5,
            'cooperative_admin' => 5,
            'user' => 3,
        ];

        foreach ($limits as $roleSlug => $limit) {
            $user = $this->actor($roleSlug);

            for ($index = 1; $index <= $limit; $index++) {
                $this->actingAs($user, 'sanctum')
                    ->postJson(route('identity.tokens.store'), [
                        'name' => $roleSlug.'-quota-'.$index,
                    ])
                    ->assertCreated();
            }

            $this->actingAs($user, 'sanctum')
                ->postJson(route('identity.tokens.store'), [
                    'name' => $roleSlug.'-quota-overflow',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('quota');

            $this->assertDatabaseCount('personal_access_tokens', array_sum(array_slice($limits, 0, array_search($roleSlug, array_keys($limits), true) + 1)));
        }
    }

    public function test_revoked_and_expired_tokens_do_not_consume_the_active_token_quota(): void
    {
        $user = $this->actor('user');
        $first = $user->createToken('quota-revoked');
        $second = $user->createToken('quota-expired');
        $third = $user->createToken('quota-active');

        $first->accessToken->forceFill(['revoked_at' => now()])->save();
        $second->accessToken->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->actingAs($user, 'sanctum')
            ->postJson(route('identity.tokens.store'), ['name' => 'quota-new-one'])
            ->assertCreated();

        $this->postJson(route('identity.tokens.store'), ['name' => 'quota-new-two'])
            ->assertCreated();

        $this->postJson(route('identity.tokens.store'), ['name' => 'quota-overflow'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quota');

        self::assertNull($third->accessToken->fresh()->revoked_at);
        self::assertDatabaseHas('personal_access_tokens', ['id' => $first->accessToken->getKey()]);
        self::assertDatabaseHas('personal_access_tokens', ['id' => $second->accessToken->getKey()]);
    }

    public function test_token_abilities_must_be_active_permissions_granted_to_the_owner(): void
    {
        $user = $this->actor('user');
        $role = $user->roles()->firstOrFail();
        $permission = Permission::query()->create([
            'name' => 'View products',
            'slug' => 'products.view',
            'description' => 'View products',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission->getKey());

        $this->actingAs($user, 'sanctum')
            ->postJson(route('identity.tokens.store'), [
                'name' => 'unauthorized-abilities',
                'abilities' => ['products.view', 'users.view'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('abilities');

        $this->postJson(route('identity.tokens.store'), [
            'name' => 'permitted-ability',
            'abilities' => ['products.view'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.abilities', ['products.view']);

        $this->postJson(route('identity.tokens.store'), [
            'name' => 'wildcard-ability',
            'abilities' => ['*'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('abilities');
    }

    public function test_permission_ability_and_policy_are_both_required_for_audit_api(): void
    {
        $root = $this->actor('super_admin_root');
        $role = $root->roles()->firstOrFail();
        $permission = Permission::query()->create([
            'name' => 'View audit',
            'slug' => 'audit.view',
            'description' => 'View audit',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission->getKey());

        $withoutAbility = $root->createToken('audit-without-ability', []);
        $this->withToken($withoutAbility->plainTextToken)
            ->getJson(route('audit.events.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $withAbility = $root->createToken('audit-with-ability', ['audit.view']);
        $this->withToken($withAbility->plainTextToken)
            ->getJson(route('audit.events.index'))
            ->assertOk();
    }

    /**
     * Authenticate through a real Sanctum bearer token.
     * This avoids Sanctum::actingAs(), which mocks the configured token model
     * and is incompatible with MAHLINE's intentionally final token model.
     *
     * @param  array<int, string>  $abilities
     */
    private function authenticateWithBearerToken(User $user, array $abilities): void
    {
        $token = $user->createToken('feature-test-authentication', $abilities);

        // Sanctum's guard caches the resolved user between requests in a test.
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken);
    }

    private function grantPermission(User $user, string $slug): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $slug,
                'description' => $slug,
                'is_active' => true,
            ],
        );

        $user->roles()->firstOrFail()->permissions()->syncWithoutDetaching([
            $permission->getKey(),
        ]);
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

    public function test_expired_api_token_is_rejected_by_sanctum_authentication(): void
    {
        $user = $this->actor('user');
        $created = $user->createToken('ninety-day-expiring-token');

        self::assertNotNull($created->accessToken->expires_at);
        self::assertTrue($created->accessToken->expires_at->isFuture());

        $this->withToken($created->plainTextToken)
            ->getJson(route('identity.tokens.index'))
            ->assertOk();

        $this->travelTo($created->accessToken->expires_at->copy()->addSecond());

        // Sanctum's guard caches the authenticated user for the lifetime of
        // the guard instance. Force a fresh guard so this request revalidates
        // the bearer token against its now-expired expires_at timestamp.
        $this->app['auth']->forgetGuards();

        $this->withToken($created->plainTextToken)
            ->getJson(route('identity.tokens.index'))
            ->assertUnauthorized();

        $this->travelBack();
    }

}
