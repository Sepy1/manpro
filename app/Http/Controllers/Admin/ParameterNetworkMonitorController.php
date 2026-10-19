<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetworkMonitorSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParameterNetworkMonitorController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.parameter.network-monitor', [
            'intervalMinutes' => NetworkMonitorSetting::intervalMinutes(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'polling_interval_minutes' => [
                'required', 'integer',
                'min:'.NetworkMonitorSetting::MIN_INTERVAL_MINUTES,
                'max:'.NetworkMonitorSetting::MAX_INTERVAL_MINUTES,
            ],
        ]);

        NetworkMonitorSetting::query()->updateOrCreate(
            ['id' => 1],
            ['polling_interval_minutes' => (int) $validated['polling_interval_minutes']]
        );

        return back()->with('status', 'Interval polling ping berhasil diperbarui.');
    }
}
