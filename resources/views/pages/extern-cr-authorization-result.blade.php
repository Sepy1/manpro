@extends('layouts.extern-cr-authorization')

@php
    $cr = $outcome['extern_cr'] ?? null;
    $result = $outcome['result'] ?? '';
    $existing = $outcome['existing_decision'] ?? null;

    $headerVariant = 'neutral';
    $badge = 'Info';
    $headerTitle = 'Otorisasi Change Request';
    $headerSubtitle = 'Status keputusan otorisasi';
    $statusIconClass = 'status-icon--neutral';
    $statusTitle = '';
    $statusText = '';
    $headerIconStroke = '#64748b';

    if ($result === \App\Support\WhatsappCrAuthorizationApplier::RESULT_DISPATCH_NOT_FOUND) {
        $headerVariant = 'neutral';
        $badge = 'Invalid';
        $headerTitle = 'Tautan Tidak Valid';
        $headerSubtitle = 'Otorisasi tidak dapat diproses';
        $statusTitle = 'Tautan tidak ditemukan';
        $statusText = 'Tautan otorisasi tidak ditemukan. Buka kembali pesan WhatsApp terbaru untuk CR ini.';
    } elseif ($result === \App\Support\WhatsappCrAuthorizationApplier::RESULT_USER_UNAUTHORIZED) {
        $headerVariant = 'neutral';
        $badge = 'Ditolak';
        $headerTitle = 'Akses Ditolak';
        $headerSubtitle = 'Akun tidak berhak otorisasi';
        $statusTitle = 'Akses ditolak';
        $statusText = 'Akun otorisator tidak lagi berhak memberi keputusan untuk CR eksternal.';
    } elseif ($cr && $result === \App\Support\WhatsappCrAuthorizationApplier::RESULT_APPLIED) {
        if ($requestedDecision === \App\Models\ExternCr::WA_AUTH_APPROVED) {
            $headerVariant = 'success';
            $badge = 'Disetujui';
            $headerTitle = 'CR Disetujui';
            $headerSubtitle = 'Keputusan berhasil tercatat';
            $statusIconClass = 'status-icon--success';
            $headerIconStroke = '#16a34a';
            $statusTitle = 'Change Request disetujui';
            $statusText = 'Keputusan setuju untuk CR '.$cr->nomor.' telah tercatat di sistem Manpro.';
        } else {
            $headerVariant = 'danger';
            $badge = 'Ditolak';
            $headerTitle = 'CR Ditolak';
            $headerSubtitle = 'Keputusan berhasil tercatat';
            $statusIconClass = 'status-icon--danger';
            $headerIconStroke = '#dc2626';
            $statusTitle = 'Change Request ditolak';
            $statusText = 'Keputusan tolak untuk CR '.$cr->nomor.' telah tercatat di sistem Manpro.';
        }
    } elseif ($cr) {
        if ($existing === \App\Models\ExternCr::WA_AUTH_APPROVED) {
            $headerVariant = 'success';
            $badge = 'Disetujui';
            $headerTitle = 'Sudah Disetujui';
            $headerSubtitle = 'Keputusan pertama tetap berlaku';
            $statusIconClass = 'status-icon--success';
            $headerIconStroke = '#16a34a';
            $statusTitle = 'Sudah disetujui sebelumnya';
            $statusText = 'CR '.$cr->nomor.' sudah pernah disetujui. Keputusan pertama tetap berlaku.';
        } elseif ($existing === \App\Models\ExternCr::WA_AUTH_REJECTED) {
            $headerVariant = 'danger';
            $badge = 'Ditolak';
            $headerTitle = 'Sudah Ditolak';
            $headerSubtitle = 'Keputusan pertama tetap berlaku';
            $statusIconClass = 'status-icon--danger';
            $headerIconStroke = '#dc2626';
            $statusTitle = 'Sudah ditolak sebelumnya';
            $statusText = 'CR '.$cr->nomor.' sudah pernah ditolak. Keputusan pertama tetap berlaku.';
        } else {
            $headerVariant = 'warning';
            $badge = 'Info';
            $headerTitle = 'Keputusan Sudah Ada';
            $headerSubtitle = 'Tidak ada perubahan';
            $statusIconClass = 'status-icon--warning';
            $headerIconStroke = '#d97706';
            $statusTitle = 'Keputusan sudah ada';
            $statusText = 'CR '.$cr->nomor.' sudah memiliki keputusan otorisasi.';
        }
    }
@endphp

@section('title', $headerTitle . ($cr ? ' — '.$cr->nomor : ''))

@section('card')
    <div class="card-header card-header--{{ $headerVariant }}">
        <div class="header-row">
            <div class="header-main">
                <div class="header-icon" aria-hidden="true">
                    @if ($headerVariant === 'success')
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="{{ $headerIconStroke }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                    @elseif ($headerVariant === 'danger')
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="{{ $headerIconStroke }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 6l12 12M18 6L6 18"/>
                        </svg>
                    @else
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="{{ $headerIconStroke }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8v4M12 16h.01"/>
                        </svg>
                    @endif
                </div>
                <div class="header-text">
                    <h1>{{ $headerTitle }}</h1>
                    <p>{{ $headerSubtitle }}</p>
                </div>
            </div>
            <span class="badge">{{ $badge }}</span>
        </div>
    </div>

    <div class="card-body">
        @if ($statusTitle !== '')
            <div class="status-icon {{ $statusIconClass }}" aria-hidden="true">
                @if ($headerVariant === 'success')
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                @elseif ($headerVariant === 'danger')
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                @else
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                @endif
            </div>

            <h2 class="status-title">{{ $statusTitle }}</h2>
            <p class="status-text">{{ $statusText }}</p>
        @endif

        @if ($cr)
            <div class="meta-box">
                <div class="meta-row">
                    <div class="field-label">Nomor CR</div>
                    <div class="field-value">{{ $cr->nomor }}</div>
                </div>
                <div class="meta-row">
                    <div class="field-label">Nama Change Request</div>
                    <div class="field-value">{{ $cr->nama ?? '—' }}</div>
                </div>
                <div class="meta-row">
                    <div class="field-label">Penanggung jawab keputusan</div>
                    <div class="field-value">{{ $cr->authorizationResponder?->name ?? '—' }}</div>
                </div>
                <div class="meta-row">
                    <div class="field-label">Waktu keputusan</div>
                    <div class="field-value">{{ optional($cr->wa_authorization_at)->format('d/m/Y H:i') ?? '—' }}</div>
                </div>
                @if ($requestedDecision === \App\Models\ExternCr::WA_AUTH_REJECTED && filled($cr->wa_authorization_reject_reason))
                    <div class="meta-row">
                        <div class="field-label">Alasan penolakan</div>
                        <div class="field-value" style="font-weight: 500; white-space: pre-wrap;">{{ $cr->wa_authorization_reject_reason }}</div>
                    </div>
                @endif
            </div>
        @endif

        @if ($result === \App\Support\WhatsappCrAuthorizationApplier::RESULT_APPLIED)
            <div class="info-note">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v4M12 16h.01"/>
                </svg>
                <span>Notifikasi konfirmasi akan dikirim ke WhatsApp otorisator.</span>
            </div>
        @endif

        <p class="footnote">Anda dapat menutup halaman ini.</p>
    </div>
@endsection
