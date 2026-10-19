<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictVendorNetworkAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'vendor_network' && ! $request->routeIs('admin.map.*')) {
            abort(403, 'Akun ini hanya memiliki akses ke monitoring MAP.');
        }

        return $next($request);
    }
}
