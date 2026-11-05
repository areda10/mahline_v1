<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Exceptions;

use RuntimeException;
use Throwable;

final class UserInvitationDeliveryException extends RuntimeException
{
    public function __construct(
        string $message = 'The user invitation could not be delivered.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
