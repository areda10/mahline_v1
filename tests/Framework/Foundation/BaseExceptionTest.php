<?php

declare(strict_types=1);

namespace Tests\Framework\Foundation;

use App\Core\Foundation\Exceptions\BaseException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BaseExceptionTest extends TestCase
{
    public function test_base_exception_class_exists(): void
    {
        $this->assertTrue(class_exists(BaseException::class));
    }

    public function test_base_exception_is_abstract(): void
    {
        $reflection = new ReflectionClass(BaseException::class);

        $this->assertTrue($reflection->isAbstract());
    }

    public function test_base_exception_extends_exception(): void
    {
        $this->assertTrue(
            is_subclass_of(BaseException::class, \Exception::class)
        );
    }
}
