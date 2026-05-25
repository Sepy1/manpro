<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Otorisasi Change Request — {{ $cr->nomor }}</title>
    <style>
        :root {
            --font: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #16a34a;
            --success-dark: #15803d;
            --danger: #dc2626;
            --danger-dark: #b91c1c;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        html {
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        body {
            font-family: var(--font);
            margin: 0;
            min-height: 100dvh;
            color: var(--text);
            background: #eef2f7;
            padding:
                calc(0.75rem + var(--safe-top))
                0.75rem
                calc(0.75rem + var(--safe-bottom));
        }

        .wrap {
            max-width: 26rem;
            margin: 0 auto;
        }

        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        /* ── Header ── */
        .card-header {
            position: relative;
            padding: 1.1rem 1rem 1.15rem;
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
            color: #fff;
            overflow: hidden;
        }

        .card-header::after {
            content: "";
            position: absolute;
            right: -1.5rem;
            bottom: -2rem;
            width: 9rem;
            height: 9rem;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.07);
            pointer-events: none;
        }

        .card-header::before {
            content: "";
            position: absolute;
            right: 2rem;
            bottom: -3.5rem;
            width: 7rem;
            height: 7rem;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            pointer-events: none;
        }

        .header-row {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.65rem;
        }

        .header-main {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            min-width: 0;
        }

        .header-icon {
            flex-shrink: 0;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border-radius: 0.55rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
        }

        .header-icon svg { display: block; }

        .header-text { min-width: 0; }

        .header-text h1 {
            margin: 0 0 0.2rem;
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .header-text p {
            margin: 0;
            font-size: 0.78rem;
            line-height: 1.35;
            opacity: 0.92;
        }

        .badge {
            flex-shrink: 0;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            margin-top: 0.15rem;
        }

        /* ── Info rows ── */
        .info-list {
            padding: 0 1rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 0;
            border-bottom: 1px solid var(--border);
        }

        .info-row:last-child { border-bottom: none; }

        .info-icon {
            flex-shrink: 0;
            width: 2.25rem;
            height: 2.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
        }

        .info-icon--blue { background: #eff6ff; color: #2563eb; }
        .info-icon--purple { background: #f5f3ff; color: #7c3aed; }
        .info-icon--green { background: #ecfdf5; color: #059669; }

        .info-icon svg { display: block; }

        .info-content { min-width: 0; flex: 1; }

        .field-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.15rem;
        }

        .field-value {
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        /* ── Description ── */
        .description-section {
            padding: 0.85rem 1rem 0;
        }

        .description-box {
            margin-top: 0.45rem;
            padding: 0.75rem 0.85rem;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 0.65rem;
        }

        .desc-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .desc-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            font-size: 0.84rem;
            line-height: 1.45;
            color: #334155;
        }

        .desc-list li + li { margin-top: 0.45rem; }

        .desc-num {
            flex-shrink: 0;
            width: 1.35rem;
            height: 1.35rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            border-radius: 999px;
            margin-top: 0.05rem;
        }

        .desc-text {
            flex: 1;
            min-width: 0;
            word-break: break-word;
        }

        .desc-plain {
            margin: 0;
            font-size: 0.84rem;
            line-height: 1.45;
            color: #334155;
            white-space: pre-line;
            word-break: break-word;
        }

        /* ── Actions ── */
        .actions {
            padding: 0.85rem 1rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }

        .btn-pdf {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.75rem 0.9rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 0.65rem;
            font-family: var(--font);
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            touch-action: manipulation;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
        }

        .btn-pdf:active { background: var(--primary-dark); transform: scale(0.99); }
        .btn-pdf:disabled { opacity: 0.65; cursor: wait; }

        .btn-pdf-left {
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }

        .btn-pdf svg { display: block; flex-shrink: 0; }

        .decision-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.55rem;
        }

        .btn-decision {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            width: 100%;
            padding: 0.75rem 0.9rem;
            border: none;
            border-radius: 0.65rem;
            font-family: var(--font);
            font-size: 0.88rem;
            font-weight: 600;
            line-height: 1.1;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
            touch-action: manipulation;
        }

        .btn-decision:active { transform: scale(0.99); opacity: 0.95; }

        .btn-approve {
            background: var(--success);
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.25);
        }

        .btn-approve:active { background: var(--success-dark); }

        .btn-reject {
            background: var(--danger);
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        }

        .btn-reject:active { background: var(--danger-dark); }

        .btn-decision svg { display: block; flex-shrink: 0; }

        /* ── Footer note ── */
        .info-note {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin: 0 1rem 1rem;
            padding: 0.65rem 0.75rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 0.55rem;
            font-size: 0.75rem;
            line-height: 1.35;
            color: #1e40af;
        }

        .info-note svg {
            flex-shrink: 0;
            margin-top: 0.05rem;
        }

        .info-note--muted {
            background: #f8fafc;
            border-color: var(--border);
            color: var(--muted);
        }

        /* ── Reject modal ── */
        .reject-overlay {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.45);
        }

        .reject-overlay.is-visible { display: flex; }

        .reject-dialog {
            width: min(100%, 22rem);
            background: #fff;
            border-radius: 0.85rem;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }

        .reject-dialog-header {
            padding: 0.85rem 1rem;
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
            color: #fff;
        }

        .reject-dialog-header h2 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .reject-dialog-header p {
            margin: 0.25rem 0 0;
            font-size: 0.75rem;
            opacity: 0.92;
        }

        .reject-dialog-body { padding: 0.85rem 1rem 1rem; }

        .reject-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.35rem;
        }

        .reject-textarea {
            width: 100%;
            min-height: 5.5rem;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.55rem;
            font-family: var(--font);
            font-size: 0.84rem;
            line-height: 1.4;
            resize: vertical;
        }

        .reject-textarea:focus {
            outline: none;
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
        }

        .reject-hint {
            margin: 0.35rem 0 0;
            font-size: 0.72rem;
            color: var(--muted);
        }

        .reject-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 0.85rem;
        }

        .reject-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 2.35rem;
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 0.55rem;
            font-family: var(--font);
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
        }

        .reject-btn-cancel {
            background: #f1f5f9;
            color: #334155;
        }

        .reject-btn-submit {
            background: var(--danger);
            color: #fff;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        }

        .reject-btn-submit:disabled { opacity: 0.55; cursor: wait; }

        /* ── PDF viewer overlay ── */
        .pdf-overlay {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: none;
            flex-direction: column;
            background: #0f172a;
        }

        .pdf-overlay.is-visible { display: flex; }

        .pdf-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            padding:
                calc(0.55rem + env(safe-area-inset-top, 0px))
                0.75rem
                0.55rem;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #fff;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.25);
        }

        .pdf-toolbar-title {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 700;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .pdf-toolbar-actions {
            display: flex;
            flex-shrink: 0;
            gap: 0.4rem;
        }

        .pdf-toolbar-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.65rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.28);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-family: inherit;
            font-size: 0.72rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .pdf-toolbar-btn:active { opacity: 0.85; }

        .pdf-viewer-wrap {
            position: relative;
            flex: 1;
            min-height: 0;
            background: #334155;
        }

        .pdf-frame {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            background: #fff;
        }

        .pdf-loading {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.72);
            color: #fff;
        }

        .pdf-loading.is-hidden { display: none; }

        .pdf-loading-title {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 700;
        }

        .progress-track {
            width: min(100%, 16rem);
            height: 0.35rem;
            background: rgba(255, 255, 255, 0.22);
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            background: #fff;
            transition: width 0.2s ease;
        }

        .progress-label {
            font-size: 0.72rem;
            font-weight: 600;
            opacity: 0.9;
        }

        @media (min-width: 640px) {
            body { padding: 1.25rem; }
            .wrap { max-width: 28rem; }
        }
    </style>
