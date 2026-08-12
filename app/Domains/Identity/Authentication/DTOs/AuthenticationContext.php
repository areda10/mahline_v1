<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\DTOs;

/**
 * Immutable context describing an authentication attempt.
 *
 * This DTO transports authentication and client information
 * between the authentication layers without depending on
 * the HTTP Request.
 *
 * AuthenticationContext is intentionally independent from
 * Laravel's HTTP layer.
 */
final readonly class AuthenticationContext
{
    public function __construct(
        /**
         * User email used for authentication.
         */
        public string $email,

        /**
         * Plain-text password supplied during authentication.
         *
         * This value must never be persisted or logged.
         */
        public string $password,

        /**
         * Laravel session identifier.
         */
        public string $sessionId,

        /**
         * Client IP address.
         */
        public ?string $ipAddress = null,

        /**
         * Raw HTTP User-Agent.
         */
        public ?string $userAgent = null,

        /**
         * Detected browser name.
         *
         * Example:
         * - Firefox
         * - Safari
         * - Chrome
         */
        public ?string $browser = null,

        /**
         * Detected device name/type.
         *
         * Example:
         * - Android
         * - iPhone
         * - Windows
         */
        public ?string $device = null,
    ) {
    }
}