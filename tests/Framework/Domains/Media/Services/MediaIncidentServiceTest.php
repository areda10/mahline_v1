<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Enums\MediaIncidentStatus;
use App\Domains\Media\Models\MediaIncident;
use App\Domains\Media\Services\MediaIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaIncidentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_workflow_supports_resolution_closure_and_reopening(): void
    {
        Notification::fake();
        $actor = $this->rootUser();
        $service = app(MediaIncidentService::class);
        $incident = $service->create($actor, 'Suspicious media access', 'Repeated unauthorized media access attempts.');

        self::assertSame(MediaIncidentStatus::OPEN, $incident->status);

        $incident = $service->transition($actor, $incident, MediaIncidentStatus::IN_PROGRESS);
        self::assertSame(MediaIncidentStatus::IN_PROGRESS, $incident->status);

        $incident = $service->transition(
            $actor,
            $incident,
            MediaIncidentStatus::RESOLVED,
            'The access policy was corrected and verified.',
        );
        self::assertNotNull($incident->resolved_at);

        $incident = $service->transition($actor, $incident, MediaIncidentStatus::CLOSED);
        self::assertNotNull($incident->closed_at);

        $incident = $service->reopen($actor, $incident, 'The suspicious activity occurred again.');
        self::assertSame(MediaIncidentStatus::OPEN, $incident->status);
        self::assertNotNull($incident->reopened_at);
        self::assertNull($incident->closed_at);
    }

    public function test_incident_attachment_is_private_and_uploaded_without_antivirus_in_v1(): void
    {
        Notification::fake();
        Storage::fake('local');

        $actor = $this->rootUser();
        $service = app(MediaIncidentService::class);
        $incident = $service->create($actor, 'Technical incident', 'A technical report must be reviewed.');

        $media = $service->attach(
            $actor,
            $incident,
            UploadedFile::fake()->createWithContent(
                'diagnostic.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
            ),
        );

        self::assertSame('incident_attachment', $media->category);
        self::assertSame('private', $media->visibility);
        Storage::disk('local')->assertExists($media->path);
    }

    private function rootUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Super Admin Root',
            'slug' => 'super_admin_root',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->getKey());

        return $user->refresh();
    }
}
