<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogUserMenuActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $routeName = $request->route()?->getName();

        if (!$user || !$request->isMethod('GET') || !is_string($routeName)) {
            return $response;
        }

        if (!$this->shouldLogRoute($routeName)) {
            return $response;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        try {
            UserActivityLog::query()->create([
                'user_id' => $user->id,
                'activity_type' => UserActivityLog::TYPE_MENU_OPEN,
                'route_name' => $routeName,
                'menu_name' => $this->menuNameFromRoute($routeName),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Avoid breaking page navigation when activity logging fails.
        }

        return $response;
    }

    private function shouldLogRoute(string $routeName): bool
    {
        if (str_starts_with($routeName, 'admin.')) {
            return str_ends_with($routeName, '.index')
                || in_array($routeName, [
                    'admin.dashboard',
                    'admin.insert-project.create',
                    'admin.profil',
                    'admin.aset-ti.cctv.dashboard',
                    'admin.aset-ti.data-center',
                ], true);
        }

        return in_array($routeName, ['dashboard', 'profile.edit'], true);
    }

    private function menuNameFromRoute(string $routeName): string
    {
        return match ($routeName) {
            'admin.dashboard' => 'Dashboard',
            'admin.insert-project.create' => 'Insert Project',
            'admin.daftar-project.index' => 'Daftar Project',
            'admin.profil' => 'Profil',
            'admin.aset-ti.cctv.dashboard' => 'Dashboard CCTV',
            'admin.aset-ti.data-center' => 'Data Center',
            'admin.aset-ti.cctv.index' => 'CCTV',
            'admin.manajemen-vendor.index' => 'Manajemen Vendor',
            'admin.manajemen-divisi.index' => 'Manajemen Divisi',
            'admin.manajemen-user.index' => 'Manajemen User',
            'dashboard' => 'Dashboard',
            'profile.edit' => 'Profile',
            default => Str::title(str_replace(['.', '-'], ' ', $routeName)),
        };
    }
}
