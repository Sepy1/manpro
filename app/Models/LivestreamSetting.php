<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

class LivestreamSetting extends Model
{
    public const DEFAULT_INTERVAL_SECONDS = 120;

    public const DEFAULT_LIVE_REFRESH_SECONDS = 30;

    public const DEFAULT_TV_RESOLUTION = 'fhd_1920x1080';

    public const MIN_INTERVAL_SECONDS = 5;

    public const MAX_INTERVAL_SECONDS = 3600;

    public const MIN_LIVE_REFRESH_SECONDS = 5;

    public const MAX_LIVE_REFRESH_SECONDS = 300;

    protected $table = 'livestream_settings';

    protected $fillable = [
        'swipe_interval_seconds',
        'live_refresh_seconds',
        'tv_resolution',
        'selected_pages',
        'custom_pages',
        'image_slides',
    ];

    protected function casts(): array
    {
        return [
            'swipe_interval_seconds' => 'integer',
            'live_refresh_seconds' => 'integer',
            'tv_resolution' => 'string',
            'selected_pages' => 'array',
            'custom_pages' => 'array',
            'image_slides' => 'array',
        ];
    }

    public static function tvResolutionOptions(): array
    {
        return [
            'nHD_640x360' => ['label' => 'nHD - 640 x 360', 'width' => 640, 'height' => 360],
            'sd_854x480' => ['label' => 'SD - 854 x 480', 'width' => 854, 'height' => 480],
            'xga_1024x576' => ['label' => 'XGA - 1024 x 576', 'width' => 1024, 'height' => 576],
            'hd_1366x768' => ['label' => 'HD - 1366 x 768', 'width' => 1366, 'height' => 768],
            'fhd_1920x1080' => ['label' => 'Full HD - 1920 x 1080', 'width' => 1920, 'height' => 1080],
            'qhd_2560x1440' => ['label' => 'QHD - 2560 x 1440', 'width' => 2560, 'height' => 1440],
            'uhd_3840x2160' => ['label' => '4K UHD - 3840 x 2160', 'width' => 3840, 'height' => 2160],
        ];
    }

    public static function normalizeTvResolution(?string $value): string
    {
        $value = trim((string) $value);
        $allowed = array_keys(self::tvResolutionOptions());

        return in_array($value, $allowed, true) ? $value : self::DEFAULT_TV_RESOLUTION;
    }

