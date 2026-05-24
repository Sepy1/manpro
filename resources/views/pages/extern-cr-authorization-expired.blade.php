@extends('layouts.extern-cr-authorization')

@section('title', 'Otorisasi Kedaluwarsa' . ($cr ? ' — '.$cr->nomor : ''))

@section('card')
    @php
        $reason = $expiredReason ?? null;
        $isDecided = $reason === 'decided';
    @endphp
    <div class="card-header card-header--warning">
        <div class="header-row">
            <div class="header-main">
                <div class="header-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                </div>
                <div class="header-text">
                    <h1>Otorisasi Kedaluwarsa</h1>
                    <p>
                        @if ($isDecided)
                            Keputusan otorisasi untuk CR ini sudah tercatat
                        @else
                            Tautan ini hanya berlaku {{ \App\Support\WhatsappCrAuthorizationExpiry::ttlLabel() }} setelah pesan WhatsApp dikirim
                        @endif
                    </p>
                </div>
            </div>
            <span class="badge">Expired</span>
        </div>
    </div>

    <div class="card-body">
        <div class="status-icon status-icon--warning" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 6v6l4 2"/>
            </svg>
        </div>

        <h2 class="status-title">
            @if ($isDecided)
                Tautan otorisasi sudah tidak berlaku
            @else
                Waktu otorisasi habis
            @endif
        </h2>
        <p class="status-text">
            @if ($isDecided)
                Change Request ini sudah disetujui atau ditolak. Halaman otorisasi tidak dapat digunakan lagi.
            @else
                Halaman otorisasi Change Request sudah tidak dapat digunakan.
                Silakan minta pengiriman ulang notifikasi WhatsApp terbaru dari admin Manpro.
            @endif
        </p>

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
                @if ($isDecided && $cr->wa_authorization_decision)
                    <div class="meta-row">
                        <div class="field-label">Keputusan</div>
                        <div class="field-value">
                            @if ($cr->wa_authorization_decision === \App\Models\ExternCr::WA_AUTH_APPROVED)
                                Disetujui
                            @elseif ($cr->wa_authorization_decision === \App\Models\ExternCr::WA_AUTH_REJECTED)
                                Ditolak
                            @else
                                {{ $cr->wa_authorization_decision }}
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="info-note info-note--warning">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v4M12 16h.01"/>
            </svg>
            <span>
                @if ($isDecided)
                    Setelah disetujui atau ditolak, tautan otorisasi langsung nonaktif.
                @else
                    Batas waktu otorisasi: {{ \App\Support\WhatsappCrAuthorizationExpiry::ttlLabel() }} sejak notifikasi WhatsApp dikirim.
                @endif
            </span>
        </div>

        <p class="footnote">Anda dapat menutup halaman ini.</p>
    </div>
@endsection
