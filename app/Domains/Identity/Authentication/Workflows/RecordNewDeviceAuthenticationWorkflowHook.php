<?php

declare(strict_types=1);

namespace App\Domains\Identity\Authentication\Workflows;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Authentication\Enums\SecurityEventType;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Identity\Authentication\Services\SecurityEventService;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;

final class RecordNewDeviceAuthenticationWorkflowHook implements AuthenticationWorkflowHook
{
    public function __construct(
        private readonly SecurityEventService $securityEventService,
        private readonly AuditService $audit,
    ) {}

    public function afterDeviceRemembered(
        User $user,
        AuthenticationSession $session,
        bool $isNewDevice,
    ): void {
        if (! $isNewDevice) {
            return;
        }

        $this->securityEventService->record(
            event: SecurityEventType::UnusualActivityDetected,
            reason: 'new_device_detected',
            user: $user,
            ipAddress: $session->ip_address,
            userAgent: $session->user_agent,
            browser: $session->browser,
            device: $session->device,
            metadata: [
                'authentication_session_id' => (string) $session->getKey(),
                'detection' => 'first_seen_device_for_user',
            ],
        );

        $this->audit->record(new AuditEntryDTO(
            eventKey: 'identity.authentication.new_device_detected',
            action: 'new_device_detected',
            actorType: User::class,
            actorId: (string) $user->getKey(),
            cooperativeId: $user->cooperative_id
                ? (string) $user->cooperative_id
                : null,
            subjectType: AuthenticationSession::class,
            subjectId: (string) $session->getKey(),
            oldValues: ['known_device' => false],
            newValues: ['known_device' => true],
            metadata: [
                'browser' => $session->browser,
                'device' => $session->device,
                'detection' => 'first_seen_device_for_user',
            ],
            ipAddress: $session->ip_address,
            userAgent: $session->user_agent,
        ));
    }
}
