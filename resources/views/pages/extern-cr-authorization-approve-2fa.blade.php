@extends('layouts.extern-cr-authorization')

@section('title', 'Verifikasi 2FA — Otorisasi CR' . ($cr ? ' '.$cr->nomor : ''))

@section('card')
    <div class="card-header card-header--primary">
        <div class="header-row">
            <div class="header-main">
                <div class="header-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <div class="header-text">
                    <h1>Verifikasi 2FA</h1>
                    <p>Masukkan kode OTP WhatsApp untuk menyetujui CR</p>
                </div>
            </div>
            <span class="badge">Setujui</span>
        </div>
    </div>

    <div class="card-body">
        @if ($cr)
            <div class="meta-box">
                <div class="meta-row">
                    <div class="field-label">Nomor CR</div>
                    <div class="field-value">{{ $cr->nomor }}</div>
                </div>
                @if ($cr->nama)
                    <div class="meta-row">
                        <div class="field-label">Nama Change Request</div>
                        <div class="field-value">{{ $cr->nama }}</div>
                    </div>
                @endif
            </div>
        @endif

        @if ($otpUnavailable ?? false)
            <div class="info-note info-note--danger">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v4M12 16h.01"/>
                </svg>
                <span>
                    OTP WhatsApp tidak dapat dikirim.
                    Pastikan akun otorisator memiliki 2FA aktif, nomor HP valid, dan konfigurasi Mahadata berjalan.
                </span>
            </div>
        @else
            <p class="status-text" style="margin-top: 0.85rem; text-align: left;">
                Kode OTP 6 digit telah dikirim ke WhatsApp
                <strong>{{ $maskedPhone }}</strong>.
                Berlaku ±{{ \App\Support\WhatsappCrAuthorizationOtp::TTL_MINUTES }} menit.
            </p>

            @if (session('status'))
                <p class="form-status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ $verifyUrl }}" class="form-group">
                @csrf
                <label class="form-label" for="otp">Kode OTP</label>
                <input
                    id="otp"
                    class="form-input"
                    type="text"
                    name="otp"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    autocomplete="one-time-code"
                    required
                    autofocus
                >
                @error('otp')
                    <p class="form-error">{{ $message }}</p>
                @enderror

                <div class="actions">
                    <button type="submit" class="btn-bar btn-success">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                        Konfirmasi Setujui
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ $resendUrl }}">
                @csrf
                <button type="submit" class="btn-bar btn-link">Kirim ulang OTP</button>
            </form>
        @endif

        <div class="actions">
            <a href="{{ $backUrl }}" class="btn-bar btn-primary">Kembali ke halaman otorisasi</a>
        </div>
    </div>
@endsection
