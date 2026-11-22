<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Http;

use App\Domains\Identity\Authentication\Http\Middleware\EnsureActiveAuthenticationSession;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Enums\MediaIncidentStatus;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Services\MediaIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaIncidentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Match the same-origin Fetch Metadata header sent by browsers.
        $this->withHeader('Sec-Fetch-Site', 'same-origin');
    }

    public function test_incident_csv_export_sanitizes_formula_like_titles(): void
    {
        Notification::fake();

        $actor = $this->rootUser();
        app(MediaIncidentService::class)->create(
            $actor,
            '=HYPERLINK("https://example.test","open")',
            'CSV formula injection regression test.',
        );

        $response = $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($actor)
            ->get(route('media.incidents.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_closed_incident_rejects_attachments_through_both_upload_routes(): void
    {
        Notification::fake();
        Storage::fake('local');

        $actor = $this->rootUser();
        $service = app(MediaIncidentService::class);
        $incident = $service->create($actor, 'Closed incident', 'No more attachments are allowed.');

        $service->transition($actor, $incident, MediaIncidentStatus::IN_PROGRESS);
        $service->transition(
            $actor,
            $incident,
            MediaIncidentStatus::RESOLVED,
            'The incident has been resolved.',
        );
        $incident = $service->transition($actor, $incident, MediaIncidentStatus::CLOSED);

        $genericUpload = $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($actor)
            ->post(route('media.store'), [
                'owner_type' => 'incident',
                'owner_id' => (string) $incident->getKey(),
                'category' => 'incident_attachment',
                'file' => UploadedFile::fake()->createWithContent(
                    'generic-attachment.pdf',
                    "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
                ),
            ]);

        $genericUpload->assertForbidden();

        $incidentUpload = $this->postJson(route('media.incidents.attachments.store', $incident), [
            'file' => UploadedFile::fake()->createWithContent(
                'incident-attachment.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
        ]);

        $incidentUpload->assertUnprocessable();
        self::assertSame(0, Media::query()->count());
        self::assertSame([], Storage::disk('local')->allFiles('media'));
    }

    public function test_non_root_cannot_access_incident_http_endpoints(): void
    {
        Notification::fake();

        $root = $this->rootUser();
        $incident = app(MediaIncidentService::class)->create(
            $root,
            'Restricted incident',
            'Only root may access incident operations.',
        );

        $actor = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );
        $actor->roles()->attach($role->getKey());

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)->actingAs($actor);

        $this->getJson(route('media.incidents.index'))->assertForbidden();
        $this->postJson(route('media.incidents.store'), [
            'title' => 'Unauthorized incident',
            'description' => 'Must be rejected.',
        ])->assertForbidden();
        $this->getJson(route('media.incidents.export'))->assertForbidden();
        $this->getJson(route('media.incidents.show', $incident))->assertForbidden();
        $this->patchJson(route('media.incidents.status', $incident), [
            'status' => 'IN_PROGRESS',
        ])->assertForbidden();
        $this->postJson(route('media.incidents.reopen', $incident), [
            'reason' => 'Unauthorized reopen.',
        ])->assertForbidden();
        $this->post(route('media.incidents.attachments.store', $incident), [
            'file' => UploadedFile::fake()->createWithContent(
                'forbidden.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
        ])->assertForbidden();
    }

    public function test_root_can_manage_incident_lifecycle_over_http_and_audit_each_action(): void
    {
        Notification::fake();

        $actor = $this->rootUser();
        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)->actingAs($actor);

        $created = $this->postJson(route('media.incidents.store'), [
            'title' => 'HTTP lifecycle incident',
            'description' => 'Created and managed through the HTTP layer.',
        ])->assertCreated()
            ->assertJsonPath('data.status', MediaIncidentStatus::OPEN->value);

        $incidentId = $created->json('data.id');

        $this->getJson(route('media.incidents.index'))
            ->assertOk()
            ->assertJsonFragment(['title' => 'HTTP lifecycle incident']);

        $this->getJson(route('media.incidents.show', $incidentId))
            ->assertOk()
            ->assertJsonPath('data.id', $incidentId);

        $this->patchJson(route('media.incidents.status', $incidentId), [
            'status' => MediaIncidentStatus::IN_PROGRESS->value,
        ])->assertOk()
            ->assertJsonPath('data.status', MediaIncidentStatus::IN_PROGRESS->value);

        $this->patchJson(route('media.incidents.status', $incidentId), [
            'status' => MediaIncidentStatus::RESOLVED->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('resolution');

        $this->patchJson(route('media.incidents.status', $incidentId), [
            'status' => MediaIncidentStatus::RESOLVED->value,
            'resolution' => 'The cause has been identified and corrected.',
        ])->assertOk()
            ->assertJsonPath('data.status', MediaIncidentStatus::RESOLVED->value);

        $this->patchJson(route('media.incidents.status', $incidentId), [
            'status' => MediaIncidentStatus::CLOSED->value,
        ])->assertOk()
            ->assertJsonPath('data.status', MediaIncidentStatus::CLOSED->value);

        $this->postJson(route('media.incidents.reopen', $incidentId), [
            'reason' => 'The issue recurred and requires a new investigation.',
        ])->assertOk()
            ->assertJsonPath('data.status', MediaIncidentStatus::OPEN->value);

        foreach ([
            'media.incident_opened',
            'media.incident_viewed',
            'media.incident_status_changed',
            'media.incident_reopened',
            'media.incidents_listed',
        ] as $eventKey) {
            self::assertDatabaseHas('audit_events', [
                'event_key' => $eventKey,
                'actor_id' => $actor->getKey(),
            ]);
        }
    }

    public function test_root_can_upload_private_incident_attachment_over_http_and_only_root_can_read_it(): void
    {
        Notification::fake();
        Storage::fake('local');

        $root = $this->rootUser();
        $incident = app(MediaIncidentService::class)->create(
            $root,
            'Attachment access incident',
            'Verify private attachment handling through HTTP.',
        );

        $this->withoutMiddleware(EnsureActiveAuthenticationSession::class)
            ->actingAs($root)
            ->post(route('media.incidents.attachments.store', $incident), [
                'file' => UploadedFile::fake()->createWithContent(
                    'diagnostic.pdf',
                    "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
                ),
            ])
            ->assertCreated()
            ->assertJsonPath('data.visibility', 'private');

        $media = Media::query()->where('mediable_id', $incident->getKey())->firstOrFail();

        self::assertSame('incident_attachment', $media->category);
        self::assertSame('private', $media->visibility);
        Storage::disk('local')->assertExists($media->path);

        $this->actingAs($root)
            ->get(route('media.private', $media))
            ->assertOk();

        $nonRoot = User::factory()->create();
        $userRole = Role::query()->firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'is_active' => true],
        );
        $nonRoot->roles()->attach($userRole->getKey());

        $this->actingAs($nonRoot)
            ->get(route('media.private', $media))
            ->assertForbidden();
    }

    private function rootUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin_root'],
            ['name' => 'Super Admin Root', 'is_active' => true],
        );
        $user->roles()->attach($role->getKey());

        return $user->refresh();
    }
}
