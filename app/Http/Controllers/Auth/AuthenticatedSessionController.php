<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    private const ADMIN_2FA_PENDING_USER_ID = 'admin_2fa.pending_user_id';

    private const ADMIN_2FA_PENDING_REMEMBER = 'admin_2fa.pending_remember';

    private const ADMIN_2FA_VERIFIED = 'admin_2fa.verified';

    private const ADMIN_2FA_TTL_MINUTES = 5;

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $exception) {
            $this->logFailedLoginAttempt($request, 'invalid_credentials');
            throw $exception;
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user?->role === 'admin') {
            $remember = $request->boolean('remember');

            if (! $this->dispatchAdminTwoFactorCode($user)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $this->logFailedLoginAttempt($request, 'otp_send_failed', $user->email);

                throw ValidationException::withMessages([
                    'email' => 'Gagal mengirim kode 2FA Telegram. Periksa konfigurasi bot dan chat ID.',
                ]);
            }

            Auth::guard('web')->logout();

            $request->session()->put([
                self::ADMIN_2FA_PENDING_USER_ID => $user->id,
                self::ADMIN_2FA_PENDING_REMEMBER => $remember,
            ]);
            $request->session()->forget(self::ADMIN_2FA_VERIFIED);
            $request->session()->regenerateToken();

            return redirect()->route('admin.2fa.challenge')
                ->with('status', 'Kode verifikasi sudah dikirim ke Telegram admin.');
        }

        $request->session()->put(self::ADMIN_2FA_VERIFIED, true);
        if ($user) {
            $this->logUserLogin($user, $request);
        }

        if (in_array($user?->role, ['admin', 'manager', 'officer', 'vendor', 'cabang'], true)) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function showAdminTwoFactorChallenge(Request $request): RedirectResponse|View
    {
        $pendingUserId = (int) $request->session()->get(self::ADMIN_2FA_PENDING_USER_ID, 0);
        if ($pendingUserId <= 0) {
            return redirect()->route('login');
        }

        $pendingUser = User::query()->find($pendingUserId);
        if (! $pendingUser || $pendingUser->role !== 'admin') {
            $this->clearPendingAdminTwoFactor($request);

            return redirect()->route('login');
        }

        return view('auth.admin-2fa', [
            'maskedEmail' => $this->maskEmail($pendingUser->email),
        ]);
    }

    public function verifyAdminTwoFactor(Request $request): RedirectResponse
    {
        $pendingUserId = (int) $request->session()->get(self::ADMIN_2FA_PENDING_USER_ID, 0);
        if ($pendingUserId <= 0) {
            return redirect()->route('login');
        }

        $throttleKey = sprintf('admin-2fa:%d|%s', $pendingUserId, $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $pendingEmail = (string) (User::query()->whereKey($pendingUserId)->value('email') ?? '');
            $this->logFailedLoginAttempt($request, 'otp_rate_limited', $pendingEmail);
            throw ValidationException::withMessages([
                'otp' => "Terlalu banyak percobaan OTP. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $payload = Cache::get($this->adminTwoFactorCacheKey($pendingUserId));
        $pendingUser = User::query()->find($pendingUserId);
        if (! $pendingUser || $pendingUser->role !== 'admin' || ! is_array($payload) || ! isset($payload['otp_hash'])) {
            $this->clearPendingAdminTwoFactor($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Sesi verifikasi 2FA tidak valid. Silakan login ulang.',
            ]);
        }

        if (! Hash::check((string) $validated['otp'], (string) $payload['otp_hash'])) {
            RateLimiter::hit($throttleKey, 60);
            $this->logFailedLoginAttempt($request, 'otp_invalid', (string) ($pendingUser->email ?? ''));
            throw ValidationException::withMessages([
                'otp' => 'Kode OTP tidak valid.',
            ]);
        }

        $remember = (bool) $request->session()->get(self::ADMIN_2FA_PENDING_REMEMBER, false);

        Auth::login($pendingUser, $remember);
        $request->session()->regenerate();
        $request->session()->put(self::ADMIN_2FA_VERIFIED, true);
        $request->session()->forget([
            self::ADMIN_2FA_PENDING_USER_ID,
            self::ADMIN_2FA_PENDING_REMEMBER,
        ]);

        Cache::forget($this->adminTwoFactorCacheKey($pendingUserId));
        RateLimiter::clear($throttleKey);
        $this->logUserLogin($pendingUser, $request);

        return redirect()->route('admin.dashboard');
    }

    public function resendAdminTwoFactor(Request $request): RedirectResponse
    {
        $pendingUserId = (int) $request->session()->get(self::ADMIN_2FA_PENDING_USER_ID, 0);
        if ($pendingUserId <= 0) {
            return redirect()->route('login');
        }

        $pendingUser = User::query()->find($pendingUserId);
        if (! $pendingUser || $pendingUser->role !== 'admin') {
            $this->clearPendingAdminTwoFactor($request);

            return redirect()->route('login');
        }

        if (! $this->dispatchAdminTwoFactorCode($pendingUser)) {
            return back()->withErrors([
                'otp' => 'Gagal mengirim ulang OTP ke Telegram.',
            ]);
        }

        return back()->with('status', 'OTP baru telah dikirim ke Telegram.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function dispatchAdminTwoFactorCode(User $user): bool
    {
        $botToken = (string) config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.chat_id');
        if ($botToken === '' || $chatId === '') {
            Log::warning('Telegram 2FA config missing');

            return false;
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put($this->adminTwoFactorCacheKey((int) $user->id), [
            'otp_hash' => Hash::make($otp),
        ], now()->addMinutes(self::ADMIN_2FA_TTL_MINUTES));

        $message = implode("\n", [
            'MANPRO Admin 2FA',
            'Kode OTP: '.$otp,
            'Berlaku: '.self::ADMIN_2FA_TTL_MINUTES.' menit',
            'User: '.$user->email,
        ]);

        $response = Http::asForm()
            ->timeout(10)
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);

        if (! $response->successful() || ! data_get($response->json(), 'ok', false)) {
            Log::warning('Telegram 2FA send failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        return true;
    }

    private function adminTwoFactorCacheKey(int $userId): string
    {
        return "admin_2fa:otp:{$userId}";
    }

    private function clearPendingAdminTwoFactor(Request $request): void
    {
        $pendingUserId = (int) $request->session()->get(self::ADMIN_2FA_PENDING_USER_ID, 0);
        if ($pendingUserId > 0) {
            Cache::forget($this->adminTwoFactorCacheKey($pendingUserId));
        }

        $request->session()->forget([
            self::ADMIN_2FA_PENDING_USER_ID,
            self::ADMIN_2FA_PENDING_REMEMBER,
            self::ADMIN_2FA_VERIFIED,
        ]);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $local = $parts[0];
        if (strlen($local) <= 2) {
            return str_repeat('*', strlen($local)).'@'.$parts[1];
        }

        return substr($local, 0, 2).str_repeat('*', max(strlen($local) - 2, 1)).'@'.$parts[1];
    }

    private function logUserLogin(User $user, Request $request): void
    {
        try {
            UserActivityLog::query()->create([
                'user_id' => $user->id,
                'activity_type' => UserActivityLog::TYPE_LOGIN,
                'route_name' => (string) ($request->route()?->getName() ?? 'login'),
                'menu_name' => null,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to write user login activity log', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function logFailedLoginAttempt(Request $request, string $reason, ?string $emailOverride = null): void
    {
        $attemptedEmail = strtolower(trim((string) ($emailOverride ?? $request->input('email', ''))));

        try {
            $user = $attemptedEmail !== ''
                ? User::query()->where('email', $attemptedEmail)->first()
                : null;

            UserActivityLog::query()->create([
                'user_id' => $user?->id,
                'activity_type' => UserActivityLog::TYPE_LOGIN_FAILED,
                'attempted_email' => $attemptedEmail !== '' ? $attemptedEmail : null,
                'failure_reason' => $reason,
                'route_name' => (string) ($request->route()?->getName() ?? 'login'),
                'menu_name' => null,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to write failed login activity log', [
                'attempted_email' => $attemptedEmail,
                'reason' => $reason,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