</head>
<body>
    @php
        $deskripsi = trim((string) ($cr->deskripsi_permintaan ?? ''));
        if ($deskripsi === '') {
            $deskripsi = trim((string) ($cr->perubahan_diharapkan ?? ''));
        }

        $descLines = [];
        if ($deskripsi !== '') {
            foreach (preg_split('/\R/u', $deskripsi) as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                $line = preg_replace('/^\d+[\.\)]\s*/u', '', $line);
                $descLines[] = $line;
            }
        }

        $hasDecision = ($cr->wa_authorization_decision ?? null) === null;
    @endphp

    <div class="wrap">
        <div class="card">
            <div class="card-header">
                <div class="header-row">
                    <div class="header-main">
                        <div class="header-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                                <rect x="9" y="3" width="6" height="4" rx="1"/>
                                <path d="M9 12h6M9 16h4"/>
                            </svg>
                        </div>
                        <div class="header-text">
                            <h1>Otorisasi Change Request</h1>
                            <p>Mohon review dan berikan keputusan Anda</p>
                        </div>
                    </div>
                    <span class="badge">Otorisasi</span>
                </div>
            </div>

            <div class="info-list">
                <div class="info-row">
                    <div class="info-icon info-icon--blue" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="16" rx="2"/>
                            <path d="M7 8h4M7 12h6M7 16h3"/>
                        </svg>
                    </div>
                    <div class="info-content">
                        <div class="field-label">Nomor CR</div>
                        <div class="field-value">{{ $cr->nomor }}</div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon info-icon--blue" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        </svg>
                    </div>
                    <div class="info-content">
                        <div class="field-label">Nama Change Request</div>
                        <div class="field-value">{{ $cr->nama ?? '—' }}</div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon info-icon--purple" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 20c0-3.3 3.6-6 8-6s8 2.7 8 6"/>
                        </svg>
                    </div>
                    <div class="info-content">
                        <div class="field-label">Pembuat</div>
                        <div class="field-value">{{ $cr->creator?->name ?? '—' }}</div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon info-icon--green" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="3" width="16" height="18" rx="1"/>
                            <path d="M9 21V9h6v12M9 12h6M9 15h6"/>
                        </svg>
                    </div>
                    <div class="info-content">
                        <div class="field-label">Aplikasi</div>
                        <div class="field-value">{{ $cr->application?->name ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="description-section">
                <div class="field-label">Deskripsi perubahan</div>
                <div class="description-box">
                    @if (count($descLines) > 0)
                        <ol class="desc-list">
                            @foreach ($descLines as $i => $line)
                                <li>
                                    <span class="desc-num">{{ $i + 1 }}</span>
                                    <span class="desc-text">{{ $line }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="desc-plain">—</p>
                    @endif
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn-pdf js-download-pdf" data-pdf-url="{{ $pdfUrl }}">
                    <span class="btn-pdf-left">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/>
                        </svg>
                        Lihat Dokumen PDF
                    </span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 6l6 6-6 6"/>
                    </svg>
                </button>

                @if ($hasDecision)
                    <div class="decision-row">
                        <a class="btn-decision btn-approve" href="{{ $approveUrl }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M5 13l4 4L19 7"/>
                            </svg>
                            Setujui
                        </a>
                        <button type="button" class="btn-decision btn-reject" id="open-reject-modal">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 6l12 12M18 6L6 18"/>
                            </svg>
                            Tolak
                        </button>
                    </div>
                @endif
            </div>

            @if ($hasDecision)
                <div class="info-note">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                    <span>Keputusan pertama akan menjadi keputusan resmi. Tautan berlaku {{ \App\Support\WhatsappCrAuthorizationExpiry::ttlLabel() }}.</span>
                </div>
            @else
                <div class="info-note info-note--muted">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4M12 16h.01"/>
                    </svg>
                    <span>Sudah ada keputusan: {{ $cr->wa_authorization_decision }}</span>
                </div>
            @endif
        </div>
    </div>

    @if ($hasDecision)
        <div class="reject-overlay" id="reject-overlay" aria-hidden="true">
            <div class="reject-dialog" role="dialog" aria-modal="true" aria-labelledby="reject-dialog-title">
                <div class="reject-dialog-header">
                    <h2 id="reject-dialog-title">Tolak Change Request</h2>
                    <p>Berikan alasan penolakan sebelum mengirim keputusan.</p>
                </div>
                <form method="POST" action="{{ $rejectUrl }}" class="reject-dialog-body" id="reject-form">
                    @csrf
                    <label class="reject-label" for="reject-reason">Alasan penolakan</label>
                    <textarea
                        id="reject-reason"
                        name="reject_reason"
                        class="reject-textarea"
                        required
                        minlength="10"
                        maxlength="2000"
                        placeholder="Jelaskan alasan penolakan CR ini…"
                    >{{ old('reject_reason') }}</textarea>
                    @error('reject_reason')
                        <p class="reject-hint" style="color: var(--danger);">{{ $message }}</p>
                    @enderror
                    <p class="reject-hint">Minimal 10 karakter. Alasan akan tercatat di riwayat CR.</p>
                    <div class="reject-actions">
                        <button type="button" class="reject-btn reject-btn-cancel" id="close-reject-modal">Batal</button>
                        <button type="submit" class="reject-btn reject-btn-submit" id="submit-reject">Kirim penolakan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="pdf-overlay" id="pdf-overlay" aria-hidden="true">
        <div class="pdf-toolbar">
            <p class="pdf-toolbar-title">Dokumen CR — {{ $cr->nomor }}</p>
            <div class="pdf-toolbar-actions">
                <a class="pdf-toolbar-btn" id="pdf-open-tab" href="{{ $pdfUrl }}" target="_blank" rel="noopener">Tab baru</a>
                <button type="button" class="pdf-toolbar-btn" id="pdf-close">Tutup</button>
            </div>
        </div>
        <div class="pdf-viewer-wrap">
            <div class="pdf-loading" id="pdf-loading">
                <p class="pdf-loading-title">Memuat PDF…</p>
                <div class="progress-track">
                    <div class="progress-bar" id="pdf-progress-bar"></div>
                </div>
                <div class="progress-label" id="pdf-progress-label">0%</div>
            </div>
            <iframe
                class="pdf-frame"
                id="pdf-frame"
                title="Dokumen PDF Change Request"
                src="about:blank"
            ></iframe>
        </div>
    </div>

    <script>
        (function () {
            var overlay = document.getElementById('pdf-overlay');
            var frame = document.getElementById('pdf-frame');
            var loading = document.getElementById('pdf-loading');
            var bar = document.getElementById('pdf-progress-bar');
            var label = document.getElementById('pdf-progress-label');
            var closeBtn = document.getElementById('pdf-close');
            var openTab = document.getElementById('pdf-open-tab');
            var busy = false;
            var timer = null;
            var pdfLoadTimeout = null;
            var activePdfUrl = '';

            function setProgress(value) {
                var pct = Math.max(0, Math.min(100, Math.round(value)));
                bar.style.width = pct + '%';
                label.textContent = pct + '%';
            }

            function showOverlay() {
                overlay.classList.add('is-visible');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function hideOverlay() {
                overlay.classList.remove('is-visible');
                overlay.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                loading.classList.remove('is-hidden');
                var title = loading.querySelector('.pdf-loading-title');
                if (title) {
                    title.textContent = 'Memuat PDF…';
                }
                setProgress(0);
                frame.src = 'about:blank';
                activePdfUrl = '';
                clearInterval(timer);
                if (pdfLoadTimeout) {
                    clearTimeout(pdfLoadTimeout);
                    pdfLoadTimeout = null;
                }
            }

            function animateTo(target, ms) {
                var start = parseFloat(bar.style.width) || 0;
                var begin = performance.now();
                clearInterval(timer);
                timer = setInterval(function () {
                    var ratio = Math.min(1, (performance.now() - begin) / ms);
                    setProgress(start + (target - start) * ratio);
                    if (ratio >= 1) {
                        clearInterval(timer);
                    }
                }, 40);
            }

            function openPdfViewer(pdfUrl) {
                if (busy || !pdfUrl) {
                    return;
                }

                busy = true;
                activePdfUrl = pdfUrl;
                if (openTab) {
                    openTab.href = pdfUrl;
                }

                document.querySelectorAll('.js-download-pdf').forEach(function (b) { b.disabled = true; });
                showOverlay();
                setProgress(8);
                animateTo(88, 900);

                if (pdfLoadTimeout) {
                    clearTimeout(pdfLoadTimeout);
                }

                frame.onload = function () {
                    if (pdfLoadTimeout) {
                        clearTimeout(pdfLoadTimeout);
                        pdfLoadTimeout = null;
                    }
                    setProgress(100);
                    loading.classList.add('is-hidden');
                    document.querySelectorAll('.js-download-pdf').forEach(function (b) { b.disabled = false; });
                    busy = false;
                };

                frame.onerror = function () {
                    if (pdfLoadTimeout) {
                        clearTimeout(pdfLoadTimeout);
                        pdfLoadTimeout = null;
                    }
                    hideOverlay();
                    document.querySelectorAll('.js-download-pdf').forEach(function (b) { b.disabled = false; });
                    busy = false;
                    window.open(pdfUrl, '_blank', 'noopener');
                };

                pdfLoadTimeout = setTimeout(function () {
                    if (!overlay.classList.contains('is-visible') || loading.classList.contains('is-hidden')) {
                        return;
                    }
                    setProgress(100);
                    var title = loading.querySelector('.pdf-loading-title');
                    if (title) {
                        title.textContent = 'Pratinjau mungkin tidak tampil di perangkat ini';
                    }
                    label.textContent = 'Ketuk Tab baru untuk membuka PDF';
                }, 12000);

                frame.src = pdfUrl;
            }

            document.querySelectorAll('.js-download-pdf').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openPdfViewer(btn.getAttribute('data-pdf-url'));
                });
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', hideOverlay);
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && overlay.classList.contains('is-visible')) {
                    hideOverlay();
                }
            });
        })();

        (function () {
            var rejectOverlay = document.getElementById('reject-overlay');
            var openBtn = document.getElementById('open-reject-modal');
            var closeBtn = document.getElementById('close-reject-modal');
            var rejectForm = document.getElementById('reject-form');
            var rejectReason = document.getElementById('reject-reason');

            if (! rejectOverlay || ! openBtn) {
                return;
            }

            function openRejectModal() {
                rejectOverlay.classList.add('is-visible');
                rejectOverlay.setAttribute('aria-hidden', 'false');
                if (rejectReason) {
                    rejectReason.focus();
                }
            }

            function closeRejectModal() {
                rejectOverlay.classList.remove('is-visible');
                rejectOverlay.setAttribute('aria-hidden', 'true');
            }

            openBtn.addEventListener('click', openRejectModal);

            if (closeBtn) {
                closeBtn.addEventListener('click', closeRejectModal);
            }

            rejectOverlay.addEventListener('click', function (e) {
                if (e.target === rejectOverlay) {
                    closeRejectModal();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && rejectOverlay.classList.contains('is-visible')) {
                    closeRejectModal();
                }
            });

            if (rejectForm) {
                rejectForm.addEventListener('submit', function () {
                    var submitBtn = document.getElementById('submit-reject');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Mengirim…';
                    }
                });
            }

            @if (($openRejectModal ?? false) || $errors->has('reject_reason'))
                openRejectModal();
            @endif
        })();
    </script>
</body>
</html>
