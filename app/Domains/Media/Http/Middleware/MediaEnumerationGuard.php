<?php

declare(strict_types=1);

namespace App\Domains\Media\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Domains\Audit\Repositories\AuditEventRepository;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use App\Domains\Media\Notifications\MediaSecurityAlertNotification;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

final class MediaEnumerationGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        $ip = (string) ($request->ip() ?? 'unknown');
        $ipHash = hash('sha256', $ip);
        $key = 'media:enumeration:'.$ipHash;
        $attempts = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addMinutes(10));

        if ($attempts === 5 || ($attempts > 5 && $attempts % 5 === 0)) {
            $requestedId = (string) $request->route('media', '');
            Log::warning('MEDIA suspicious sequence of unavailable identifiers detected.', [
                'ip_hash' => $ipHash,
                'requested_id_hash' => hash('sha256', $requestedId),
                'not_found_attempts_10m' => $attempts,
            ]);

            try {
                $events = app(AuditEventRepository::class);
                if ($events->verifyIntegrity()) {
                    app(AuditService::class)->record(new AuditEntryDTO(
                        eventKey: 'media.enumeration_detected',
                        action: 'enumeration_detected',
                        metadata: [
                            'ip_hash' => $ipHash,
                            'requested_id_hash' => hash('sha256', $requestedId),
                            'attempts_10m' => $attempts,
                        ],
                    ));
                } else {
                    Log::critical('MEDIA enumeration event could not be appended because the audit chain is invalid.');
                }
            } catch (\Throwable $exception) {
                Log::critical('MEDIA enumeration audit append failed.', [
                    'exception' => $exception::class,
                ]);
            }

            if ($attempts === 5 || $attempts % 10 === 0) {
                $roots = User::query()
                    ->whereHas('roles', static fn ($query) => $query->where('slug', 'super_admin_root'))
                    ->whereNotNull('email')
                    ->get();

                try {
                    Notification::send(
                        $roots,
                        new MediaSecurityAlertNotification(
                            'media_identifier_enumeration',
                            'unknown',
                            $ipHash,
                            $attempts,
                        ),
                    );
                } catch (\Throwable $exception) {
                    Log::error('MEDIA enumeration notification delivery failed.', [
                        'exception' => $exception::class,
                    ]);
                }
            }
        }

        return $response;
    }
}
