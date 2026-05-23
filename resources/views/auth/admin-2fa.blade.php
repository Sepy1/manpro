<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        Kode OTP telah dikirim melalui <span class="font-semibold text-gray-900 dark:text-white">WhatsApp</span>
        ke <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ $maskedPhone }}</span>
        untuk akun <span class="font-semibold">{{ $maskedEmail }}</span>.
        Masukkan kode 6 digit untuk melanjutkan login (±5 menit).
    </div>

    <form method="POST" action="{{ route('admin.2fa.verify') }}">
        @csrf

        <div>
            <x-input-label for="otp" :value="__('Kode OTP')" />
            <x-text-input id="otp" class="mt-1 block w-full" type="text" name="otp" maxlength="6" required autofocus />
            <x-input-error :messages="$errors->get('otp')" class="mt-2" />
        </div>

        <div class="mt-4 flex items-center justify-end">
            <x-primary-button>
                {{ __('Verifikasi') }}
            </x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.2fa.resend') }}" class="mt-3">
        @csrf
        <button type="submit"
            class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
            Kirim ulang OTP
        </button>
    </form>
</x-guest-layout>
