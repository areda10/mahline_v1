<?php

declare(strict_types=1);

namespace Tests\Framework\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MediaCategoryIndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_category_lookup_index_exists(): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM media'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        self::assertContains('media_mediable_category_deleted_index', $indexes);
    }
}
