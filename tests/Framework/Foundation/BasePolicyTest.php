<?php

declare(strict_types=1);

namespace Tests\Framework\Foundation;

use App\Core\Foundation\Policies\BasePolicy;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BasePolicyTest extends TestCase
{
    public function test_base_policy_class_exists(): void
    {
        $this->assertTrue(class_exists(BasePolicy::class));
    }

    public function test_base_policy_is_abstract(): void
    {
        $reflection = new ReflectionClass(BasePolicy::class);

        $this->assertTrue($reflection->isAbstract());
    }
}
