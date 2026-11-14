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
        if (! function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD is required to generate the fake image.');
        }

        $file = UploadedFile::fake()->image('product.jpg', 100, 100);
        $result = app(MediaValidationService::class)->validate($file, MediaCategory::PRODUCT_IMAGE);

        self::assertSame('jpg', $result['extension']);
        self::assertSame('image/jpeg', $result['mime_type']);
        self::assertGreaterThan(0, $result['size']);
    }

    public function test_profile_image_rejects_a_file_over_two_megabytes(): void
    {
        $file = UploadedFile::fake()->create('profile.jpg', 2049, 'image/jpeg');

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::PROFILE_IMAGE);
    }

    public function test_product_image_rejects_a_file_over_five_megabytes(): void
    {
        $file = UploadedFile::fake()->create('product.jpg', 5121, 'image/jpeg');

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::PRODUCT_IMAGE);
    }

    public function test_document_rejects_a_non_pdf_extension(): void
    {
        $file = UploadedFile::fake()->create('document.png', 10, 'image/png');

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::DOCUMENT);
    }

    public function test_document_rejects_a_mime_type_that_does_not_match_its_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('forged.pdf', 'not a PDF document');

        $this->expectException(ValidationException::class);

        app(MediaValidationService::class)->validate($file, MediaCategory::DOCUMENT);
    }

    public function test_document_accepts_a_pdf_with_a_real_pdf_mime_type(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'proof.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );

        $result = app(MediaValidationService::class)->validate($file, MediaCategory::DOCUMENT);

        self::assertSame('pdf', $result['extension']);
        self::assertSame('application/pdf', $result['mime_type']);
    }

    public function test_cooperative_image_size_limit_is_five_megabytes(): void
    {
        self::assertSame(5 * 1024 * 1024, MediaCategory::COOPERATIVE_LOGO->maxBytes());
        self::assertSame(5 * 1024 * 1024, MediaCategory::COOPERATIVE_PHOTO->maxBytes());
    }

    public function test_document_size_limit_is_fifteen_megabytes(): void
    {
        self::assertSame(15 * 1024 * 1024, MediaCategory::DOCUMENT->maxBytes());
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
