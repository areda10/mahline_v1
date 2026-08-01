<?php

declare(strict_types=1);

namespace Tests\Framework\Unit;

use App\Core\Foundation\Services\BaseService;
use PHPUnit\Framework\TestCase;

final class BaseServiceTest extends TestCase
{
    public function test_base_service_class_exists(): void
    {
        $this->assertTrue(class_exists(BaseService::class));
    }

    public function test_base_service_is_abstract(): void
    {
        $reflection = new \ReflectionClass(BaseService::class);

        $this->assertTrue($reflection->isAbstract());
    }
}