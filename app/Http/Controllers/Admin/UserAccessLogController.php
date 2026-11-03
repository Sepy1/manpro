<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UserAccessLogsExport;
use App\Http\Controllers\Controller;
use App\Models\UserAccessLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class UserAccessLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        $filters = $this->validateFilters($request);
        $query = $this->filteredQuery($filters);

        return view('pages.dashboard.log-access-user', [
            'logs' => $query->orderByDesc('event_date')->orderByDesc('event_clock')->paginate(25)->withQueryString(),
            'statuses' => UserAccessLog::query()->distinct()->orderBy('status')->pluck('status'),
            'devices' => UserAccessLog::query()->distinct()->orderBy('device_name')->pluck('device_name'),
            'filters' => [
                'keyword' => trim((string) ($filters['keyword'] ?? '')),
                'status' => (string) ($filters['status'] ?? ''),
                'device' => (string) ($filters['device'] ?? ''),
                'date_from' => (string) ($filters['date_from'] ?? ''),
                'date_until' => (string) ($filters['date_until'] ?? ''),
            ],
            'filteredCount' => (clone $query)->count(),
            'totalCount' => UserAccessLog::query()->count(),
        ]);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $filters = $this->validateFilters($request);
        $filename = 'log-akses-user-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new UserAccessLogsExport($this->filteredQuery($filters)), $filename);
    }

    public function destroy(UserAccessLog $accessLog): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        $accessLog->delete();

        return redirect()->route('admin.log-access-user.index')->with('success', 'Log akses user berhasil dihapus.');
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'device' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    private function filteredQuery(array $filters): Builder
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        return UserAccessLog::query()
            ->when($keyword !== '', function (Builder $query) use ($keyword): void {
                $query->where(function (Builder $nested) use ($keyword): void {
                    foreach (['username', 'source_ip', 'destination_ip', 'device_name', 'device_id', 'event_type', 'action', 'message'] as $column) {
                        $nested->orWhere($column, 'like', "%{$keyword}%");
                    }
                });
            })
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['device']), fn (Builder $query) => $query->where('device_name', $filters['device']))
            ->when(! empty($filters['date_from']), fn (Builder $query) => $query->whereDate('event_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_until']), fn (Builder $query) => $query->whereDate('event_date', '<=', $filters['date_until']));
    }
}
