<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

use App\Core\Foundation\Enums\Contracts\BaseEnumContract;
use App\Core\Foundation\Enums\Traits\InteractsWithEnum;

enum UserStatus: string implements BaseEnumContract
{
    use InteractsWithEnum;

    /**
     * User can authenticate and use the application.
     */
    case Active = 'active';

    /**
     * User account exists but awaits activation.
     */
    case Pending = 'pending';

    /**
     * User account is disabled.
     */
    case Inactive = 'inactive';

    /**
     * User account is temporarily suspended.
     */
    case Suspended = 'suspended';

    /**
     * User account is archived and no longer usable.
     */
    case Archived = 'archived';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active    => 'Active',
            self::Pending   => 'Pending',
            self::Inactive  => 'Inactive',
            self::Suspended => 'Suspended',
            self::Archived  => 'Archived',
        };
    }

    /**
     * Convert the enum case to an array.
     */
    public function toArray(): array
    {
        return [
            'name'  => $this->name,
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }
}