<?php

declare(strict_types=1);

namespace App\Core\Foundation\Enums\Exceptions;

use InvalidArgumentException;
use Throwable;

final class InvalidEnumValueException extends InvalidArgumentException
{
    /**
     * Create an exception for an invalid enum value.
     */
    public static function fromValue(
        string|int $value,
        string $enumClass,
    ): self {
        return new self(
            sprintf(
                'The value "%s" is invalid for enum "%s".',
                (string) $value,
                $enumClass
            )
        );
    }

    /**
     * Create an exception for an invalid enum name.
     */
    public static function fromName(
        string $name,
        string $enumClass,
    ): self {
        return new self(
            sprintf(
                'The name "%s" is invalid for enum "%s".',
                $name,
                $enumClass
            )
        );
    }

    /**
     * Create an exception for an invalid enum case.
     */
    public static function fromCase(
        string $case,
        string $enumClass,
    ): self {
        return new self(
            sprintf(
                'The case "%s" is invalid for enum "%s".',
                $case,
                $enumClass
            )
        );
    }

    /**
     * Create an exception from a previous throwable.
     */
    public static function fromThrowable(
        Throwable $previous,
        string $enumClass,
    ): self {
        return new self(
            sprintf(
                'An error occurred while handling enum "%s": %s',
                $enumClass,
                $previous->getMessage()
            ),
            0,
            $previous
        );
    }
}
