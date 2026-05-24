<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>@yield('title', 'Otorisasi Change Request')</title>
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
            --warning: #d97706;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }

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

        .wrap { max-width: 26rem; margin: 0 auto; }

        .card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .card-header {
            position: relative;
            padding: 1.1rem 1rem 1.15rem;
            color: #fff;
            overflow: hidden;
        }

        .card-header--primary { background: linear-gradient(135deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%); }
        .card-header--success { background: linear-gradient(135deg, #15803d 0%, #16a34a 55%, #22c55e 100%); }
        .card-header--danger { background: linear-gradient(135deg, #b91c1c 0%, #dc2626 55%, #ef4444 100%); }
        .card-header--warning { background: linear-gradient(135deg, #b45309 0%, #d97706 55%, #f59e0b 100%); }
        .card-header--neutral { background: linear-gradient(135deg, #334155 0%, #475569 55%, #64748b 100%); }

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

        .card-body { padding: 1rem; }

        .status-icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
        }

        .status-icon--success { background: #dcfce7; color: var(--success); }
        .status-icon--danger { background: #fee2e2; color: var(--danger); }
        .status-icon--warning { background: #fef3c7; color: var(--warning); }
        .status-icon--neutral { background: #f1f5f9; color: var(--muted); }

        .status-title {
            margin: 0 0 0.35rem;
            font-size: 1rem;
            font-weight: 700;
            text-align: center;
            line-height: 1.3;
        }

        .status-text {
            margin: 0;
            font-size: 0.84rem;
            line-height: 1.45;
            color: #334155;
            text-align: center;
        }

        .meta-box {
            margin-top: 0.85rem;
            padding: 0.75rem 0.85rem;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 0.65rem;
        }

        .meta-row + .meta-row {
            margin-top: 0.55rem;
            padding-top: 0.55rem;
            border-top: 1px solid var(--border);
        }

        .field-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.15rem;
        }

        .field-value {
            font-size: 0.88rem;
            font-weight: 700;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .info-note {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-top: 0.85rem;
            padding: 0.65rem 0.75rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 0.55rem;
            font-size: 0.75rem;
            line-height: 1.35;
            color: #1e40af;
        }

        .info-note--warning {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }

        .info-note--danger {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .info-note svg { flex-shrink: 0; margin-top: 0.05rem; }

        .form-group { margin-top: 0.85rem; }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 0.35rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 0.85rem;
            border: 1px solid var(--border);
            border-radius: 0.65rem;
            font-family: var(--font);
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-align: center;
            color: var(--text);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-error {
            margin: 0.35rem 0 0;
            font-size: 0.78rem;
            color: var(--danger);
        }

        .form-status {
            margin: 0 0 0.5rem;
            font-size: 0.78rem;
            color: var(--success);
            text-align: center;
        }

        .actions {
            margin-top: 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }

        .btn-bar {
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

        .btn-bar:active { transform: scale(0.99); opacity: 0.95; }

        .btn-primary { background: var(--primary); box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25); }
        .btn-primary:active { background: var(--primary-dark); }

        .btn-success { background: var(--success); box-shadow: 0 2px 8px rgba(22, 163, 74, 0.25); }
        .btn-danger { background: var(--danger); box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25); }

        .btn-link {
            background: transparent;
            color: var(--primary);
            box-shadow: none;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 0.35rem;
        }

        .btn-link:active { transform: none; opacity: 0.8; }

        .footnote {
            margin: 0.85rem 0 0;
            font-size: 0.72rem;
            line-height: 1.35;
            color: var(--muted);
            text-align: center;
        }

        @media (min-width: 640px) {
            body { padding: 1.25rem; }
            .wrap { max-width: 28rem; }
        }
    </style>
    @stack('head')
</head>
<body>
    <div class="wrap">
        <div class="card">
            @yield('card')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
