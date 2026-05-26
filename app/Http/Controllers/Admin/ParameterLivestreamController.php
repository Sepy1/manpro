<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LivestreamSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParameterLivestreamController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $resolved = LivestreamSetting::resolved();

        return view('pages.dashboard.parameter.livestream', [
            'pageOptions' => LivestreamSetting::pageOptions(),
            'selectedPages' => $resolved['selected_pages'],
            'customPages' => $resolved['custom_pages'],
            'swipeIntervalSeconds' => $resolved['swipe_interval_seconds'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $allowedPageKeys = array_keys(LivestreamSetting::pageOptions());

        $validated = $request->validate([
            'swipe_interval_seconds' => [
                'required',
                'integer',
                'min:'.LivestreamSetting::MIN_INTERVAL_SECONDS,
                'max:'.LivestreamSetting::MAX_INTERVAL_SECONDS,
            ],
            'selected_pages' => ['nullable', 'array'],
            'selected_pages.*' => ['required', 'string', 'in:'.implode(',', $allowedPageKeys)],
            'custom_pages' => ['nullable', 'array'],
            'custom_pages.*' => ['nullable', 'string', 'max:255', 'regex:/^\/(?!\/)[^\s#]*$/'],
        ]);

        $selectedPages = LivestreamSetting::normalizeSelectedPages((array) ($validated['selected_pages'] ?? []));
        $customPages = LivestreamSetting::normalizeCustomPages((array) ($validated['custom_pages'] ?? []));

        if ($selectedPages === [] && $customPages === []) {
            return back()
                ->withErrors([
                    'selected_pages' => 'Pilih minimal 1 halaman bawaan atau tambahkan 1 halaman custom.',
                ])
                ->withInput();
        }

        $setting = LivestreamSetting::query()->first() ?? new LivestreamSetting();
        $setting->swipe_interval_seconds = (int) $validated['swipe_interval_seconds'];
        $setting->selected_pages = $selectedPages;
        $setting->custom_pages = $customPages;
        $setting->save();

        return redirect()->route('admin.parameter.livestream.index')
            ->with('status', 'Pengaturan livestream diperbarui.');
    }
}
