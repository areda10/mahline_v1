<?php

declare(strict_types=1);

namespace App\Core\Foundation\Enums\Traits;

use App\Core\Foundation\Enums\Exceptions\InvalidEnumValueException;
use BackedEnum;

trait InteractsWithEnum
{
    /**
     * Return all enum cases.
     *
     * @return array<static>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Return all enum values.
     *
     * @return array<int, string|int>
     */
    public static function values(): array
    {
        return array_map(
            static fn (BackedEnum $case): string|int => $case->value,
            self::cases()
        );
    }

    /**
     * Return all enum names.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(
            static fn (BackedEnum $case): string => $case->name,
            self::cases()
        );
    }

    /**
     * Return [value => name].
     *
     * @return array<string|int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->name;
        }

        return $options;
    }

    /**
     * Alias of options().
     *
     * @return array<string|int, string>
     */
    public static function labels(): array
    {
        return self::options();
    }

    /**
     * Check if a value exists.
     */
    public static function has(string|int $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    /**
     * Check if a name exists.
     */
    public static function hasName(string $name): bool
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find an enum by its name.
     */
    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Find an enum by its name or throw.
     */
    public static function fromName(string $name): self
    {
        $case = self::tryFromName($name);

        if ($case === null) {
            throw InvalidEnumValueException::fromName(
                $name,
                static::class
            );
        }

        return $case;
    }

    /**
     * Return a random enum case.
     */
    public static function random(): self
    {
        $cases = self::cases();

        return $cases[array_rand($cases)];
    }

    /**
     * Return the enum label.
     */
    public function label(): string
    {
        return $this->name;
    }

    /**
     * Return the enum value.
     */
    public function value(): string|int
    {
        return $this->value;
    }

    /**
     * Convert enum to array.
     *
     * @return array{name: string, value: string|int, label: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }

    /**
     * Convert all enum cases to array.
     *
     * @return array<int, array{name: string, value: string|int, label: string}>
     */
    public static function toArrayList(): array
    {
        return array_map(
            static fn (self $case): array => $case->toArray(),
            self::cases()
        );
    }
}