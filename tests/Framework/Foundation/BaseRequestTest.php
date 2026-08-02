<?php

declare(strict_types=1);

namespace Tests\Framework\Foundation;

use App\Core\Foundation\Requests\BaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BaseRequestTest extends TestCase
{
    public function test_base_request_class_exists(): void
    {
        $this->assertTrue(class_exists(BaseRequest::class));
    }

    public function test_base_request_is_abstract(): void
    {
        $reflection = new ReflectionClass(BaseRequest::class);

        $this->assertTrue($reflection->isAbstract());
    }

    public function test_base_request_extends_form_request(): void
    {
        $this->assertTrue(is_subclass_of(BaseRequest::class, FormRequest::class));
    }
}
