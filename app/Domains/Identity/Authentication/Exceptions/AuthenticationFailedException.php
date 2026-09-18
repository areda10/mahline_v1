<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Exceptions;

use App\Core\Foundation\Exceptions\BaseException;

final class AuthenticationFailedException extends BaseException
{
    public function __construct(
        string $message = 'Authentication failed.',
    ) {
        parent::__construct($message);
    }
}