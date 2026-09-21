<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidPassword implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail,
    ): void {
        if (! is_string($value)) {
            $fail('The password must be a string.');

            return;
        }

        try {
            PasswordPolicy::validate($value);
        } catch (\InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}