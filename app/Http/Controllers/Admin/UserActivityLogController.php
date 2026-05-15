<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'activity_type' => ['nullable', Rule::in([
                UserActivityLog::TYPE_LOGIN,
                UserActivityLog::TYPE_LOGIN_FAILED,
                UserActivityLog::TYPE_MENU_OPEN,
            ])],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $query = UserActivityLog::query()
            ->with(['user:id,name,email,role'])
            ->when(!empty($validated['user_id']), function ($builder) use ($validated): void {
                $builder->where('user_id', (int) $validated['user_id']);
            })
            ->when(!empty($validated['activity_type']), function ($builder) use ($validated): void {
                $builder->where('activity_type', $validated['activity_type']);
            })
            ->when(!empty($validated['period_start']), function ($builder) use ($validated): void {
                $builder->whereDate('created_at', '>=', $validated['period_start']);
            })
            ->when(!empty($validated['period_end']), function ($builder) use ($validated): void {
                $builder->whereDate('created_at', '<=', $validated['period_end']);
            })
            ->when(!empty($validated['keyword']), function ($builder) use ($validated): void {
                $keyword = trim((string) $validated['keyword']);
                $builder->where(function ($nested) use ($keyword): void {
                    $nested->where('route_name', 'like', "%{$keyword}%")
                        ->orWhere('menu_name', 'like', "%{$keyword}%")
                        ->orWhere('url', 'like', "%{$keyword}%")
                        ->orWhere('ip_address', 'like', "%{$keyword}%")
                        ->orWhereHas('user', function ($userQuery) use ($keyword): void {
                            $userQuery->where('name', 'like', "%{$keyword}%")
                                ->orWhere('email', 'like', "%{$keyword}%");
                        });
                });
            });

        return view('pages.dashboard.log-user', [
            'logs' => $query->latest('created_at')->get(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'role']),
            'activityTypes' => [
                UserActivityLog::TYPE_LOGIN => 'Login',
                UserActivityLog::TYPE_LOGIN_FAILED => 'Login Gagal',
                UserActivityLog::TYPE_MENU_OPEN => 'Buka Menu',
            ],
            'filters' => [
                'user_id' => (string) ($validated['user_id'] ?? ''),
                'activity_type' => (string) ($validated['activity_type'] ?? ''),
                'period_start' => (string) ($validated['period_start'] ?? ''),
                'period_end' => (string) ($validated['period_end'] ?? ''),
                'keyword' => (string) ($validated['keyword'] ?? ''),
            ],
        ]);
    }
}