    public static function pageOptions(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'route' => 'admin.dashboard',
            ],
            'cr_eksternal' => [
                'label' => 'CR Eksternal',
                'route' => 'admin.cr-eksternal.index',
            ],
            'data_center' => [
                'label' => 'Aset TI - Data Center',
                'route' => 'admin.aset-ti.data-center',
            ],
        ];
    }

    public static function defaultSelectedPages(): array
    {
        return array_keys(self::pageOptions());
    }

    public static function normalizeSelectedPages(array $pages): array
    {
        $allowed = array_keys(self::pageOptions());
        $normalized = array_values(array_unique(array_intersect($allowed, $pages)));

        return $normalized;
    }

    public static function normalizeCustomPages(array $pages): array
    {
        $normalized = [];

        foreach ($pages as $page) {
            $sanitized = self::sanitizeCustomPath((string) $page);
            if ($sanitized === null) {
                continue;
            }

            $normalized[] = $sanitized;
        }

        return array_values(array_unique($normalized));
    }

    public static function resolved(): array
    {
        $defaults = [
            'swipe_interval_seconds' => self::DEFAULT_INTERVAL_SECONDS,
            'live_refresh_seconds' => self::DEFAULT_LIVE_REFRESH_SECONDS,
            'tv_resolution' => self::DEFAULT_TV_RESOLUTION,
            'selected_pages' => self::defaultSelectedPages(),
            'custom_pages' => [],
            'image_slides' => [],
        ];

        try {
            $setting = self::query()->first();
            if (! $setting) {
                return $defaults;
            }

            $seconds = (int) ($setting->swipe_interval_seconds ?? self::DEFAULT_INTERVAL_SECONDS);
            $seconds = max(min($seconds, self::MAX_INTERVAL_SECONDS), self::MIN_INTERVAL_SECONDS);
            $liveRefreshSeconds = (int) ($setting->live_refresh_seconds ?? self::DEFAULT_LIVE_REFRESH_SECONDS);
            $liveRefreshSeconds = max(min($liveRefreshSeconds, self::MAX_LIVE_REFRESH_SECONDS), self::MIN_LIVE_REFRESH_SECONDS);

            return [
                'swipe_interval_seconds' => $seconds,
                'live_refresh_seconds' => $liveRefreshSeconds,
                'tv_resolution' => self::normalizeTvResolution($setting->tv_resolution),
                'selected_pages' => self::normalizeSelectedPages((array) ($setting->selected_pages ?? [])),
                'custom_pages' => self::normalizeCustomPages((array) ($setting->custom_pages ?? [])),
                'image_slides' => self::normalizeImageSlides((array) ($setting->image_slides ?? [])),
            ];
        } catch (Throwable) {
            return $defaults;
        }
    }

    public static function normalizeImageSlides(array $slides): array
    {
        $normalized = [];

        foreach ($slides as $slide) {
            $value = str_replace('\\', '/', trim((string) $slide));
            $value = ltrim($value, '/');
            if ($value === '') {
                continue;
            }
            if (! Str::startsWith($value, 'livestream/slides/')) {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    public static function selectedBasePaths(): array
    {
        $resolved = self::resolved();
        $options = self::pageOptions();
        $paths = [];

        foreach ($resolved['selected_pages'] as $pageKey) {
            $routeName = $options[$pageKey]['route'] ?? null;
            if (! is_string($routeName) || ! Route::has($routeName)) {
                continue;
            }

            $path = parse_url(route($routeName), PHP_URL_PATH);
            if (is_string($path) && Str::startsWith($path, '/')) {
                $paths[] = $path;
            }
        }

        foreach ($resolved['custom_pages'] as $customPath) {
            if (! is_string($customPath)) {
                continue;
            }
            $sanitized = self::sanitizeCustomPath($customPath);
            if ($sanitized === null) {
                continue;
            }
            $basePath = parse_url($sanitized, PHP_URL_PATH);
            if (is_string($basePath) && Str::startsWith($basePath, '/')) {
                $paths[] = $basePath;
            }
        }

        return array_values(array_unique($paths));
    }

    public static function selectedLivestreamUrls(): array
    {
        $urls = [];
        $resolved = self::resolved();
        $options = self::pageOptions();

        foreach ($resolved['selected_pages'] as $pageKey) {
            $routeName = $options[$pageKey]['route'] ?? null;
            if (! is_string($routeName)) {
                continue;
            }
            if (! Route::has($routeName)) {
                continue;
            }

            $urls[] = self::withLivestreamFlag(route($routeName));
        }

        foreach ($resolved['custom_pages'] as $customPath) {
            if (! is_string($customPath)) {
                continue;
            }
            $sanitized = self::sanitizeCustomPath($customPath);
            if ($sanitized === null) {
                continue;
            }

            $urls[] = self::withLivestreamFlag(url($sanitized));
        }

        return array_values(array_unique($urls));
    }

    private static function withLivestreamFlag(string $url): string
    {
        $parts = parse_url($url);
        $query = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['livestream'] = 1;

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

    private static function sanitizeCustomPath(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || ! Str::startsWith($value, '/')) {
            return null;
        }
        if (Str::startsWith($value, '//') || Str::contains($value, ['#'])) {
            return null;
        }

        $parts = parse_url($value);
        if ($parts === false) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || ! Str::startsWith($path, '/')) {
            return null;
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        unset($query['livestream'], $query['livestream_embed']);
        $queryString = http_build_query($query);

        return $queryString !== '' ? $path.'?'.$queryString : $path;
    }
}
