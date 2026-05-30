<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LivestreamSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ParameterLivestreamController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $resolved = LivestreamSetting::resolved();

        return view('pages.dashboard.parameter.livestream', [
            'pageOptions' => LivestreamSetting::pageOptions(),
            'tvResolutionOptions' => LivestreamSetting::tvResolutionOptions(),
            'selectedPages' => $resolved['selected_pages'],
            'customPages' => $resolved['custom_pages'],
            'imageSlides' => $resolved['image_slides'],
            'swipeIntervalSeconds' => $resolved['swipe_interval_seconds'],
            'liveRefreshSeconds' => $resolved['live_refresh_seconds'],
            'tvResolution' => $resolved['tv_resolution'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $allowedPageKeys = array_keys(LivestreamSetting::pageOptions());
        $allowedTvResolutions = array_keys(LivestreamSetting::tvResolutionOptions());

        $validated = $request->validate([
            'swipe_interval_seconds' => [
                'required',
                'integer',
                'min:'.LivestreamSetting::MIN_INTERVAL_SECONDS,
                'max:'.LivestreamSetting::MAX_INTERVAL_SECONDS,
            ],
            'live_refresh_seconds' => [
                'required',
                'integer',
                'min:'.LivestreamSetting::MIN_LIVE_REFRESH_SECONDS,
                'max:'.LivestreamSetting::MAX_LIVE_REFRESH_SECONDS,
            ],
            'tv_resolution' => ['required', 'string', 'in:'.implode(',', $allowedTvResolutions)],
            'selected_pages' => ['nullable', 'array'],
            'selected_pages.*' => ['required', 'string', 'in:'.implode(',', $allowedPageKeys)],
            'custom_pages' => ['nullable', 'array'],
            'custom_pages.*' => ['nullable', 'string', 'max:255', 'regex:/^\/(?!\/)[^\s#]*$/'],
            'remove_image_slides' => ['nullable', 'array'],
            'remove_image_slides.*' => ['nullable', 'string', 'max:255'],
            'slide_images' => ['nullable'],
            'slide_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $selectedPages = LivestreamSetting::normalizeSelectedPages((array) ($validated['selected_pages'] ?? []));
        $customPages = LivestreamSetting::normalizeCustomPages((array) ($validated['custom_pages'] ?? []));
        $removeImageSlides = LivestreamSetting::normalizeImageSlides((array) ($validated['remove_image_slides'] ?? []));

        $setting = LivestreamSetting::query()->first() ?? new LivestreamSetting();
        $currentImageSlides = LivestreamSetting::normalizeImageSlides((array) ($setting->image_slides ?? []));
        $keptImageSlides = array_values(array_diff($currentImageSlides, $removeImageSlides));

        foreach ($removeImageSlides as $removePath) {
            Storage::disk('public')->delete($removePath);
        }

        $uploadedSlides = [];
        $slideUploads = [];
        $rawSlideFiles = $request->allFiles()['slide_images'] ?? [];
        foreach (Arr::flatten((array) $rawSlideFiles) as $file) {
            if ($file instanceof UploadedFile) {
                $slideUploads[] = $file;
            }
        }
        if (count($slideUploads) > 10) {
            $slideUploads = array_slice($slideUploads, 0, 10);
        }

        foreach ($slideUploads as $upload) {
            if (! $upload instanceof UploadedFile || ! $upload->isValid()) {
                continue;
            }
            $uploadedSlides[] = $upload->store('livestream/slides', 'public');
        }

        $imageSlides = LivestreamSetting::normalizeImageSlides(array_merge($keptImageSlides, $uploadedSlides));

        if ($selectedPages === [] && $customPages === [] && $imageSlides === []) {
            return back()
                ->withErrors([
                    'selected_pages' => 'Pilih minimal 1 sumber slide (halaman bawaan/custom atau gambar).',
                ])
                ->withInput();
        }

        $setting->swipe_interval_seconds = (int) $validated['swipe_interval_seconds'];
        $setting->live_refresh_seconds = (int) $validated['live_refresh_seconds'];
        $setting->tv_resolution = LivestreamSetting::normalizeTvResolution((string) $validated['tv_resolution']);
        $setting->selected_pages = $selectedPages;
        $setting->custom_pages = $customPages;
        $setting->image_slides = $imageSlides;
        $setting->save();

        return redirect()->route('admin.parameter.livestream.index')
            ->with('status', 'Pengaturan livestream diperbarui.');
    }
}
