<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Identity\Authentication\DTOs;

use App\Domains\Identity\Authentication\DTOs\AuthenticationContext;
use Tests\TestCase;

final class AuthenticationContextTest extends TestCase
{
    /**
     * AuthenticationContext stores authentication data.
     */
    public function test_authentication_context_stores_data(): void
    {
        $context = new AuthenticationContext(
            email: 'user@example.com',
            password: 'password',
            sessionId: 'session-a',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            browser: 'Firefox',
            device: 'Android',
        );

        $this->assertSame(
            'user@example.com',
            $context->email
        );

        $this->assertSame(
            'password',
            $context->password
        );

        $this->assertSame(
            'session-a',
            $context->sessionId
        );

        $this->assertSame(
            '127.0.0.1',
            $context->ipAddress
        );

        $this->assertSame(
            'Mozilla/5.0',
            $context->userAgent
        );

        $this->assertSame(
            'Firefox',
            $context->browser
        );

        $this->assertSame(
            'Android',
            $context->device
        );
    }

    /**
     * Optional client information can be null.
     */
    public function test_optional_client_information_can_be_null(): void
    {
        $context = new AuthenticationContext(
            email: 'user@example.com',
            password: 'password',
            sessionId: 'session-a',
        );

        $this->assertNull($context->ipAddress);
        $this->assertNull($context->userAgent);
        $this->assertNull($context->browser);
        $this->assertNull($context->device);
    }

    /**
     * AuthenticationContext must not depend on HTTP Request.
     */
    public function test_authentication_context_does_not_depend_on_request(): void
    {
        $reflection = new \ReflectionClass(
            AuthenticationContext::class
        );

        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof \ReflectionNamedType) {
                continue;
            }

            $this->assertNotSame(
                \Illuminate\Http\Request::class,
                $type->getName()
            );
        }
    }
}