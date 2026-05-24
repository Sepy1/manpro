<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Tindak Lanjut CR — {{ $cr->nomor }}</title>
    <style>
        :root {
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #2563eb;
            --success: #16a34a;
            --danger: #dc2626;
            --shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --safe-top: env(safe-area-inset-top, 0px);
            --actions-height: 0px;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        html {
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            background: linear-gradient(160deg, #eff6ff 0%, var(--bg) 45%, #f8fafc 100%);
            color: var(--text);
            padding:
                calc(1rem + var(--safe-top))
                max(1rem, env(safe-area-inset-right, 0px))
                calc(1.5rem + var(--actions-height) + var(--safe-bottom))
                max(1rem, env(safe-area-inset-left, 0px));
        }

        .wrap {
            max-width: 32rem;
            margin: 0 auto;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 1.15rem 1.15rem 1rem;
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
            color: #fff;
        }

        .badge {
            display: inline-block;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: .28rem .6rem;
            border-radius: 999px;
            margin-bottom: .6rem;
        }

        .card-header h1 {
            margin: 0 0 .4rem;
            font-size: clamp(1.05rem, 4.5vw, 1.2rem);
            line-height: 1.35;
            font-weight: 700;
        }

        .card-header p {
            margin: 0;
            font-size: .88rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.92);
        }

        .card-body { padding: 1rem 1.15rem 1.15rem; }

        .field {
            padding: .8rem 0;
            border-bottom: 1px solid var(--border);
        }

        .field:last-child { border-bottom: none; padding-bottom: 0; }

        .field-label {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: .3rem;
        }

        .field-value {
            font-size: .95rem;
            font-weight: 600;
            line-height: 1.45;
            color: var(--text);
            overflow-wrap: anywhere;
        }

        .field-value.description {
            font-weight: 500;
            white-space: pre-line;
            word-break: break-word;
            font-size: .92rem;
            line-height: 1.55;
        }

        .footnote {
            margin: 1rem 0 0;
            font-size: .8rem;
            line-height: 1.45;
            color: var(--muted);
            text-align: center;
        }

        /* Desktop / tablet inline actions */
        .actions-inline {
            display: none;
            grid-template-columns: 1fr;
            gap: .65rem;
            margin-top: 1.25rem;
        }

        /* Mobile sticky bottom bar */
        .actions-sticky {
            display: block;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 40;
            padding:
                .75rem max(1rem, env(safe-area-inset-left, 0px))
                calc(.75rem + var(--safe-bottom))
                max(1rem, env(safe-area-inset-right, 0px));
            background: rgba(255, 255, 255, 0.94);
            border-top: 1px solid var(--border);
            box-shadow: 0 -8px 30px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .actions-sticky-inner {
            max-width: 32rem;
            margin: 0 auto;
            display: grid;
            gap: .55rem;
        }

        .actions-sticky.has-decision-btns {
            grid-template-columns: 1fr 1fr;
        }

        .actions-sticky .btn-pdf {
            grid-column: 1 / -1;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            min-height: 3rem;
            padding: .75rem 1rem;
            border-radius: .75rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            line-height: 1.2;
            border: none;
            cursor: pointer;
            width: 100%;
            touch-action: manipulation;
            user-select: none;
            -webkit-user-select: none;
            transition: opacity .12s ease, transform .12s ease, box-shadow .12s ease;
        }

        @media (hover: hover) and (pointer: fine) {
            .btn:hover { transform: translateY(-1px); }
        }

        .btn:active { transform: scale(0.98); opacity: .95; }
        .btn:disabled { opacity: .65; cursor: wait; transform: none; }

        .btn-pdf {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.28);
        }

        .btn-approve {
            background: var(--success);
            color: #fff;
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.22);
        }

        .btn-reject {
            background: var(--danger);
            color: #fff;
            box-shadow: 0 4px 14px rgba(220, 38, 38, 0.22);
        }

        @media (min-width: 640px) {
            body {
                padding:
                    calc(1.5rem + var(--safe-top))
                    1rem
                    calc(2.5rem + var(--safe-bottom))
                    1rem;
            }

            .card-header { padding: 1.35rem 1.35rem 1.1rem; }
            .card-body { padding: 1.25rem 1.35rem 1.35rem; }

            .actions-inline {
                display: grid;
            }

            .actions-inline.has-decision-btns {
                grid-template-columns: 1fr 1fr;
            }

            .actions-inline .btn-pdf { grid-column: 1 / -1; }

            .actions-sticky { display: none; }

            :root { --actions-height: 0px; }
        }

        .pdf-overlay {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: none;
            align-items: center;
            justify-content: center;
            padding: max(1rem, env(safe-area-inset-top, 0px)) 1rem max(1rem, env(safe-area-inset-bottom, 0px));
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .pdf-overlay.is-visible { display: flex; }

        .pdf-toast {
            width: min(100%, 22rem);
            background: #fff;
            border-radius: .9rem;
            padding: 1.15rem 1.2rem 1rem;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
        }

        .pdf-toast-title {
            margin: 0 0 .35rem;
            font-size: .95rem;
            font-weight: 700;
            color: var(--text);
        }

        .pdf-toast-text {
            margin: 0 0 .85rem;
            font-size: .84rem;
            color: var(--muted);
            line-height: 1.45;
        }

        .progress-track {
            height: .45rem;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #2563eb, #3b82f6);
            border-radius: 999px;
            transition: width .25s ease;
        }

        .progress-label {
            margin-top: .45rem;
            font-size: .75rem;
            font-weight: 600;
            color: var(--muted);
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $deskripsi = trim((string) ($cr->deskripsi_permintaan ?? ''));
        if ($deskripsi === '') {
            $deskripsi = trim((string) ($cr->perubahan_diharapkan ?? ''));
        }
        if ($deskripsi === '') {
            $deskripsi = '—';
        }
        $hasDecision = ($cr->wa_authorization_decision ?? null) === null;
    @endphp

    <div class="wrap">
        <div class="card">
            <div class="card-header">
                <span class="badge">Otorisasi CR</span>
                <h1>Tindak lanjut Change Request</h1>
                <p>Tinjau detail berikut, unduh PDF bila perlu, lalu tentukan keputusan Anda.</p>
            </div>

            <div class="card-body">
                <div class="field">
                    <div class="field-label">Nomor CR</div>
                    <div class="field-value">{{ $cr->nomor }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Nama aplikasi</div>
                    <div class="field-value">{{ $cr->application?->name ?? '—' }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Nama Change Request</div>
                    <div class="field-value">{{ $cr->nama ?? '—' }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Pembuat</div>
                    <div class="field-value">{{ $cr->creator?->name ?? '—' }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Deskripsi perubahan</div>
                    <div class="field-value description">{{ $deskripsi }}</div>
                </div>

                <div class="actions-inline{{ $hasDecision ? ' has-decision-btns' : '' }}">
                    <button type="button" class="btn btn-pdf js-download-pdf" data-pdf-url="{{ $pdfUrl }}">
                        Unduh PDF
                    </button>
                    @if ($hasDecision)
                        <a class="btn btn-approve" href="{{ $approveUrl }}">Setujui</a>
                        <a class="btn btn-reject" href="{{ $rejectUrl }}">Tolak</a>
                    @endif
                </div>

                @if (! $hasDecision)
                    <p class="footnote">CR ini sudah memiliki keputusan otorisasi ({{ $cr->wa_authorization_decision }}).</p>
                @else
                    <p class="footnote">Keputusan pertama yang dicatat akan menjadi keputusan resmi.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="actions-sticky{{ $hasDecision ? ' has-decision-btns' : '' }}" id="actions-sticky">
        <div class="actions-sticky-inner">
            <button type="button" class="btn btn-pdf js-download-pdf" data-pdf-url="{{ $pdfUrl }}">
                Unduh PDF
            </button>
            @if ($hasDecision)
                <a class="btn btn-approve" href="{{ $approveUrl }}">Setujui</a>
                <a class="btn btn-reject" href="{{ $rejectUrl }}">Tolak</a>
            @endif
        </div>
    </div>

    <div class="pdf-overlay" id="pdf-overlay" aria-hidden="true">
        <div class="pdf-toast" role="status" aria-live="polite">
            <p class="pdf-toast-title">Memproses PDF</p>
            <p class="pdf-toast-text" id="pdf-toast-text">Bundel PDF sedang disiapkan. Dokumen akan dibuka setelah selesai.</p>
            <div class="progress-track">
                <div class="progress-bar" id="pdf-progress-bar"></div>
            </div>
            <div class="progress-label" id="pdf-progress-label">0%</div>
        </div>
    </div>

    <script>
        (function () {
            var overlay = document.getElementById('pdf-overlay');
            var bar = document.getElementById('pdf-progress-bar');
            var label = document.getElementById('pdf-progress-label');
            var toastText = document.getElementById('pdf-toast-text');
            var stickyBar = document.getElementById('actions-sticky');
            var busy = false;
            var timer = null;

            function isMobileLayout() {
                return window.matchMedia('(max-width: 639px)').matches;
            }

            function syncStickyPadding() {
                if (!stickyBar || !isMobileLayout()) {
                    document.documentElement.style.setProperty('--actions-height', '0px');
                    return;
                }
                document.documentElement.style.setProperty('--actions-height', stickyBar.offsetHeight + 'px');
            }

            syncStickyPadding();
            window.addEventListener('resize', syncStickyPadding);
            window.addEventListener('orientationchange', syncStickyPadding);

            function setProgress(value) {
                var pct = Math.max(0, Math.min(100, Math.round(value)));
                bar.style.width = pct + '%';
                label.textContent = pct + '%';
            }

            function showOverlay() {
                overlay.classList.add('is-visible');
                overlay.setAttribute('aria-hidden', 'false');
            }

            function hideOverlay() {
                overlay.classList.remove('is-visible');
                overlay.setAttribute('aria-hidden', 'true');
                setProgress(0);
            }

            function animateTo(target, ms) {
                var start = parseFloat(bar.style.width) || 0;
                var begin = performance.now();
                clearInterval(timer);
                timer = setInterval(function () {
                    var elapsed = performance.now() - begin;
                    var ratio = Math.min(1, elapsed / ms);
                    setProgress(start + (target - start) * ratio);
                    if (ratio >= 1) clearInterval(timer);
                }, 40);
            }

            function openPdfBlob(blobUrl) {
                if (isMobileLayout()) {
                    window.location.href = blobUrl;
                    return;
                }

                var tab = window.open(blobUrl, '_blank');
                if (!tab) {
                    window.location.href = blobUrl;
                }
            }

            function bindPdfButtons() {
                document.querySelectorAll('.js-download-pdf').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var pdfUrl = btn.getAttribute('data-pdf-url');
                        if (busy || !pdfUrl) return;

                        busy = true;
                        document.querySelectorAll('.js-download-pdf').forEach(function (b) {
                            b.disabled = true;
                        });

                        showOverlay();
                        toastText.textContent = isMobileLayout()
                            ? 'Bundel PDF sedang disiapkan. Halaman PDF akan terbuka di perangkat Anda.'
                            : 'Bundel PDF sedang disiapkan. Tab baru akan terbuka setelah selesai.';
                        setProgress(8);
                        animateTo(72, 1200);

                        fetch(pdfUrl, { credentials: 'same-origin' })
                            .then(function (response) {
                                if (!response.ok) throw new Error('Gagal memuat PDF');
                                return response.blob();
                            })
                            .then(function (blob) {
                                animateTo(100, 350);
                                var blobUrl = URL.createObjectURL(blob);
                                openPdfBlob(blobUrl);
                                setTimeout(function () {
                                    URL.revokeObjectURL(blobUrl);
                                    hideOverlay();
                                    document.querySelectorAll('.js-download-pdf').forEach(function (b) {
                                        b.disabled = false;
                                    });
                                    busy = false;
                                }, isMobileLayout() ? 1200 : 500);
                            })
                            .catch(function () {
                                clearInterval(timer);
                                hideOverlay();
                                document.querySelectorAll('.js-download-pdf').forEach(function (b) {
                                    b.disabled = false;
                                });
                                busy = false;
                                window.location.href = pdfUrl;
                            });
                    });
                });
            }

            bindPdfButtons();
        })();
    </script>
</body>
</html>
