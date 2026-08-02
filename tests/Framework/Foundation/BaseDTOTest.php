<?php

declare(strict_types=1);

namespace Tests\Framework\Foundation;

use App\Core\Foundation\DTOs\BaseDTO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BaseDTOTest extends TestCase
{
    public function test_base_dto_class_exists(): void
    {
        $this->assertTrue(class_exists(BaseDTO::class));
    }

    public function test_base_dto_is_abstract(): void
    {
        $reflection = new ReflectionClass(BaseDTO::class);

        $this->assertTrue($reflection->isAbstract());
    }
}
