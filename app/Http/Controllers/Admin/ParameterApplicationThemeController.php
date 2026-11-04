<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationThemeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ParameterApplicationThemeController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.parameter.application-theme', [
            'themes' => ApplicationThemeSetting::THEMES,
            'activeTheme' => ApplicationThemeSetting::activeTheme(),
            'fontFamilies' => ApplicationThemeSetting::FONT_FAMILIES,
            'fontSizes' => ApplicationThemeSetting::FONT_SIZES,
            'fontWeights' => ApplicationThemeSetting::FONT_WEIGHTS,
            'activeFontFamily' => ApplicationThemeSetting::activeFontFamily(),
            'activeFontSize' => ApplicationThemeSetting::activeFontSize(),
            'activeFontWeight' => ApplicationThemeSetting::activeFontWeight(),
            'activeLogoUrl' => ApplicationThemeSetting::activeLogoPath()
                ? route('admin.parameter.application-theme.logo', ['v' => ApplicationThemeSetting::query()->max('updated_at')])
                : null,
            'activeLoginLogoUrl' => ApplicationThemeSetting::activeLoginLogoPath()
                ? route('branding.login-logo', ['v' => ApplicationThemeSetting::query()->max('updated_at')])
                : null,
        ]);
    }

    public function logo(): BinaryFileResponse
    {
        $path = ApplicationThemeSetting::activeLogoPath();

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function loginLogo(): BinaryFileResponse
    {
        $path = ApplicationThemeSetting::activeLoginLogoPath();

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'theme' => ['required', Rule::in(array_keys(ApplicationThemeSetting::THEMES))],
            'font_family' => ['required', Rule::in(array_keys(ApplicationThemeSetting::FONT_FAMILIES))],
            'font_size' => ['required', Rule::in(array_keys(ApplicationThemeSetting::FONT_SIZES))],
            'font_weight' => ['required', Rule::in(array_keys(ApplicationThemeSetting::FONT_WEIGHTS))],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'login_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldLogoPath = ApplicationThemeSetting::query()->whereKey(1)->value('logo_path');
        $newLogoPath = $request->file('logo')?->store('application-branding', 'public');
        $oldLoginLogoPath = ApplicationThemeSetting::query()->whereKey(1)->value('login_logo_path');
        $newLoginLogoPath = $request->file('login_logo')?->store('application-branding', 'public');

        ApplicationThemeSetting::query()->updateOrCreate(
            ['id' => 1],
            array_filter([
                'theme' => $validated['theme'],
                'font_family' => $validated['font_family'],
                'font_size' => $validated['font_size'],
                'font_weight' => $validated['font_weight'],
                'logo_path' => $newLogoPath,
                'login_logo_path' => $newLoginLogoPath,
            ], static fn ($value) => $value !== null),
        );

        if ($newLogoPath && is_string($oldLogoPath) && $oldLogoPath !== $newLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        if ($newLoginLogoPath && is_string($oldLoginLogoPath) && $oldLoginLogoPath !== $newLoginLogoPath) {
            Storage::disk('public')->delete($oldLoginLogoPath);
        }

        return back()->with('status', 'Tema aplikasi berhasil diterapkan.');
    }
}
