<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Tindak Lanjut CR — {{ $cr->nomor }}</title>
    <style>
        :root {
            --font: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            --fs-xs: 0.6875rem;
            --fs-sm: 0.8125rem;
            --fs-base: 0.875rem;
            --fs-md: 0.9375rem;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #2563eb;
            --success: #16a34a;
            --danger: #dc2626;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        html {
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
            font-size: 16px;
        }

        body {
            font-family: var(--font);
            font-size: var(--fs-base);
            line-height: 1.35;
            margin: 0;
            height: 100dvh;
            overflow: hidden;
            color: var(--text);
            background: linear-gradient(165deg, #eff6ff 0%, #f1f5f9 50%, #f8fafc 100%);
            padding:
                calc(0.4rem + var(--safe-top))
                0.5rem
                calc(0.4rem + var(--safe-bottom));
        }

        .wrap {
            max-width: 28rem;
            height: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .card {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            box-shadow: 0 6px 24px rgba(15, 23, 42, 0.07);
            overflow: hidden;
        }

        .card-header {
            flex-shrink: 0;
            padding: 0.55rem 0.7rem;
            background: linear-gradient(135deg, #1e40af, #2563eb);
            color: #fff;
        }

        .card-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .card-header h1 {
            margin: 0;
            font-size: var(--fs-md);
            font-weight: 700;
            line-height: 1.25;
        }

        .badge {
            flex-shrink: 0;
            font-size: var(--fs-xs);
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.22);
            padding: 0.15rem 0.45rem;
            border-radius: 999px;
        }

        .card-body {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            padding: 0.55rem 0.7rem 0.65rem;
        }

        .meta-grid {
            flex-shrink: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.35rem 0.55rem;
            margin-bottom: 0.45rem;
        }

        .meta-item.span-2 { grid-column: 1 / -1; }

        .field-label {
            font-size: var(--fs-xs);
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.1rem;
        }

        .field-value {
            font-size: var(--fs-sm);
            font-weight: 600;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .description-box {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            margin-bottom: 0.45rem;
        }

        .description-scroll {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0.4rem 0.45rem;
            border: 1px solid var(--border);
            border-radius: 0.45rem;
            background: #f8fafc;
        }

        .field-value.description {
            font-size: var(--fs-sm);
            font-weight: 500;
            line-height: 1.4;
            white-space: pre-line;
            word-break: break-word;
        }

        .actions {
            flex-shrink: 0;
            display: grid;
            gap: 0.35rem;
        }

        .actions.has-decision-btns {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.25rem;
            padding: 0.35rem 0.45rem;
            border-radius: 0.45rem;
            text-decoration: none;
            font-family: var(--font);
            font-size: var(--fs-sm);
            font-weight: 600;
            line-height: 1.1;
            border: none;
            cursor: pointer;
            width: 100%;
            touch-action: manipulation;
        }

        .btn:active { opacity: 0.92; transform: scale(0.98); }
        .btn:disabled { opacity: 0.6; cursor: wait; }

        .btn-pdf { background: var(--primary); color: #fff; }
        .btn-approve { background: var(--success); color: #fff; }
        .btn-reject { background: var(--danger); color: #fff; }

        .footnote {
            flex-shrink: 0;
            margin: 0.35rem 0 0;
            font-size: var(--fs-xs);
            line-height: 1.3;
            color: var(--muted);
            text-align: center;
        }

        @media (min-width: 640px) {
            body {
                height: auto;
                min-height: 100dvh;
                overflow: auto;
                padding: 1rem;
            }

            .wrap {
                height: auto;
                max-width: 32rem;
            }

            .card {
                flex: none;
            }

            .card-header { padding: 0.75rem 0.85rem; }
            .card-body { padding: 0.75rem 0.85rem 0.85rem; }

            .description-box { flex: none; }
            .description-scroll {
                max-height: 9rem;
                flex: none;
            }

            .actions.has-decision-btns {
                grid-template-columns: 1fr 1fr;
            }

            .actions .btn-pdf { grid-column: 1 / -1; }

            .btn {
                min-height: 2.5rem;
                font-size: var(--fs-base);
            }
        }

        .pdf-overlay {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.45);
        }

        .pdf-overlay.is-visible { display: flex; }

        .pdf-toast {
            width: min(100%, 20rem);
            background: #fff;
            border-radius: 0.65rem;
            padding: 0.85rem 0.9rem;
            font-size: var(--fs-sm);
        }

        .pdf-toast-title {
            margin: 0 0 0.25rem;
            font-size: var(--fs-base);
            font-weight: 700;
        }

        .pdf-toast-text {
            margin: 0 0 0.6rem;
            font-size: var(--fs-sm);
            color: var(--muted);
            line-height: 1.35;
        }

        .progress-track {
            height: 0.35rem;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            background: var(--primary);
            transition: width 0.2s ease;
        }

        .progress-label {
            margin-top: 0.3rem;
            font-size: var(--fs-xs);
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
                <div class="card-header-top">
                    <h1>Tindak lanjut CR</h1>
                    <span class="badge">Otorisasi</span>
                </div>
            </div>

            <div class="card-body">
                <div class="meta-grid">
                    <div class="meta-item">
                        <div class="field-label">Nomor CR</div>
                        <div class="field-value">{{ $cr->nomor }}</div>
                    </div>
                    <div class="meta-item">
                        <div class="field-label">Aplikasi</div>
                        <div class="field-value">{{ $cr->application?->name ?? '—' }}</div>
                    </div>
                    <div class="meta-item span-2">
                        <div class="field-label">Nama Change Request</div>
                        <div class="field-value">{{ $cr->nama ?? '—' }}</div>
                    </div>
                    <div class="meta-item span-2">
                        <div class="field-label">Pembuat</div>
                        <div class="field-value">{{ $cr->creator?->name ?? '—' }}</div>
                    </div>
                </div>

                <div class="description-box">
                    <div class="field-label">Deskripsi perubahan</div>
                    <div class="description-scroll">
                        <div class="field-value description">{{ $deskripsi }}</div>
                    </div>
                </div>

                <div class="actions{{ $hasDecision ? ' has-decision-btns' : '' }}">
                    <button type="button" class="btn btn-pdf js-download-pdf" data-pdf-url="{{ $pdfUrl }}">PDF</button>
                    @if ($hasDecision)
                        <a class="btn btn-approve" href="{{ $approveUrl }}">Setujui</a>
                        <a class="btn btn-reject" href="{{ $rejectUrl }}">Tolak</a>
                    @endif
                </div>

                @if (! $hasDecision)
                    <p class="footnote">Sudah ada keputusan: {{ $cr->wa_authorization_decision }}</p>
                @else
                    <p class="footnote">Keputusan pertama menjadi keputusan resmi.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="pdf-overlay" id="pdf-overlay" aria-hidden="true">
        <div class="pdf-toast" role="status" aria-live="polite">
            <p class="pdf-toast-title">Memproses PDF</p>
            <p class="pdf-toast-text" id="pdf-toast-text">Mohon tunggu…</p>
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
            var busy = false;
            var timer = null;

            function isMobileLayout() {
                return window.matchMedia('(max-width: 639px)').matches;
            }

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
                    var ratio = Math.min(1, (performance.now() - begin) / ms);
                    setProgress(start + (target - start) * ratio);
                    if (ratio >= 1) clearInterval(timer);
                }, 40);
            }

            document.querySelectorAll('.js-download-pdf').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var pdfUrl = btn.getAttribute('data-pdf-url');
                    if (busy || !pdfUrl) return;
                    busy = true;
                    document.querySelectorAll('.js-download-pdf').forEach(function (b) { b.disabled = true; });
                    showOverlay();
                    setProgress(8);
                    animateTo(72, 1000);

                    fetch(pdfUrl, { credentials: 'same-origin' })
                        .then(function (r) { if (!r.ok) throw new Error(); return r.blob(); })
                        .then(function (blob) {
                            animateTo(100, 300);
                            var blobUrl = URL.createObjectURL(blob);
                            window.location.href = blobUrl;
                            setTimeout(function () {
                                URL.revokeObjectURL(blobUrl);
                                hideOverlay();
                                document.querySelectorAll('.js-download-pdf').forEach(function (b) { b.disabled = false; });
                                busy = false;
                            }, 800);
                        })
                        .catch(function () {
                            clearInterval(timer);
                            hideOverlay();
                            document.querySelectorAll('.js-download-pdf').forEach(function (b) { b.disabled = false; });
                            busy = false;
                            window.location.href = pdfUrl;
                        });
                });
            });
        })();
    </script>
</body>
</html>
