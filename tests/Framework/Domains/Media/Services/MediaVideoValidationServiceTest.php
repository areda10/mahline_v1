<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Services;

use App\Domains\Media\Contracts\MediaVideoInspector;
use App\Domains\Media\Services\MediaVideoValidationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class MediaVideoValidationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_accepts_a_valid_mp4_with_verified_container_and_metadata(): void
    {
        $this->bindInspector([
            'container' => 'mov,mp4,m4a,3gp,3g2,mj2',
            'duration' => 59.5,
            'width' => 1920,
            'height' => 1080,
            'video_codec' => 'h264',
            'audio_codec' => 'aac',
        ]);

        $file = UploadedFile::fake()->createWithContent('presentation.mp4', $this->minimalMp4LikePayload());

        $result = app(MediaVideoValidationService::class)->validate($file);

        self::assertSame('mp4', $result['extension']);
        self::assertSame('video/mp4', $result['mime_type']);
        self::assertSame('h264', $result['video']['video_codec']);
    }

    public function test_accepts_mov_and_webm_with_matching_real_mime_and_container(): void
    {
        foreach ([
            ['clip.mov', 'video/quicktime', 'mov,mp4,m4a,3gp,3g2,mj2'],
            ['clip.webm', 'video/webm', 'webm'],
        ] as [$name, $mime, $container]) {
            $this->bindInspector([
                'container' => $container,
                'duration' => 30.0,
                'width' => 1280,
                'height' => 720,
                'video_codec' => 'vp9',
                'audio_codec' => null,
            ]);

            $file = UploadedFile::fake()->createWithContent($name, $this->payloadForMime($mime));
            self::assertSame($mime, app(MediaVideoValidationService::class)->validate($file)['mime_type']);
        }
    }

    public function test_rejects_an_extension_that_does_not_match_the_actual_mime_type(): void
    {
        // MIME mismatch is rejected before FFprobe; no inspector call is expected.
        $file = UploadedFile::fake()->createWithContent('fake.mp4', "not a video\n");

        $this->expectException(ValidationException::class);
        app(MediaVideoValidationService::class)->validate($file);
    }

    public function test_rejects_a_container_that_does_not_match_the_extension(): void
    {
        $this->bindInspector([
            ...$this->validMetadata(),
            'container' => 'webm',
        ]);
        $file = UploadedFile::fake()->createWithContent('fake.mp4', $this->minimalMp4LikePayload());

        $this->expectException(ValidationException::class);
        app(MediaVideoValidationService::class)->validate($file);
    }

    public function test_rejects_a_video_longer_than_sixty_seconds(): void
    {
        $this->bindInspector([
            ...$this->validMetadata(),
            'duration' => 60.01,
        ]);
        $file = UploadedFile::fake()->createWithContent('long.mp4', $this->minimalMp4LikePayload());

        $this->expectException(ValidationException::class);
        app(MediaVideoValidationService::class)->validate($file);
    }

    public function test_accepts_exactly_sixty_seconds(): void
    {
        $this->bindInspector([
            ...$this->validMetadata(),
            'duration' => 60.0,
        ]);
        $file = UploadedFile::fake()->createWithContent('limit.mp4', $this->minimalMp4LikePayload());

        self::assertSame(60.0, app(MediaVideoValidationService::class)->validate($file)['video']['duration']);
    }

    public function test_rejects_a_video_with_a_dimension_above_1080p(): void
    {
        $this->bindInspector([
            ...$this->validMetadata(),
            'width' => 1921,
            'height' => 1080,
        ]);
        $file = UploadedFile::fake()->createWithContent('large.mp4', $this->minimalMp4LikePayload());

        $this->expectException(ValidationException::class);
        app(MediaVideoValidationService::class)->validate($file);
    }

    public function test_rejects_a_video_over_twenty_megabytes(): void
    {
        // Oversized files are rejected before FFprobe; no inspector call is expected.
        $file = UploadedFile::fake()->create('large.mp4', 20 * 1024 + 1, 'video/mp4');

        $this->expectException(ValidationException::class);
        app(MediaVideoValidationService::class)->validate($file);
    }

    public function test_rejects_an_unreadable_or_unprobeable_video(): void
    {
        $inspector = Mockery::mock(MediaVideoInspector::class);
        $inspector->shouldReceive('inspect')->once()->andThrow(new \RuntimeException('probe failed'));
        $this->app->instance(MediaVideoInspector::class, $inspector);

        $file = UploadedFile::fake()->createWithContent('broken.mp4', $this->minimalMp4LikePayload());

        $this->expectException(ValidationException::class);
        app(MediaVideoValidationService::class)->validate($file);
    }

    private function bindInspector(array $metadata): void
    {
        $inspector = Mockery::mock(MediaVideoInspector::class);
        $inspector->shouldReceive('inspect')->once()->andReturn($metadata);
        $this->app->instance(MediaVideoInspector::class, $inspector);
    }

    private function validMetadata(): array
    {
        return [
            'container' => 'mov,mp4,m4a,3gp,3g2,mj2',
            'duration' => 45.0,
            'width' => 1280,
            'height' => 720,
            'video_codec' => 'h264',
            'audio_codec' => 'aac',
        ];
    }

    private function minimalMp4LikePayload(): string
    {
        return $this->payloadForMime('video/mp4');
    }

    private function payloadForMime(string $mime): string
    {
        return match ($mime) {
            'video/mp4' => "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2",
            'video/quicktime' => "\x00\x00\x00\x18ftypqt  \x00\x00\x02\x00",
            'video/webm' => "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01",
            default => '',
        };
    }
}
