<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupMonitoringEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupMonitoringEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d H:i:s'],
            'nama_db' => ['required', 'string', 'max:255'],
            'ukuran' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'status' => ['required', 'string', 'max:50'],
            'lokasi_file' => ['required', 'string', 'max:5000'],
            'server' => ['required', 'string', 'max:255'],
        ]);

        $event = BackupMonitoringEvent::query()->create($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Event backup berhasil diterima.',
            'data' => [
                'id' => $event->id,
                'tanggal' => $event->tanggal->format('Y-m-d H:i:s'),
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
