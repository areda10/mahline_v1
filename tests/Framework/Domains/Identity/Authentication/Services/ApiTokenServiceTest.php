<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\Services;

use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Models\PersonalAccessToken;
use App\Domains\Identity\Authentication\Services\ApiTokenService;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_owner_can_create_token_and_secret_is_not_written_to_audit(): void
    {
        $user = User::factory()->create();

        $newToken = $this->service->createForOwner(
            owner: $user,
            actor: $user,
            name: 'personal-integration',
        );

        self::assertNotEmpty($newToken->plainTextToken);
        self::assertSame([], $newToken->accessToken->abilities);

        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'identity.authentication.api_token_created',
            'action' => 'api_token_created',
            'subject_id' => $newToken->accessToken->getKey(),
        ]);

        $audit = DB::table('audit_events')->first();
        self::assertStringNotContainsString($newToken->plainTextToken, json_encode($audit));
        self::assertStringNotContainsString($newToken->accessToken->token, json_encode($audit));
    }

    public function test_owner_token_listing_and_metadata_never_expose_token_hash(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedToken = $owner->createToken('owned-token');
        $otherUser->createToken('foreign-token');

        $tokens = $this->service->tokensForUser($owner);
        $metadata = $this->service->metadata($ownedToken->accessToken);

        self::assertCount(1, $tokens);
        self::assertSame($ownedToken->accessToken->getKey(), $tokens->first()->getKey());
        self::assertSame('owned-token', $metadata['name']);
        self::assertTrue($metadata['is_active']);
        self::assertArrayNotHasKey('token', $metadata);
        self::assertArrayNotHasKey('plainTextToken', $metadata);
        self::assertArrayNotHasKey('tokenable_id', $metadata);
    }

    public function test_owner_cannot_create_an_api_token_for_another_user(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();

        try {
            $this->service->createForOwner(
                owner: $owner,
                actor: $actor,
                name: 'forbidden-token',
            );

            self::fail('Creating a token for another user must be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame(
                'An API token can only be created by its owner.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
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

    public function test_root_revocation_audit_records_actor_and_reason_without_token_secret_or_hash(): void
    {
        $owner = User::factory()->create();
        $root = User::factory()->create();
        $created = $owner->createToken('root-audited-token');

        self::assertTrue($this->service->revokeToken(
            owner: $owner,
            token: $created->accessToken,
            actor: $root,
            reason: 'root_revocation',
            eventKey: 'identity.authentication.root_api_token_revoked',
            action: 'root_api_token_revoked',
        ));

        $audit = DB::table('audit_events')
            ->where('event_key', 'identity.authentication.root_api_token_revoked')
            ->first();

        self::assertNotNull($audit);
        self::assertSame((string) $root->getKey(), (string) $audit->actor_id);
        self::assertSame((string) $created->accessToken->getKey(), (string) $audit->subject_id);
        self::assertStringContainsString('root_revocation', json_encode($audit));
        self::assertStringNotContainsString($created->plainTextToken, json_encode($audit));
        self::assertStringNotContainsString($created->accessToken->token, json_encode($audit));
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

    public function test_service_created_tokens_expire_after_exactly_ninety_days(): void
    {
        $this->travelTo(now()->startOfSecond());

        $user = User::factory()->create();

        $created = $this->service->createForOwner(
            owner: $user,
            actor: $user,
            name: 'ninety-day-token',
        );

        self::assertEquals(
            now()->addDays(PersonalAccessToken::EXPIRATION_DAYS),
            $created->accessToken->fresh()->expires_at,
        );

        self::assertTrue($this->service->metadata($created->accessToken->fresh())['is_active']);
    }

    public function test_expired_token_metadata_is_inactive_even_when_not_revoked(): void
    {
        $user = User::factory()->create();
        $created = $user->createToken('expired-metadata-token');

        $created->accessToken->forceFill([
            'expires_at' => now()->subSecond(),
        ])->save();

        $metadata = $this->service->metadata($created->accessToken->fresh());

        self::assertNull($created->accessToken->fresh()->revoked_at);
        self::assertFalse($metadata['is_active']);
    }

    public function test_expiration_migration_normalizes_all_existing_tokens_from_creation_date(): void
    {
        $user = User::factory()->create();
        $createdAt = now()->subDays(120)->startOfSecond();

        DB::table('personal_access_tokens')->insert([
            'id' => (string) str()->ulid(),
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->getKey(),
            'name' => 'legacy-permanent-token',
            'token' => hash('sha256', 'legacy-permanent-token-secret'),
            'abilities' => json_encode([]),
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $migration = require database_path(
            'migrations/2026_11_10_220000_enforce_ninety_day_api_token_expiration.php',
        );

        $migration->up();

        $legacyToken = DB::table('personal_access_tokens')
            ->where('name', 'legacy-permanent-token')
            ->first();

        self::assertNotNull($legacyToken);
        self::assertEquals(
            $createdAt->copy()->addDays(90)->toDateTimeString(),
            \Illuminate\\Support\\Carbon::parse($legacyToken->expires_at)->toDateTimeString(),
        );

        $this->travelBack();
    }

}
