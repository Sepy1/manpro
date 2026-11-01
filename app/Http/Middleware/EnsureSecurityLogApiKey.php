<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSecurityLogApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('services.security_log.api_key');
        if ($configuredKey === '') {
            return response()->json([
                'ok' => false,
                'message' => 'API log security belum dikonfigurasi.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $providedKey = (string) $request->header('X-Security-Log-Api-Key');
        if ($providedKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'ok' => false,
                'message' => 'API key tidak valid.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
