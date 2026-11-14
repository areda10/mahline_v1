<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Media\Services\MediaImageProcessor;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class MediaImageProcessorTest extends TestCase
{
    public function test_it_generates_medium_and_thumbnail_derivatives(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('PHP GD is required to process Media images.');
        }

        $file = UploadedFile::fake()->image('source.jpg', 1600, 800);
        $derivatives = app(MediaImageProcessor::class)->derivatives($file, 'jpg');

        self::assertArrayHasKey('medium', $derivatives);
        self::assertArrayHasKey('thumbnail', $derivatives);

        $medium = getimagesizefromstring($derivatives['medium']);
        $thumbnail = getimagesizefromstring($derivatives['thumbnail']);

        self::assertIsArray($medium);
        self::assertIsArray($thumbnail);
        self::assertSame(1200, $medium[0]);
        self::assertSame(400, $thumbnail[0]);
        self::assertSame(600, $medium[1]);
        self::assertSame(200, $thumbnail[1]);
    }
}
