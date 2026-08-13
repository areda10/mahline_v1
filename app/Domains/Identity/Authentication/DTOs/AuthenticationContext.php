<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\DTOs;

/**
 * Immutable context describing an authentication attempt.
 *
 * This DTO transports authentication and client information
 * between the HTTP and domain authentication layers.
 *
 * The DTO intentionally has no dependency on Laravel's
 * HTTP Request object.
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
         * IMPORTANT:
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
         * Detected browser.
         *
         * Examples:
         * - Firefox
         * - Chrome
         * - Safari
         * - Edge
         */
        public ?string $browser = null,

        /**
         * Detected device.
         *
         * Examples:
         * - Android
         * - iPhone
         * - iPad
         * - Windows
         * - macOS
         */
        public ?string $device = null,
    ) {
    }
}