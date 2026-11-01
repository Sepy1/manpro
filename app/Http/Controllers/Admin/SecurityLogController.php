<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityAttackLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', 'string', 'max:50'],
        ]);
        $keyword = trim((string) ($validated['keyword'] ?? ''));

        $query = SecurityAttackLog::query()
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
            ->when(! empty($validated['severity']), fn ($builder) => $builder->where('severity', $validated['severity']));

        return view('pages.dashboard.log-security', [
            'logs' => $query->latest('created_at')->paginate(25)->withQueryString(),
            'severities' => SecurityAttackLog::query()
                ->distinct()
                ->orderBy('severity')
                ->pluck('severity'),
            'filters' => [
                'keyword' => $keyword,
                'severity' => (string) ($validated['severity'] ?? ''),
            ],
            'filteredCount' => (clone $query)->count(),
            'totalCount' => SecurityAttackLog::query()->count(),
        ]);
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
