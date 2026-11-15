<?php

declare(strict_types=1);

namespace Tests\Framework\Domains\Audit;

use App\Domains\Audit\Services\AuditService;
use App\Shared\DTOs\AuditEntryDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

    public function test_records_http_request_context(): void
    {
        $this->app['request']->server->set(
            'REMOTE_ADDR',
            '192.0.2.10',
        );

        $this->app['request']->headers->set(
            'User-Agent',
            'Mozilla/5.0 MAHLINE Audit Test',
        );

        $this->app['request']->attributes->set(
            'correlation_id',
            'test-correlation-001',
        );

        $event = app(AuditService::class)->record(
            new AuditEntryDTO(
                eventKey: 'TEST_HTTP_CONTEXT',
                action: 'TEST',
            ),
        );

        self::assertSame('192.0.2.10', $event->ip_address);

        self::assertSame(
            'Mozilla/5.0 MAHLINE Audit Test',
            $event->user_agent,
        );

        self::assertSame(
            'test-correlation-001',
            $event->correlation_id,
        );
    }

    public function test_uses_ip_address_from_current_http_request(): void
    {
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            ['REMOTE_ADDR' => '203.0.113.10'],
        );

        $this->app->instance('request', $request);

        $event = app(AuditService::class)->record(
            new AuditEntryDTO(
                eventKey: 'TEST_IP_ADDRESS',
                action: 'CREATE',
            )
        );

        $this->assertSame('203.0.113.10', $event->ip_address);
    }

    public function test_uses_user_agent_from_current_http_request(): void
    {
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_USER_AGENT' => 'MAHLINE-Test-Agent/1.0',
            ],
        );

        $this->app->instance('request', $request);

        $event = app(AuditService::class)->record(
            new AuditEntryDTO(
                eventKey: 'TEST_USER_AGENT',
                action: 'CREATE',
            )
        );

        $this->assertSame(
            'MAHLINE-Test-Agent/1.0',
            $event->user_agent,
        );
    }

    public function test_uses_correlation_id_from_current_http_request(): void
    {
        $request = Request::create('/', 'GET');

        $request->attributes->set(
            'correlation_id',
            'test-correlation-123',
        );

        $this->app->instance('request', $request);

        $event = app(AuditService::class)->record(
            new AuditEntryDTO(
                eventKey: 'TEST_CORRELATION_ID',
                action: 'CREATE',
            )
        );

        $this->assertSame(
            'test-correlation-123',
            $event->correlation_id,
        );
    }

    public function test_explicit_dto_request_metadata_takes_priority(): void
    {
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_USER_AGENT' => 'Request-Agent/1.0',
            ],
        );

        $request->attributes->set(
            'correlation_id',
            'request-correlation-id',
        );

        $this->app->instance('request', $request);

        $event = app(AuditService::class)->record(
            new AuditEntryDTO(
                eventKey: 'TEST_DTO_PRIORITY',
                action: 'CREATE',
                ipAddress: '198.51.100.20',
                userAgent: 'DTO-Agent/2.0',
                correlationId: 'dto-correlation-id',
            )
        );

        $this->assertSame('198.51.100.20', $event->ip_address);
        $this->assertSame('DTO-Agent/2.0', $event->user_agent);
        $this->assertSame('dto-correlation-id', $event->correlation_id);
    }
}
