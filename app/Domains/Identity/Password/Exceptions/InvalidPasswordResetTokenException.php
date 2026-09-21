<?php

declare(strict_types=1);

namespace App\Domains\Identity\Password\Exceptions;

use RuntimeException;

final class InvalidPasswordResetTokenException extends RuntimeException
{
}