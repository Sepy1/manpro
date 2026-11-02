<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationThemeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParameterApplicationThemeController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.parameter.application-theme', [
            'themes' => ApplicationThemeSetting::THEMES,
            'activeTheme' => ApplicationThemeSetting::activeTheme(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'theme' => ['required', Rule::in(array_keys(ApplicationThemeSetting::THEMES))],
        ]);

        ApplicationThemeSetting::query()->updateOrCreate(
            ['id' => 1],
            ['theme' => $validated['theme']],
        );

        return back()->with('status', 'Tema aplikasi berhasil diterapkan.');
    }
}
