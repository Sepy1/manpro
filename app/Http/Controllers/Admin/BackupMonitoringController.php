<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupMonitoringEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BackupMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $query = BackupMonitoringEvent::query()
            ->when($keyword !== '', function ($builder) use ($keyword): void {
                $builder->where(function ($nested) use ($keyword): void {
                    $nested->where('server', 'like', "%{$keyword}%")
                        ->orWhere('nama_db', 'like', "%{$keyword}%")
                        ->orWhere('lokasi_file', 'like', "%{$keyword}%");
                });
            })
            ->when(! empty($validated['status']), fn ($builder) => $builder->where('status', $validated['status']))
            ->when(! empty($validated['date_from']), fn ($builder) => $builder->whereDate('tanggal', '>=', $validated['date_from']))
            ->when(! empty($validated['date_until']), fn ($builder) => $builder->whereDate('tanggal', '<=', $validated['date_until']));

        $filteredCount = (clone $query)->count();

        return view('pages.dashboard.monitoring-backup', [
            'events' => $query
                ->orderByDesc('tanggal')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'statuses' => BackupMonitoringEvent::query()
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status'),
            'filters' => [
                'keyword' => $keyword,
                'status' => (string) ($validated['status'] ?? ''),
                'date_from' => (string) ($validated['date_from'] ?? ''),
                'date_until' => (string) ($validated['date_until'] ?? ''),
            ],
            'filteredCount' => $filteredCount,
            'totalCount' => BackupMonitoringEvent::query()->count(),
        ]);
    }
}
