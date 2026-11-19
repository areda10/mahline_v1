<?php

declare(strict_types=1);

namespace App\Domains\Identity\UserManagement\Listeners;

use App\Domains\Cooperatives\Events\CooperativeCreatedV1;
use App\Domains\Identity\UserManagement\Contracts\SuperAdminCooperativeAssignmentService;

final class AssignCreatorToNewCooperative
{
    public function __construct(
        private readonly SuperAdminCooperativeAssignmentService $assignments,
    ) {}

    public function handle(CooperativeCreatedV1 $event): void
    {
        $this->assignments->assignCreatorToNewCooperative(
            $event->creatorUserId,
            $event->cooperativeId,
        );
    }
}
