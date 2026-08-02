<?php

declare(strict_types=1);

namespace Tests\Framework\Foundation;

use App\Core\Foundation\ValueObjects\BaseValueObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BaseValueObjectTest extends TestCase
{
    public function test_base_value_object_class_exists(): void
    {
        $this->assertTrue(class_exists(BaseValueObject::class));
    }

    public function test_base_value_object_is_abstract(): void
    {
        $reflection = new ReflectionClass(BaseValueObject::class);

        $this->assertTrue($reflection->isAbstract());
    }
}
