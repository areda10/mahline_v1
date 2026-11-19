<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Services;

use App\Domains\Cooperatives\Contracts\CooperativeStatusReader;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CooperativeStatusReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_reader_distinguishes_active_deleted_and_missing_cooperatives(): void
    {
        $cooperative = Cooperative::factory()->create([
            'status' => CooperativeStatus::ACTIVE,
        ]);

        $reader = app(CooperativeStatusReader::class);

        $active = $reader->findStatus((string) $cooperative->getKey());

        self::assertNotNull($active);
        self::assertSame((string) $cooperative->getKey(), $active->cooperativeId);
        self::assertSame(CooperativeStatus::ACTIVE->value, $active->status);
        self::assertTrue($active->isActive);
        self::assertFalse($active->isDeleted);

        $cooperative->delete();

        $deleted = $reader->findStatus((string) $cooperative->getKey());

        self::assertNotNull($deleted);
        self::assertFalse($deleted->isActive);
        self::assertTrue($deleted->isDeleted);

        self::assertNull($reader->findStatus('01J00000000000000000000000'));
    }
}
