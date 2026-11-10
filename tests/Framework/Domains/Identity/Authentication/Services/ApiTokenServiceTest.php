<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authentication\Services\ApiTokenService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApiTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ApiTokenService::class);
    }

    public function test_revoke_for_user_revokes_each_active_api_token_and_preserves_web_sessions(): void
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();

        $first = $user->createToken('first-token');
        $second = $user->createToken('second-token');

        $session = AuthenticationSession::query()->create([
            'user_id' => $user->getKey(),
            'session_id' => 'api-token-service-session',
            'authenticated_at' => now(),
            'last_activity_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ]);

        $revoked = $this->service->revokeForUser(
            user: $user,
            actor: $actor,
            reason: 'password_changed',
        );

        self::assertSame(2, $revoked);
        self::assertNotNull($first->accessToken->fresh()->revoked_at);
        self::assertNotNull($second->accessToken->fresh()->revoked_at);
        self::assertDatabaseCount('personal_access_tokens', 2);

        self::assertNull($session->fresh()->revoked_at);
        self::assertNull($session->fresh()->revocation_reason);

        $this->assertDatabaseCount('audit_events', 2);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.authentication.token_revoked',
            'action' => 'token_revoked',
            'actor_id' => $actor->getKey(),
            'subject_type' => PersonalAccessToken::class,
            'subject_id' => $first->accessToken->getKey(),
        ]);
    }

    public function test_revoke_token_only_revokes_the_token_owned_by_the_supplied_user(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedToken = $owner->createToken('owned-token');
        $foreignToken = $otherUser->createToken('foreign-token');

        self::assertTrue($this->service->revokeToken(
            owner: $owner,
            token: $ownedToken->accessToken,
            actor: $actor,
            reason: 'manual_revocation',
        ));

        self::assertNotNull($ownedToken->accessToken->fresh()->revoked_at);
        self::assertNull($foreignToken->accessToken->fresh()->revoked_at);

        self::assertFalse($this->service->revokeToken(
            owner: $owner,
            token: $foreignToken->accessToken,
            actor: $actor,
            reason: 'manual_revocation',
        ));

        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_repeated_revocation_does_not_create_duplicate_audit_events(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('single-token');

        self::assertTrue($this->service->revokeToken(
            owner: $user,
            token: $token->accessToken,
            actor: $user,
        ));

        self::assertFalse($this->service->revokeToken(
            owner: $user,
            token: $token->accessToken,
            actor: $user,
        ));

        $this->assertDatabaseCount('audit_events', 1);
    }
}
