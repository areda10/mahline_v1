<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Identity\Authentication\Console;

use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

final class PruneExpiredApiTokensCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_prunes_eligible_tokens_and_reports_the_count(): void
    {
        $user = User::factory()->create();
        $created = $user->createToken('command-expired-token');

        DB::table('personal_access_tokens')
            ->where('id', $created->accessToken->getKey())
            ->update(['expires_at' => now()->subDays(91)]);

        $this->artisan('identity:tokens:prune-expired')
            ->expectsOutputToContain('1 expired API token(s) permanently removed.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $created->accessToken->getKey(),
        ]);
    }

    public function test_expired_token_purge_is_scheduled_daily_without_overlapping(): void
    {
        $events = app(Schedule::class)->events();

        $event = collect($events)->first(
            fn ($scheduled): bool => str_contains(
                (string) $scheduled->command,
                'identity:tokens:prune-expired',
            ),
        );

        self::assertNotNull($event);
        self::assertSame('0 0 * * *', $event->expression);
        self::assertTrue($event->withoutOverlapping);
    }
}
