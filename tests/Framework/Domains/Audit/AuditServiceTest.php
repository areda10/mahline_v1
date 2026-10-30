<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Audit;

use App\Domains\Audit\Models\AuditEvent;
use App\Domains\Audit\Services\AuditService;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_a_business_audit_event(): void
    {
        $event = app(AuditService::class)->record(new AuditEntryDTO(
            eventKey: 'PRODUCT_STATUS_CHANGED',
            action: 'STATUS_CHANGE',
            actorType: 'user',
            actorId: '01M00000000000000000000000',
            cooperativeId: '01M00000000000000000000001',
            subjectType: 'product',
            subjectId: '01M00000000000000000000002',
            oldValues: ['is_active' => false],
            newValues: ['is_active' => true],
        ));

        $this->assertDatabaseHas('audit_events', [
            'id' => $event->getKey(),
            'event_key' => 'PRODUCT_STATUS_CHANGED',
            'action' => 'STATUS_CHANGE',
            'subject_id' => '01M00000000000000000000002',
        ]);
        $this->assertSame(['is_active' => false], $event->old_values);
        $this->assertSame(['is_active' => true], $event->new_values);
    }

    public function test_filters_sensitive_keys_recursively(): void
    {
        $event = app(AuditService::class)->record(new AuditEntryDTO(
            eventKey: 'USER_UPDATED',
            action: 'UPDATE',
            newValues: [
                'display_name' => 'Example',
                'password' => 'must-not-persist',
                'profile' => [
                    'email' => 'example@example.test',
                    'access_token' => 'must-not-persist',
                ],
            ],
            metadata: ['authorization' => 'Bearer secret'],
        ));

        $this->assertSame([
            'display_name' => 'Example',
            'profile' => ['email' => 'example@example.test'],
        ], $event->new_values);
        $this->assertSame([], $event->metadata);
    }

    public function test_audit_event_cannot_be_updated_or_deleted_through_eloquent(): void
    {
        $event = app(AuditService::class)->record(new AuditEntryDTO(
            eventKey: 'TEST_EVENT',
            action: 'CREATE',
        ));

        try {
            $event->action = 'ALTERED';
            $event->save();
            $this->fail('Expected immutable audit event update to throw.');
        } catch (LogicException $exception) {
            $this->assertSame('Audit events are append-only.', $exception->getMessage());
        }

        $event->refresh();

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_rejects_empty_event_key_or_action(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(AuditService::class)->record(new AuditEntryDTO(
            eventKey: ' ',
            action: 'CREATE',
        ));
    }
}
