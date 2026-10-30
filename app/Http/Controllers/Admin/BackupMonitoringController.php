<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BackupMonitoringEventsExport;
use App\Http\Controllers\Controller;
use App\Models\BackupMonitoringEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class BackupMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $this->validateFilters($request);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $query = $this->filteredEventsQuery($validated);

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

    public function export(Request $request)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $filters = $this->validateFilters($request);
        $filename = 'monitoring-backup-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(
            new BackupMonitoringEventsExport($this->filteredEventsQuery($filters)),
            $filename
        );
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    private function filteredEventsQuery(array $filters): Builder
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        return BackupMonitoringEvent::query()
            ->when($keyword !== '', function ($builder) use ($keyword): void {
                $builder->where(function ($nested) use ($keyword): void {
                    $nested->where('server', 'like', "%{$keyword}%")
                        ->orWhere('nama_db', 'like', "%{$keyword}%")
                        ->orWhere('lokasi_file', 'like', "%{$keyword}%");
                });
            })
            ->when(! empty($filters['status']), fn ($builder) => $builder->where('status', $filters['status']))
            ->when(! empty($filters['date_from']), fn ($builder) => $builder->whereDate('tanggal', '>=', $filters['date_from']))
            ->when(! empty($filters['date_until']), fn ($builder) => $builder->whereDate('tanggal', '<=', $filters['date_until']));
    }
}
