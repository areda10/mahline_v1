<?php

declare(strict_types=1);

namespace App\Domains\Media\Http\Middleware;

use App\Domains\Audit\Repositories\AuditEventRepository;
use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Users\Models\User;
use App\Shared\DTOs\AuditEntryDTO;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class MediaRootAccessAuditMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User || ! $actor->isAuthorized() || ! $actor->hasRole('super_admin_root')) {
            if ($actor instanceof User) {
                try {
                    $events = app(AuditEventRepository::class);
                    if ($events->verifyIntegrity()) {
                        app(AuditService::class)->record(new AuditEntryDTO(
                            eventKey: 'media.root_operation_denied',
                            action: 'root_operation_denied',
                            actorType: User::class,
                            actorId: (string) $actor->getKey(),
                            metadata: [
                                'route_name' => (string) $request->route()?->getName(),
                                'method' => $request->method(),
                            ],
                        ));
                    }
                } catch (\Throwable $exception) {
                    Log::critical('MEDIA root-operation denial could not be audited.', [
                        'exception' => $exception::class,
                    ]);
                }
            }

            abort(403);
        }

        return $next($request);
    }
}
