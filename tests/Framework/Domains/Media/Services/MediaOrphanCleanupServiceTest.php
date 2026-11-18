<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Media\Services\MediaOrphanCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class MediaOrphanCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'media.public_disk' => 'local',
            'media.private_disk' => 'local',
            'media.orphan_safety_hours' => 24,
        ]);
    }

    public function test_it_detects_orphans_before_deleting_them_after_twenty_four_hours(): void
    {
        Storage::disk('local')->put('media/orphan/file.bin', 'orphan');

        self::assertSame(0, app(MediaOrphanCleanupService::class)->detectAndPurge());
        self::assertDatabaseHas('media_orphan_candidates', [
            'disk' => 'local',
            'path' => 'media/orphan/file.bin',
        ]);
        Storage::disk('local')->assertExists('media/orphan/file.bin');

        DB::table('media_orphan_candidates')
            ->where('path', 'media/orphan/file.bin')
            ->update(['first_seen_at' => now()->subHours(25)]);

        self::assertSame(1, app(MediaOrphanCleanupService::class)->detectAndPurge());
        Storage::disk('local')->assertMissing('media/orphan/file.bin');
        self::assertDatabaseMissing('media_orphan_candidates', [
            'path' => 'media/orphan/file.bin',
        ]);
    }

    public function test_it_never_deletes_a_file_referenced_by_a_media_record(): void
    {
        Storage::disk('local')->put('media/owned/original.pdf', 'document');

        DB::table('media')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'mediable_type' => 'App\\Domains\\Products\\Models\\Product',
            'mediable_id' => (string) \Illuminate\Support\Str::ulid(),
            'collection' => 'documents',
            'category' => 'document',
            'disk' => 'local',
            'path' => 'media/owned/original.pdf',
            'original_name' => 'original.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 8,
            'visibility' => 'private',
            'is_primary' => false,
            'sort_order' => 0,
            'metadata' => json_encode(['derivatives' => []]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertSame(0, app(MediaOrphanCleanupService::class)->detectAndPurge());
        Storage::disk('local')->assertExists('media/owned/original.pdf');
        self::assertDatabaseMissing('media_orphan_candidates', [
            'path' => 'media/owned/original.pdf',
        ]);
    }

    public function test_it_enforces_a_minimum_twenty_four_hour_safety_period(): void
    {
        config(['media.orphan_safety_hours' => 1]);
        Storage::disk('local')->put('media/orphan/short-window.bin', 'orphan');

        app(MediaOrphanCleanupService::class)->detectAndPurge();

        DB::table('media_orphan_candidates')
            ->where('path', 'media/orphan/short-window.bin')
            ->update(['first_seen_at' => now()->subHours(2)]);

        self::assertSame(0, app(MediaOrphanCleanupService::class)->detectAndPurge());
        Storage::disk('local')->assertExists('media/orphan/short-window.bin');
    }
}
