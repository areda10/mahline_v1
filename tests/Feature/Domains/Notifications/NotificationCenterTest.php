<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Notifications;

use App\Domains\Identity\Users\Models\User;
use App\Domains\Notifications\Http\Controllers\NotificationCenterController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_the_authenticated_users_notifications(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownNotification = $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'order_status_changed',
            'data' => ['title' => 'Commande mise à jour'],
        ]);
        $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'order_status_changed',
            'data' => ['title' => 'Notification privée'],
        ]);

        Auth::login($owner);

        $response = app(NotificationCenterController::class)->index();

        $this->assertTrue($response->getData()['notifications']->getCollection()->contains('id', $ownNotification->getKey()));
        $this->assertSame(1, $response->getData()['notifications']->total());
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'private_event',
            'data' => ['title' => 'Privé'],
        ]);

        Auth::login($owner);

        $this->expectException(ModelNotFoundException::class);

        app(NotificationCenterController::class)->markRead($notification->getKey());
    }

    public function test_mark_all_read_updates_only_the_authenticated_users_unread_notifications(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownNotification = $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'own_event',
            'data' => ['title' => 'À lire'],
        ]);
        $otherNotification = $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'other_event',
            'data' => ['title' => 'À conserver'],
        ]);

        Auth::login($owner);

        app(NotificationCenterController::class)->markAllRead();

        $this->assertNotNull($ownNotification->fresh()->read_at);
        $this->assertNull($otherNotification->fresh()->read_at);
    }
}
