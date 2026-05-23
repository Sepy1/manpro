<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->two_factor_enabled
            && in_array($user->role, User::ROLES, true)
            && ! $request->session()->get('admin_2fa.verified', false)
        ) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun ini memerlukan verifikasi 2FA. Silakan login kembali untuk menerima OTP WhatsApp.',
            ]);
        }

        return $next($request);
    }
}
