<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Cooperatives\Models;

use App\Core\Foundation\Models\BaseModel;
use App\Domains\Cooperatives\Enums\CooperativeStatus;
use App\Domains\Cooperatives\Models\Cooperative;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CooperativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooperative_extends_base_model(): void
    {
        $cooperative = new Cooperative();

        $this->assertInstanceOf(BaseModel::class, $cooperative);
    }

    public function test_cooperative_uses_soft_deletes(): void
    {
        $traits = class_uses_recursive(Cooperative::class);

        $this->assertContains(
            SoftDeletes::class,
            $traits,
        );
    }

    public function test_cooperative_uses_cooperatives_table(): void
    {
        $cooperative = new Cooperative();

        $this->assertSame(
            'cooperatives',
            $cooperative->getTable(),
        );
    }

    public function test_cooperative_uses_ulid_as_primary_key(): void
    {
        $cooperative = new Cooperative();

        $this->assertSame(
            'id',
            $cooperative->getKeyName(),
        );

        $this->assertSame(
            'string',
            $cooperative->getKeyType(),
        );

        $this->assertFalse(
            $cooperative->getIncrementing(),
        );
    }

    public function test_cooperative_has_expected_fillable_attributes(): void
    {
        $cooperative = new Cooperative();

        $this->assertSame(
            [
                'name',
                'rib',
                'tax_id',
                'address_id',
                'phone',
                'email',
                'rc',
                'ice',
                'status',
            ],
            $cooperative->getFillable(),
        );
    }

    public function test_status_is_cast_to_cooperative_status(): void
    {
        $cooperative = new Cooperative();

        $casts = $cooperative->getCasts();

        $this->assertArrayHasKey('status', $casts);
        $this->assertSame(
            CooperativeStatus::class,
            $casts['status'],
        );
    }

    public function test_deleted_at_is_cast_to_datetime(): void
    {
        $cooperative = new Cooperative();

        $casts = $cooperative->getCasts();

        $this->assertArrayHasKey('deleted_at', $casts);
        $this->assertSame(
            'datetime',
            $casts['deleted_at'],
        );
    }

    public function test_cooperative_can_be_instantiated_with_attributes(): void
    {
        $cooperative = new Cooperative([
            'name' => 'Coopérative Test',
            'rib' => '123456789012345678901234',
            'tax_id' => 'PAT-001',
            'address_id' => '01JTESTADDRESS00000000000000',
            'phone' => '+212600000000',
            'email' => 'contact@example.test',
            'rc' => 'RC-001',
            'ice' => '001234567890123',
            'status' => CooperativeStatus::ACTIVE,
        ]);

        $this->assertSame(
            'Coopérative Test',
            $cooperative->name,
        );

        $this->assertSame(
            '123456789012345678901234',
            $cooperative->rib,
        );

        $this->assertSame(
            'PAT-001',
            $cooperative->tax_id,
        );

        $this->assertSame(
            '01JTESTADDRESS00000000000000',
            $cooperative->address_id,
        );

        $this->assertSame(
            '+212600000000',
            $cooperative->phone,
        );

        $this->assertSame(
            'contact@example.test',
            $cooperative->email,
        );

        $this->assertSame(
            'RC-001',
            $cooperative->rc,
        );

        $this->assertSame(
            '001234567890123',
            $cooperative->ice,
        );

        $this->assertSame(
            CooperativeStatus::ACTIVE,
            $cooperative->status,
        );
    }
}
