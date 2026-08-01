<?php

declare(strict_types=1);

namespace Tests\Framework\Foundation;

use App\Core\Foundation\Actions\BaseAction;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BaseActionTest extends TestCase
{
    public function test_base_action_class_exists(): void
    {
        $this->assertTrue(class_exists(BaseAction::class));
    }

    public function test_base_action_is_abstract(): void
    {
        $reflection = new ReflectionClass(BaseAction::class);

        $this->assertTrue($reflection->isAbstract());
    }
}
