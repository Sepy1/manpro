<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LivestreamSetting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LivestreamPlayerController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $resolved = LivestreamSetting::resolved();
        $tvResolution = LivestreamSetting::normalizeTvResolution((string) ($resolved['tv_resolution'] ?? ''));
        $tvResolutionMeta = LivestreamSetting::tvResolutionOptions()[$tvResolution] ?? LivestreamSetting::tvResolutionOptions()[LivestreamSetting::DEFAULT_TV_RESOLUTION];
        $options = LivestreamSetting::pageOptions();
        $pages = [];

        foreach ($resolved['selected_pages'] as $pageKey) {
            $meta = $options[$pageKey] ?? null;
            $routeName = $meta['route'] ?? null;
            if (! is_array($meta) || ! is_string($routeName) || ! Route::has($routeName)) {
                continue;
            }

            $pages[] = [
                'type' => 'page',
                'label' => (string) ($meta['label'] ?? $pageKey),
                'url' => $this->appendQuery(route($routeName), [
                    'livestream' => '1',
                    'livestream_embed' => '1',
                ]),
            ];
        }

        foreach ($resolved['custom_pages'] as $customPath) {
            if (! is_string($customPath) || $customPath === '') {
                continue;
            }

            $pages[] = [
                'type' => 'page',
                'label' => $customPath,
                'url' => $this->appendQuery(url($customPath), [
                    'livestream' => '1',
                    'livestream_embed' => '1',
                ]),
            ];
        }

        foreach ($resolved['image_slides'] as $index => $imagePath) {
            if (! is_string($imagePath) || $imagePath === '') {
                continue;
            }
            if (! Storage::disk('public')->exists($imagePath)) {
                continue;
            }

            $pages[] = [
                'type' => 'image',
                'label' => 'Slide Gambar '.($index + 1),
                'url' => '/storage/'.ltrim($imagePath, '/'),
            ];
        }

        if ($pages === []) {
            $pages[] = [
                'type' => 'page',
                'label' => 'Dashboard',
                'url' => $this->appendQuery(route('admin.dashboard'), [
                    'livestream' => '1',
                    'livestream_embed' => '1',
                ]),
            ];
        }

        return view('pages.dashboard.livestream-player', [
            'pages' => $pages,
            'swipeIntervalMs' => (int) ($resolved['swipe_interval_seconds'] * 1000),
            'liveRefreshMs' => (int) ($resolved['live_refresh_seconds'] * 1000),
            'tvResolutionLabel' => (string) ($tvResolutionMeta['label'] ?? ''),
            'tvWidth' => (int) ($tvResolutionMeta['width'] ?? 1920),
            'tvHeight' => (int) ($tvResolutionMeta['height'] ?? 1080),
            'exitUrl' => route('admin.dashboard'),
        ]);
    }

    private function appendQuery(string $url, array $params): string
    {
        $parts = parse_url($url);
        $query = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($params as $key => $value) {
            $query[$key] = $value;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $queryString = http_build_query($query);

        if ($scheme && $host) {
            return $scheme.'://'.$host.$port.$path.'?'.$queryString;
        }

        return $path.'?'.$queryString;
    }
}
