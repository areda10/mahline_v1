<?php

declare(strict_types=1);

namespace App\Domains\Media\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        }

        return $response;
    }
}
