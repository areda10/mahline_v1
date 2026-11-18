<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Media\Services\MediaImageProcessor;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class MediaImageProcessorTest extends TestCase
{
    public function test_it_generates_all_four_v1_derivatives_in_smart_webp_format(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');
        self::assertTrue(function_exists('imagewebp'), 'PHP GD WebP support must be enabled for Media V1.');

        $file = UploadedFile::fake()->image('source.jpg', 1600, 800);
        $derivatives = app(MediaImageProcessor::class)->derivatives($file, 'jpg');

        self::assertSame(['thumbnail', 'small', 'medium', 'large'], array_keys($derivatives));

        $expectedDimensions = [
            'thumbnail' => [150, 75],
            'small' => [300, 150],
            'medium' => [600, 300],
            'large' => [1600, 800],
        ];

        foreach ($expectedDimensions as $name => [$width, $height]) {
            self::assertSame('webp', $derivatives[$name]['extension']);
            self::assertSame('image/webp', $derivatives[$name]['mime_type']);

            $image = getimagesizefromstring($derivatives[$name]['contents']);
            self::assertIsArray($image);
            self::assertSame($width, $image[0]);
            self::assertSame($height, $image[1]);
        }
    }

    public function test_it_does_not_upscale_small_images(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');
        self::assertTrue(function_exists('imagewebp'), 'PHP GD WebP support must be enabled for Media V1.');

        $file = UploadedFile::fake()->image('small.jpg', 80, 40);
        $derivatives = app(MediaImageProcessor::class)->derivatives($file, 'jpg');

        foreach ($derivatives as $derivative) {
            $image = getimagesizefromstring($derivative['contents']);
            self::assertIsArray($image);
            self::assertSame(80, $image[0]);
            self::assertSame(40, $image[1]);
        }
    }
}
