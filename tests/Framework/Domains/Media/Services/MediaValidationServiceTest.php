<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Media\Enums\MediaCategory;
use App\Domains\Media\Services\MediaValidationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class MediaValidationServiceTest extends TestCase
{
    public function test_product_image_accepts_a_valid_jpeg(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');

        $file = UploadedFile::fake()->image('product.jpg', 100, 100);
        $result = app(MediaValidationService::class)->validate($file, MediaCategory::PRODUCT_IMAGE);

        self::assertSame('jpg', $result['extension']);
        self::assertSame('image/jpeg', $result['mime_type']);
        self::assertGreaterThan(0, $result['size']);
    }

    public function test_profile_image_rejects_a_file_over_five_megabytes(): void
    {
        $file = UploadedFile::fake()->create('profile.jpg', 5121, 'image/jpeg');

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::PROFILE_IMAGE);
    }

    public function test_product_image_rejects_a_file_over_five_megabytes(): void
    {
        $file = UploadedFile::fake()->create('product.jpg', 5121, 'image/jpeg');

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::PRODUCT_IMAGE);
    }

    public function test_image_rejects_dimensions_over_four_thousand_pixels(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');
        $file = UploadedFile::fake()->image('oversized.jpg', 4001, 100);

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::PRODUCT_IMAGE);
    }

    public function test_document_rejects_an_unsupported_webp_extension(): void
    {
        $file = UploadedFile::fake()->create('document.webp', 10, 'image/webp');

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::DOCUMENT);
    }

    public function test_document_rejects_a_mime_type_that_does_not_match_its_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('forged.pdf', 'not a PDF document');

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::DOCUMENT);
    }

    public function test_document_accepts_a_pdf_with_a_real_pdf_signature(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'proof.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );

        $result = app(MediaValidationService::class)->validate($file, MediaCategory::DOCUMENT);

        self::assertSame('pdf', $result['extension']);
        self::assertSame('application/pdf', $result['mime_type']);
    }

    public function test_document_accepts_jpeg_and_png_images(): void
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'PHP GD must be enabled for Media V1.');

        foreach (['proof.jpg', 'proof.png'] as $name) {
            $file = UploadedFile::fake()->image($name, 100, 100);
            $result = app(MediaValidationService::class)->validate($file, MediaCategory::DOCUMENT);

            self::assertContains($result['mime_type'], ['image/jpeg', 'image/png']);
        }
    }

    public function test_document_rejects_a_file_over_ten_megabytes(): void
    {
        $file = UploadedFile::fake()->create('proof.pdf', 10241, 'application/pdf');

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::DOCUMENT);
    }

    public function test_image_and_document_size_limits_match_v1(): void
    {
        foreach ([
            MediaCategory::PRODUCT_IMAGE,
            MediaCategory::COOPERATIVE_LOGO,
            MediaCategory::COOPERATIVE_PHOTO,
            MediaCategory::PROFILE_IMAGE,
        ] as $category) {
            self::assertSame(5 * 1024 * 1024, $category->maxBytes());
        }

        self::assertSame(10 * 1024 * 1024, MediaCategory::DOCUMENT->maxBytes());
    }

    public function test_all_image_categories_allow_only_jpeg_png_and_webp(): void
    {
        $expected = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        foreach ([
            MediaCategory::PRODUCT_IMAGE,
            MediaCategory::COOPERATIVE_LOGO,
            MediaCategory::COOPERATIVE_PHOTO,
            MediaCategory::PROFILE_IMAGE,
        ] as $category) {
            self::assertSame($expected, $category->allowedMimeTypes());
        }
    }

    public function test_svg_is_not_an_allowed_image_format(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        );

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::COOPERATIVE_LOGO);
    }
}
