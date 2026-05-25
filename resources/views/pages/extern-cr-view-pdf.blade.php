<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1e40af">
    <title>Lihat CR — {{ $cr->nomor }}</title>
    <style>
        :root {
            --font: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #2563eb;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --toolbar-h: 3.35rem;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            height: 100%;
            font-family: var(--font);
            color: var(--text);
            background: #0f172a;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            min-height: var(--toolbar-h);
            padding:
                calc(0.55rem + var(--safe-top))
                0.85rem
                0.55rem;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #fff;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.25);
        }

        .toolbar-main { min-width: 0; }

        .toolbar-title {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 700;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .toolbar-sub {
            margin: 0.12rem 0 0;
            font-size: 0.72rem;
            opacity: 0.9;
            overflow-wrap: anywhere;
        }

        .toolbar-actions {
            display: flex;
            flex-shrink: 0;
            gap: 0.4rem;
        }

        .btn {
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
            white-space: nowrap;
        }

        .btn:active { opacity: 0.85; }

        .viewer {
            height: calc(100dvh - var(--toolbar-h) - var(--safe-top));
            background: #334155;
        }

        .pdf-frame {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            background: #fff;
        }

        .attachments {
            background: #f8fafc;
            border-top: 1px solid var(--border);
            padding: 0.85rem 0.85rem calc(0.85rem + var(--safe-bottom));
        }

        .attachments h2 {
            margin: 0 0 0.55rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .attachment-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .attachment-item a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.6rem;
            background: #fff;
            color: var(--text);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .attachment-item a:active { background: #eff6ff; }

        .attachment-name {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .attachment-action {
            flex-shrink: 0;
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .footnote {
            margin: 0.65rem 0 0;
            font-size: 0.72rem;
            line-height: 1.35;
            color: var(--muted);
        }

        @media (min-width: 768px) {
            .viewer { height: calc(100dvh - var(--toolbar-h) - var(--safe-top) - 0.5rem); }
        }
    </style>
</head>
<body>
    <header class="toolbar">
        <div class="toolbar-main">
            <p class="toolbar-title">{{ $cr->nomor }}</p>
            <p class="toolbar-sub">{{ $cr->nama ?: 'Change Request' }}</p>
        </div>
        <div class="toolbar-actions">
            <a class="btn" href="{{ $mergedPdfUrl }}" target="_blank" rel="noopener">Buka PDF</a>
        </div>
    </header>

    <main class="viewer">
        <iframe
            class="pdf-frame"
            src="{{ $mergedPdfUrl }}"
            title="PDF formulir CR dan lampiran PDF"
        ></iframe>
    </main>

    @if ($pdfAttachments->isNotEmpty() || $otherAttachments->isNotEmpty())
        <section class="attachments">
            @if ($pdfAttachments->isNotEmpty())
                <h2>Lampiran PDF</h2>
                <ul class="attachment-list">
                    @foreach ($pdfAttachments as $attachment)
                        <li class="attachment-item">
                            <a href="{{ route('extern-cr.view-by-nomor.attachment', ['nomor' => \App\Support\WhatsappCrAuthorizationButtonCodes::viewCrUrlSuffix($cr), 'attachment' => $attachment->id]) }}" target="_blank" rel="noopener">
                                <span class="attachment-name">{{ $attachment->original_name ?: 'Lampiran PDF #'.$attachment->id }}</span>
                                <span class="attachment-action">Lihat</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($otherAttachments->isNotEmpty())
                <h2 style="{{ $pdfAttachments->isNotEmpty() ? 'margin-top: 0.85rem;' : '' }}">Lampiran lain</h2>
                <ul class="attachment-list">
                    @foreach ($otherAttachments as $attachment)
                        <li class="attachment-item">
                            <a href="{{ route('extern-cr.view-by-nomor.attachment', ['nomor' => \App\Support\WhatsappCrAuthorizationButtonCodes::viewCrUrlSuffix($cr), 'attachment' => $attachment->id]) }}">
                                <span class="attachment-name">{{ $attachment->original_name ?: 'Lampiran #'.$attachment->id }}</span>
                                <span class="attachment-action">Unduh</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="footnote">Pratinjau di atas menampilkan formulir CR beserta lampiran PDF yang digabung. Gunakan daftar di bawah untuk membuka lampiran PDF per file atau mengunduh lampiran non-PDF.</p>
        </section>
    @endif
</body>
</html>
