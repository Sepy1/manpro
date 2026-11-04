<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityAttackLog;
use App\Services\AttackIpGeolocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttackMapController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.attack-map', [
            'severities' => SecurityAttackLog::query()->distinct()->orderBy('severity')->pluck('severity'),
            'initialFilters' => $request->only(['keyword', 'severity', 'device', 'action', 'source', 'destination', 'date_from', 'date_until']),
        ]);
    }

    public function data(Request $request, AttackIpGeolocationService $geo): JsonResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        $validated = $request->validate([
            'hours' => ['nullable', 'integer', 'in:1,6,12,24,72,168'],
            'severity' => ['nullable', 'string', 'max:50'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'device' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:45'],
            'destination' => ['nullable', 'string', 'max:45'],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $hours = (int) ($validated['hours'] ?? 24);
        $hasDateRange = ! empty($validated['date_from']) || ! empty($validated['date_until']);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $logs = SecurityAttackLog::query()
            ->when(! $hasDateRange, fn ($query) => $query->where('created_at', '>=', now()->subHours($hours)))
            ->when($validated['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($validated['device'] ?? null, fn ($query, $device) => $query->where('device', $device))
            ->when($validated['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($validated['source'] ?? null, fn ($query, $source) => $query->where('srcip', 'like', '%'.trim($source).'%'))
            ->when($validated['destination'] ?? null, fn ($query, $destination) => $query->where('dstip', 'like', '%'.trim($destination).'%'))
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->whereDate('event_time', '>=', $date))
            ->when($validated['date_until'] ?? null, fn ($query, $date) => $query->whereDate('event_time', '<=', $date))
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($nested) use ($keyword) {
                    foreach (['device', 'attack', 'attackid', 'subtype', 'action', 'srcip', 'dstip', 'service', 'method', 'url', 'event_time'] as $column) {
                        $nested->orWhere($column, 'like', "%{$keyword}%");
                    }
                });
            })
            ->latest('created_at')->limit(500)->get();

        $groups = $logs->groupBy(fn ($log) => $log->srcip.'|'.$log->dstip.'|'.$log->severity)
            ->map(fn ($items) => [
                'source_ip' => $items->first()->srcip,
                'source_port' => $items->first()->srcport,
                'destination_ip' => $items->first()->dstip,
                'destination_port' => $items->first()->dstport,
                'severity' => $items->first()->severity,
                'attack' => $items->first()->attack,
                'action' => $items->first()->action,
                'device' => $items->first()->device,
                'service' => $items->first()->service,
                'method' => $items->first()->method,
                'url' => $items->first()->url,
                'count' => $items->count(),
                'last_event' => $items->first()->event_time,
            ])->sortByDesc('count')->take(60)->values();

        $locations = $geo->resolveMany($groups->flatMap(fn ($group) => [$group['source_ip'], $group['destination_ip']])->all());
        $arcs = $groups->map(function ($group) use ($locations) {
            $source = $locations[$group['source_ip']] ?? null;
            $destination = $locations[$group['destination_ip']] ?? null;
            return $source && $destination ? [...$group, 'source' => $source, 'destination' => $destination] : null;
        })->filter()->values();

        $countries = $arcs->groupBy(fn ($arc) => $arc['source']['country'])
            ->map(fn ($items, $country) => ['country' => $country, 'count' => $items->sum('count')])
            ->sortByDesc('count')->take(10)->values();

        return response()->json([
            'arcs' => $arcs,
            'countries' => $countries,
            'summary' => [
                'total' => $logs->count(),
                'sources' => $logs->pluck('srcip')->unique()->count(),
                'countries' => $arcs->pluck('source.country')->unique()->count(),
                'blocked' => $logs->filter(fn ($log) => str_contains(strtolower($log->action), 'block'))->count(),
            ],
            'generated_at' => now()->toIso8601String(),
            'geolocation' => $geo->diagnostics(),
        ]);
    }
}
