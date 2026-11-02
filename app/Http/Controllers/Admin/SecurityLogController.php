<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SecurityAttackLogsExport;
use App\Http\Controllers\Controller;
use App\Models\SecurityAttackLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class SecurityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $this->validateFilters($request);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $query = $this->filteredLogsQuery($validated);

        return view('pages.dashboard.log-security', [
            'logs' => $query->latest('created_at')->paginate(25)->withQueryString(),
            'severities' => SecurityAttackLog::query()->distinct()->orderBy('severity')->pluck('severity'),
            'devices' => SecurityAttackLog::query()->distinct()->orderBy('device')->pluck('device'),
            'actions' => SecurityAttackLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => [
                'keyword' => $keyword,
                'severity' => (string) ($validated['severity'] ?? ''),
                'device' => (string) ($validated['device'] ?? ''),
                'action' => (string) ($validated['action'] ?? ''),
                'source' => trim((string) ($validated['source'] ?? '')),
                'destination' => trim((string) ($validated['destination'] ?? '')),
                'date_from' => (string) ($validated['date_from'] ?? ''),
                'date_until' => (string) ($validated['date_until'] ?? ''),
            ],
            'filteredCount' => (clone $query)->count(),
            'totalCount' => SecurityAttackLog::query()->count(),
        ]);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $filters = $this->validateFilters($request);
        $filename = 'log-security-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new SecurityAttackLogsExport($this->filteredLogsQuery($filters)), $filename);
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', 'string', 'max:50'],
            'device' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:45'],
            'destination' => ['nullable', 'string', 'max:45'],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    private function filteredLogsQuery(array $filters): Builder
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        return SecurityAttackLog::query()
            ->when($keyword !== '', function ($builder) use ($keyword): void {
                $builder->where(function ($nested) use ($keyword): void {
                    foreach ([
                        'device',
                        'attack',
                        'attackid',
                        'subtype',
                        'action',
                        'srcip',
                        'dstip',
                        'service',
                        'method',
                        'url',
                        'event_time',
                    ] as $column) {
                        $nested->orWhere($column, 'like', "%{$keyword}%");
                    }
                });
            })
            ->when(! empty($filters['severity']), fn ($builder) => $builder->where('severity', $filters['severity']))
            ->when(! empty($filters['device']), fn ($builder) => $builder->where('device', $filters['device']))
            ->when(! empty($filters['action']), fn ($builder) => $builder->where('action', $filters['action']))
            ->when(! empty($filters['source']), fn ($builder) => $builder->where('srcip', 'like', '%'.trim($filters['source']).'%'))
            ->when(! empty($filters['destination']), fn ($builder) => $builder->where('dstip', 'like', '%'.trim($filters['destination']).'%'))
            ->when(! empty($filters['date_from']), fn ($builder) => $builder->whereDate('event_time', '>=', $filters['date_from']))
            ->when(! empty($filters['date_until']), fn ($builder) => $builder->whereDate('event_time', '<=', $filters['date_until']));
    }

    public function destroy(SecurityAttackLog $securityLog): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $securityLog->delete();

        return redirect()
            ->route('admin.log-security.index')
            ->with('success', 'Log security berhasil dihapus.');
    }
}
