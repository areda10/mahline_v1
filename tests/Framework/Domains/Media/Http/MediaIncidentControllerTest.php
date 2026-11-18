<?php

declare(strict_types=1);

namespace Tests\\Framework\\Domains\\Media\\Http;

use App\\Domains\\Identity\\Authentication\\Http\\Middleware\\EnsureActiveAuthenticationSession;
use App\\Domains\\Identity\\Authorization\\Models\\Role;
use App\\Domains\\Identity\\Users\\Models\\User;
use App\\Domains\\Media\\Enums\\MediaIncidentStatus;
use App\\Domains\\Media\\Models\\Media;
use App\\Domains\\Media\\Services\\MediaIncidentService;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Http\\UploadedFile;
use Illuminate\\Support\\Facades\\Notification;
use Illuminate\\Support\\Facades\\Storage;
use Tests\\TestCase;

final class MediaIncidentControllerTest extends TestCase
{
    use RefreshDatabase;

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
                    "%PDF-1.4\\n1 0 obj\\n<< /Type /Catalog >>\\nendobj\\n%%EOF",
                ),
            ]);

        $genericUpload->assertForbidden();

        $incidentUpload = $this->post(route('media.incidents.attachments.store', $incident), [
            'file' => UploadedFile::fake()->createWithContent(
                'incident-attachment.pdf',
                "%PDF-1.4\\n1 0 obj\\n<< /Type /Catalog >>\\nendobj\\n%%EOF",
            ),
        ]);

        $incidentUpload->assertUnprocessable();
        self::assertSame(0, Media::query()->count());
        self::assertSame([], Storage::disk('local')->allFiles('media'));
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
