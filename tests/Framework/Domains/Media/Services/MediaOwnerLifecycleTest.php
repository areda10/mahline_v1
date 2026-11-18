<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Services\MediaService;
use App\Domains\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaOwnerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_deleting_an_owner_soft_deletes_its_active_media_and_retains_files(): void
    {
        Storage::fake('local');
        $actor = $this->rootUser();
        $product = Product::factory()->create();
        $media = app(MediaService::class)->upload(
            $actor,
            $product,
            UploadedFile::fake()->createWithContent(
                'owner-document.pdf',
                "%PDF-1.4\\n1 0 obj\\n<< /Type /Catalog >>\\nendobj\\n%%EOF",
            ),
            'document',
            'legal',
        );

        $product->forceDelete();

        self::assertSoftDeleted('media', ['id' => $media->getKey()]);
        Storage::disk('local')->assertExists($media->path);
        self::assertDatabaseHas('audit_events', [
            'event_key' => 'media.owner_force_deleted',
            'subject_id' => $media->getKey(),
        ]);
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
