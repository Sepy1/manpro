<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecurityAttackLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityAttackLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device' => ['required', 'string', 'max:255'],
            'severity' => ['required', 'string', 'max:50'],
            'attack' => ['required', 'string', 'max:5000'],
            'attackid' => ['required', 'string', 'max:100'],
            'subtype' => ['required', 'string', 'max:100'],
            'action' => ['required', 'string', 'max:50'],
            'srcip' => ['required', 'ip'],
            'srcport' => ['required', 'integer', 'min:0', 'max:65535'],
            'dstip' => ['required', 'ip'],
            'dstport' => ['required', 'integer', 'min:0', 'max:65535'],
            'service' => ['required', 'string', 'max:100'],
            'method' => ['nullable', 'string', 'max:50'],
            'url' => ['nullable', 'string', 'max:5000'],
            'time' => ['required', 'string', 'max:100'],
        ]);

        $event = SecurityAttackLog::query()->create([
            'device' => $validated['device'],
            'severity' => $validated['severity'],
            'attack' => $validated['attack'],
            'attackid' => $validated['attackid'],
            'subtype' => $validated['subtype'],
            'action' => $validated['action'],
            'srcip' => $validated['srcip'],
            'srcport' => $validated['srcport'],
            'dstip' => $validated['dstip'],
            'dstport' => $validated['dstport'],
            'service' => $validated['service'],
            'method' => $validated['method'] ?? '-',
            'url' => $validated['url'] ?? '-',
            'event_time' => $validated['time'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Log security berhasil diterima.',
            'data' => [
                'id' => $event->id,
                'time' => $event->event_time,
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
