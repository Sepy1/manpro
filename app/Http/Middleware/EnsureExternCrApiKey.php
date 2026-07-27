<?php

namespace App\Http\Middleware;

use App\Models\ExternCrApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureExternCrApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('extern_cr_api_keys')) {
            return response()->json([
                'ok' => false,
                'message' => 'Tabel API key CR eksternal belum tersedia.',
            ], 503);
        }

        $providedKey = trim((string) $request->header('X-Extern-Cr-Api-Key', ''));
        if ($providedKey === '') {
            return response()->json([
                'ok' => false,
                'message' => 'API key tidak valid.',
            ], 401);
        }

        $keyHash = hash('sha256', $providedKey);
        $record = ExternCrApiKey::query()
            ->where('key_hash', $keyHash)
            ->where('is_active', true)
            ->first();

        if ($record === null) {
            return response()->json([
                'ok' => false,
                'message' => 'API key tidak valid atau tidak aktif.',
            ], 401);
        }

        if ($record->expires_at !== null && $record->expires_at->isPast()) {
            return response()->json([
                'ok' => false,
                'message' => 'API key sudah kedaluwarsa.',
            ], 401);
        }

        $record->forceFill([
            'last_used_at' => Carbon::now(),
        ])->save();

        return $next($request);
    }
}
