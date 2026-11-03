<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAccessLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserAccessLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $events = array_is_list($payload) ? $payload : [$payload];

        $validated = validator(['events' => $events], [
            'events' => ['required', 'array', 'min:1', 'max:500'],
            'events.*.vendor' => ['nullable', 'string', 'max:100'],
            'events.*.device_name' => ['nullable', 'string', 'max:255'],
            'events.*.device_id' => ['nullable', 'string', 'max:100'],
            'events.*.virtual_domain' => ['nullable', 'string', 'max:100'],
            'events.*.event_type' => ['nullable', 'string', 'max:100'],
            'events.*.event_category' => ['nullable', 'string', 'max:100'],
            'events.*.username' => ['nullable', 'string', 'max:255'],
            'events.*.auth_server' => ['nullable', 'string', 'max:255'],
            'events.*.source_ip' => ['nullable', 'ip'],
            'events.*.destination_ip' => ['nullable', 'ip'],
            'events.*.tunnel_ip' => ['nullable', 'ip'],
            'events.*.source_interface' => ['nullable', 'string', 'max:255'],
            'events.*.destination_interface' => ['nullable', 'string', 'max:255'],
            'events.*.method' => ['nullable', 'string', 'max:50'],
            'events.*.action' => ['nullable', 'string', 'max:100'],
            'events.*.status' => ['nullable', 'string', 'max:50'],
            'events.*.original_status' => ['nullable', 'string', 'max:100'],
            'events.*.reason' => ['nullable', 'string', 'max:255'],
            'events.*.profile' => ['nullable', 'string', 'max:255'],
            'events.*.date' => ['nullable', 'date_format:Y-m-d'],
            'events.*.time' => ['nullable', 'date_format:H:i:s'],
            'events.*.timezone' => ['nullable', 'string', 'max:10'],
            'events.*.event_time' => ['nullable', 'string', 'max:100'],
            'events.*.log_id' => ['nullable', 'string', 'max:100'],
            'events.*.log_description' => ['nullable', 'string'],
            'events.*.severity' => ['nullable', 'string', 'max:50'],
            'events.*.message' => ['nullable', 'string'],
            'events.*.raw_log' => ['nullable', 'string'],
        ])->validate()['events'];

        $ids = DB::transaction(function () use ($validated): array {
            return collect($validated)->map(function (array $event): int {
                $event['event_date'] = $event['date'] ?? null;
                $event['event_clock'] = $event['time'] ?? null;
                unset($event['date'], $event['time']);

                return UserAccessLog::query()->create($event)->id;
            })->all();
        });

        return response()->json([
            'ok' => true,
            'message' => count($ids).' log akses user berhasil diterima.',
            'data' => ['count' => count($ids), 'ids' => $ids],
        ], JsonResponse::HTTP_CREATED);
    }
}
