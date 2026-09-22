<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Enums;

enum SecurityEventType: string
{
    case AccountLocked = 'account_locked';

    case BruteForceDetected = 'brute_force_detected';

    case UnusualActivityDetected = 'unusual_activity_detected';

    case GlobalLogout = 'global_logout';

    case SecurityIncident = 'security_incident';
}