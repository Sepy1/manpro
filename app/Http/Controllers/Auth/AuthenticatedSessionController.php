<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\IndonesianWhatsappPhoneNormalizer;
use App\Support\MahadataWhatsappOtpSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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

        if ($this->isPortalPanelUser($user) && $user->two_factor_enabled) {
            $remember = $request->boolean('remember');

            if (! $this->dispatchAdminTwoFactorCode($user)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $this->logFailedLoginAttempt($request, 'otp_send_failed', $user->email);

                throw ValidationException::withMessages([
                    'email' => 'Gagal mengirim kode 2FA WhatsApp. Pastikan nomor HP sudah valid di Manajemen User, centang 2FA hanya untuk pengguna yang punya WA, serta periksa konfigurasi Mahadata.',
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
                ->with('status', 'Kode verifikasi sudah dikirim melalui WhatsApp.');
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
        if (! $this->isPortalPanelUser($pendingUser)) {
            $this->clearPendingAdminTwoFactor($request);

            return redirect()->route('login');
        }

        if (! $pendingUser->two_factor_enabled) {
            $this->clearPendingAdminTwoFactor($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Akun ini tidak menggunakan verifikasi 2FA. Silakan login ulang.',
            ]);
        }

        return view('auth.admin-2fa', [
            'maskedEmail' => $this->maskEmail($pendingUser->email),
            'maskedPhone' => $this->maskWaRecipient($pendingUser->phone ? (string) $pendingUser->phone : ''),
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
        if (! $this->isPortalPanelUser($pendingUser) || ! $pendingUser->two_factor_enabled || ! is_array($payload) || ! isset($payload['otp_hash'])) {
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
        if (! $this->isPortalPanelUser($pendingUser) || ! $pendingUser->two_factor_enabled) {
            $this->clearPendingAdminTwoFactor($request);

            return redirect()->route('login');
        }

        if (! $this->dispatchAdminTwoFactorCode($pendingUser)) {
            return back()->withErrors([
                'otp' => 'Gagal mengirim ulang OTP WhatsApp.',
            ]);
        }

        return back()->with('status', 'OTP baru telah dikirim melalui WhatsApp.');
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
        if (! $user->two_factor_enabled) {
            return false;
        }

        $endpoint = trim((string) config('services.mahadata_whatsapp.endpoint'));
        $token = trim((string) config('services.mahadata_whatsapp.token'));

        if ($endpoint === '' || $token === '') {
            Log::warning('Mahadata WhatsApp 2FA: endpoint atau token kosong.', [
                'endpoint_set' => $endpoint !== '',
                'token_set' => $token !== '',
            ]);

            return false;
        }

        $rawPhone = trim((string) ($user->phone ?? ''));
        if ($rawPhone === '') {
            Log::warning('Mahadata WhatsApp 2FA: pengguna belum mengisi nomor HP.', ['user_id' => $user->id]);

            return false;
        }

        $waTo = IndonesianWhatsappPhoneNormalizer::toWaDigits62($rawPhone);
        if ($waTo === null) {
            Log::warning('Mahadata WhatsApp 2FA: nomor HP tersimpan tidak valid.', ['user_id' => $user->id]);

            return false;
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put($this->adminTwoFactorCacheKey((int) $user->id), [
            'otp_hash' => Hash::make($otp),
        ], now()->addMinutes(self::ADMIN_2FA_TTL_MINUTES));

        return app(MahadataWhatsappOtpSender::class)->send($waTo, $otp);
    }

    private function isPortalPanelUser(?User $user): bool
    {
        return $user !== null && in_array($user->role, User::ROLES, true);
    }

    private function maskWaRecipient(string $phoneDigits): string
    {
        if ($phoneDigits === '') {
            return '—';
        }

        $d = preg_replace('/\D+/', '', $phoneDigits);
        $len = strlen($d);
        if ($len < 6) {
            return '—';
        }

        $head = substr($d, 0, 4);
        $tail = substr($d, -3);

        return $head.str_repeat('•', max($len - 7, 2)).$tail;
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
