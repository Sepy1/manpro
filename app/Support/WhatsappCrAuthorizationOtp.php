<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

final class WhatsappCrAuthorizationOtp
{
    public const TTL_MINUTES = 5;

    public function dispatchForApproval(User $user, string $interactionToken): bool
    {
        if (! $user->two_factor_enabled) {
            return false;
        }

        $endpoint = trim((string) config('services.mahadata_whatsapp.endpoint'));
        $token = trim((string) config('services.mahadata_whatsapp.token'));
        if ($endpoint === '' || $token === '') {
            Log::warning('Mahadata WhatsApp CR auth 2FA: endpoint atau token kosong.');

            return false;
        }

        $rawPhone = trim((string) ($user->phone ?? ''));
        if ($rawPhone === '') {
            Log::warning('Mahadata WhatsApp CR auth 2FA: pengguna belum mengisi nomor HP.', ['user_id' => $user->id]);

            return false;
        }

        $waTo = IndonesianWhatsappPhoneNormalizer::toWaDigits62($rawPhone);
        if ($waTo === null) {
            Log::warning('Mahadata WhatsApp CR auth 2FA: nomor HP tidak valid.', ['user_id' => $user->id]);

            return false;
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put($this->cacheKey($interactionToken), [
            'otp_hash' => Hash::make($otp),
            'user_id' => (int) $user->id,
        ], now()->addMinutes(self::TTL_MINUTES));

        return app(MahadataWhatsappOtpSender::class)->send($waTo, $otp);
    }

    public function verify(string $interactionToken, string $otpPlain, int $expectedUserId): bool
    {
        $payload = Cache::get($this->cacheKey($interactionToken));
        if (! is_array($payload) || ! isset($payload['otp_hash'], $payload['user_id'])) {
            return false;
        }

        if ((int) $payload['user_id'] !== $expectedUserId) {
            return false;
        }

        $otp = trim($otpPlain);
        if ($otp === '' || ! Hash::check($otp, (string) $payload['otp_hash'])) {
            return false;
        }

        Cache::forget($this->cacheKey($interactionToken));

        return true;
    }

    public function forget(string $interactionToken): void
    {
        Cache::forget($this->cacheKey($interactionToken));
    }

    public function hasPending(string $interactionToken): bool
    {
        return Cache::has($this->cacheKey($interactionToken));
    }

    public function maskPhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '—';
        }

        $digits = preg_replace('/\D+/', '', $phone);
        $len = strlen((string) $digits);
        if ($len < 6) {
            return '—';
        }

        $head = substr((string) $digits, 0, 4);
        $tail = substr((string) $digits, -3);

        return $head.str_repeat('•', max($len - 7, 2)).$tail;
    }

    private function cacheKey(string $interactionToken): string
    {
        return 'cr_auth_2fa:otp:'.strtolower(trim($interactionToken));
    }
}
