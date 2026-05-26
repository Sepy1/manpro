<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LivestreamSetting;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class LivestreamPlayerController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $resolved = LivestreamSetting::resolved();
        $options = LivestreamSetting::pageOptions();
        $pages = [];

        foreach ($resolved['selected_pages'] as $pageKey) {
            $meta = $options[$pageKey] ?? null;
            $routeName = $meta['route'] ?? null;
            if (! is_array($meta) || ! is_string($routeName) || ! Route::has($routeName)) {
                continue;
            }

            $pages[] = [
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
                'label' => $customPath,
                'url' => $this->appendQuery(url($customPath), [
                    'livestream' => '1',
                    'livestream_embed' => '1',
                ]),
            ];
        }

        if ($pages === []) {
            $pages[] = [
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
