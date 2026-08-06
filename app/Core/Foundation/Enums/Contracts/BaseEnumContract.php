<?php

declare(strict_types=1);

namespace App\Core\Foundation\Enums\Contracts;

interface BaseEnumContract
{
    /**
     * Return all backed values.
     */
    public static function values(): array;

    /**
     * Return all enum names.
     */
    public static function names(): array;

    /**
     * Return all cases as [value => label].
     */
    public static function options(): array;

    /**
     * Return the human-readable label.
     */
    public function label(): string;

    /**
     * Convert the enum case to an array.
     */
    public function toArray(): array;
}