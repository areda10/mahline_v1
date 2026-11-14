<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Media\Models;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Media\Models\Media;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MediaRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_a_media_morph_many_relationship(): void
    {
        $product = Product::factory()->create();

        self::assertInstanceOf(MorphMany::class, $product->media());

        $media = $product->media()->create($this->mediaAttributes());

        self::assertInstanceOf(Media::class, $media);
        self::assertSame($product->getKey(), $media->mediable_id);
        self::assertSame(Product::class, $media->mediable_type);
        self::assertSame($product->getKey(), $media->mediable->getKey());
        self::assertInstanceOf(Product::class, $media->mediable);
    }

    public function test_cooperative_has_a_media_morph_many_relationship(): void
    {
        $cooperative = Cooperative::factory()->create();

        self::assertInstanceOf(MorphMany::class, $cooperative->media());

        $media = $cooperative->media()->create($this->mediaAttributes());

        self::assertSame($cooperative->getKey(), $media->mediable_id);
        self::assertSame(Cooperative::class, $media->mediable_type);
        self::assertInstanceOf(Cooperative::class, $media->mediable);
        self::assertSame($cooperative->getKey(), $media->mediable->getKey());
    }

    public function test_user_has_a_media_morph_many_relationship(): void
    {
        $user = User::factory()->create();

        self::assertInstanceOf(MorphMany::class, $user->media());

        $media = $user->media()->create($this->mediaAttributes());

        self::assertSame($user->getKey(), $media->mediable_id);
        self::assertSame(User::class, $media->mediable_type);
        self::assertInstanceOf(User::class, $media->mediable);
        self::assertSame($user->getKey(), $media->mediable->getKey());
    }

    public function test_media_resolves_its_mediable_owner(): void
    {
        $product = Product::factory()->create();
        $media = Media::query()->create([
            ...$this->mediaAttributes(),
            'mediable_type' => Product::class,
            'mediable_id' => $product->getKey(),
        ]);

        self::assertInstanceOf(Product::class, $media->mediable);
        self::assertSame($product->getKey(), $media->mediable->getKey());
    }

    public function test_media_relationship_is_isolated_per_owner(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $mediaA = $productA->media()->create($this->mediaAttributes());
        $mediaB = $productB->media()->create($this->mediaAttributes());

        self::assertCount(1, $productA->media);
        self::assertCount(1, $productB->media);
        self::assertSame($mediaA->getKey(), $productA->media->first()->getKey());
        self::assertSame($mediaB->getKey(), $productB->media->first()->getKey());
        self::assertFalse($productA->media->contains($mediaB));
        self::assertFalse($productB->media->contains($mediaA));
    }

    public function test_soft_deleted_media_is_excluded_from_the_default_relationship(): void
    {
        $product = Product::factory()->create();
        $media = $product->media()->create($this->mediaAttributes());

        $media->delete();

        self::assertCount(0, $product->media);
        self::assertSame(1, $product->media()->withTrashed()->count());
        self::assertContains(
            SoftDeletes::class,
            class_uses_recursive(Media::class),
        );
    }

    public function test_media_uses_a_string_ulid_primary_key(): void
    {
        $product = Product::factory()->create();
        $media = $product->media()->create($this->mediaAttributes());

        self::assertSame('id', $media->getKeyName());
        self::assertSame('string', $media->getKeyType());
        self::assertFalse($media->getIncrementing());
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $media->getKey());
    }

    public function test_product_exposes_images_primary_image_and_documents(): void
    {
        $product = Product::factory()->create();
        $primary = $product->media()->create([
            ...$this->mediaAttributes(),
            'category' => 'product_image',
            'is_primary' => true,
        ]);
        $document = $product->media()->create([
            ...$this->mediaAttributes(),
            'category' => 'document',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'original_name' => 'proof.pdf',
        ]);

        self::assertSame($primary->getKey(), $product->primaryImage->getKey());
        self::assertCount(1, $product->images);
        self::assertSame($document->getKey(), $product->documents->first()->getKey());
    }

    public function test_cooperative_exposes_logo_photos_and_documents(): void
    {
        $cooperative = Cooperative::factory()->create();
        $logo = $cooperative->media()->create([
            ...$this->mediaAttributes(),
            'category' => 'cooperative_logo',
            'is_primary' => true,
        ]);
        $photo = $cooperative->media()->create([
            ...$this->mediaAttributes(),
            'category' => 'cooperative_photo',
        ]);

        self::assertSame($logo->getKey(), $cooperative->logo->getKey());
        self::assertSame($photo->getKey(), $cooperative->photos->first()->getKey());
    }

    public function test_user_exposes_profile_image_and_documents(): void
    {
        $user = User::factory()->create();
        $profile = $user->media()->create([
            ...$this->mediaAttributes(),
            'category' => 'profile_image',
            'is_primary' => true,
        ]);

        self::assertSame($profile->getKey(), $user->profileImage->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaAttributes(): array
    {
        return [
            'collection' => 'product-gallery',
            'category' => 'product_image',
            'disk' => 'local',
            'path' => 'media/test-image.webp',
            'original_name' => 'test-image.webp',
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'size' => 1024,
            'visibility' => 'private',
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }
}
