<?php

declare(strict_types=1);

namespace Tests\Framework\Foundation;

use App\Core\Foundation\Resources\BaseResource;
use Illuminate\Http\Resources\Json\JsonResource;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BaseResourceTest extends TestCase
{
    public function test_base_resource_class_exists(): void
    {
        $this->assertTrue(class_exists(BaseResource::class));
    }

    public function test_base_resource_is_abstract(): void
    {
        $reflection = new ReflectionClass(BaseResource::class);

        $this->assertTrue($reflection->isAbstract());
    }

    public function test_base_resource_extends_json_resource(): void
    {
        $this->assertTrue(
            is_subclass_of(BaseResource::class, JsonResource::class)
        );
    }
}
