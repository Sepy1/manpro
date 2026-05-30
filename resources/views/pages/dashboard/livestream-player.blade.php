@extends('layouts.fullscreen-layout')

@section('content')
    <div
        id="livestream-player-root"
        class="relative h-screen w-screen overflow-hidden bg-slate-950"
        data-pages='@json($pages)'
        data-swipe-interval-ms="{{ $swipeIntervalMs }}"
        data-live-refresh-ms="{{ $liveRefreshMs }}"
        data-tv-width="{{ $tvWidth }}"
        data-tv-height="{{ $tvHeight }}"
        data-exit-url="{{ $exitUrl }}"
    >
        <div class="absolute inset-0">
            <div
                id="livestream-player-viewport"
                class="absolute left-1/2 top-1/2 overflow-hidden bg-black"
                style="transform: translate(-50%, -50%) scale(1); transform-origin: top left;"
            >
                <div
                    id="livestream-player-track"
                    class="flex h-full w-full transition-transform duration-500 ease-[cubic-bezier(0.22,0.61,0.36,1)] will-change-transform"
                    style="transform: translateX(0);"
                >
                    @foreach ($pages as $page)
                        @if (($page['type'] ?? 'page') === 'image')
                            <div class="relative h-full w-full shrink-0 bg-black">
                                <img
                                    src="{{ $page['url'] }}"
                                    alt="Livestream - {{ $page['label'] }}"
                                    class="h-full w-full object-contain"
                                    loading="eager"
                                />
                            </div>
                        @else
                            <iframe
                                src="{{ $page['url'] }}"
                                data-base-src="{{ $page['url'] }}"
                                data-slide-index="{{ $loop->index }}"
                                data-refreshable-frame="1"
                                title="Livestream - {{ $page['label'] }}"
                                class="h-full w-full shrink-0 border-0"
                                loading="eager"
                                referrerpolicy="same-origin"
                            ></iframe>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <div class="pointer-events-none absolute inset-x-0 top-0 z-10 flex items-center justify-between bg-gradient-to-b from-black/45 to-transparent px-4 py-3 text-xs text-white/90">
            <div id="livestream-player-title" class="rounded-md bg-black/35 px-2 py-1">Livestream</div>
            <div class="rounded-md bg-black/35 px-2 py-1">Resolusi: {{ $tvResolutionLabel }} | Esc keluar | ← → pindah</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.getElementById('livestream-player-root');
            const viewport = document.getElementById('livestream-player-viewport');
            const track = document.getElementById('livestream-player-track');
            const titleEl = document.getElementById('livestream-player-title');
            if (!root || !track || !viewport) {
                return;
            }

            const pages = JSON.parse(root.dataset.pages || '[]');
            const total = Array.isArray(pages) ? pages.length : 0;
            if (!total) {
                return;
            }

            const intervalMs = Math.max(parseInt(root.dataset.swipeIntervalMs || '120000', 10) || 120000, 5000);
            const liveRefreshMs = Math.max(parseInt(root.dataset.liveRefreshMs || '30000', 10) || 30000, 5000);
            const tvWidth = Math.max(parseInt(root.dataset.tvWidth || '1920', 10) || 1920, 320);
            const tvHeight = Math.max(parseInt(root.dataset.tvHeight || '1080', 10) || 1080, 240);
            const baseAspect = tvWidth / tvHeight;
            const minDesktopWidth = 1366;
            const layoutWidth = Math.max(tvWidth, minDesktopWidth);
            const layoutHeight = Math.round(layoutWidth / baseAspect);
            const exitUrl = root.dataset.exitUrl || '/admin/dashboard';
            let current = 0;
            let touchStartX = null;
            let touchStartY = null;
            let timer = null;
            let refreshTimer = null;
            let refreshCursor = 0;
            const refreshFrames = Array.from(track.querySelectorAll('[data-refreshable-frame]'));

            const applyViewportScale = () => {
                const screenW = window.innerWidth || document.documentElement.clientWidth || tvWidth;
                const screenH = window.innerHeight || document.documentElement.clientHeight || tvHeight;
                const scale = Math.max(Math.min(screenW / layoutWidth, screenH / layoutHeight), 0.1);

                // Keep desktop-like canvas size on low TV resolutions,
                // then scale down so pages don't switch to responsive/mobile layout.
                viewport.style.width = `${layoutWidth}px`;
                viewport.style.height = `${layoutHeight}px`;
                viewport.style.transform = `translate(-50%, -50%) scale(${scale})`;
            };

            const refreshUrl = (url) => {
                try {
                    const next = new URL(url, window.location.origin);
                    next.searchParams.set('_lsv', String(Date.now()));
                    return next.toString();
                } catch (_) {
                    const hasQuery = String(url).includes('?');
                    return String(url) + (hasQuery ? '&' : '?') + '_lsv=' + Date.now();
                }
            };

            const reloadFrame = (index) => {
                const frame = refreshFrames[index];
                if (!frame) {
                    return;
                }
                const baseSrc = frame.dataset.baseSrc || frame.getAttribute('src') || '';
                if (!baseSrc) {
                    return;
                }
                frame.dataset.baseSrc = baseSrc;
                frame.dataset.lastRefreshAt = String(Date.now());
                frame.setAttribute('src', refreshUrl(baseSrc));
            };

            const refreshHiddenFrame = () => {
                if (!refreshFrames.length) {
                    return;
                }
                if (refreshFrames.length === 1) {
                    reloadFrame(0);
                    return;
                }

                for (let step = 0; step < refreshFrames.length; step++) {
                    const index = (refreshCursor + step) % refreshFrames.length;
                    const slideIndex = parseInt(refreshFrames[index]?.dataset.slideIndex || '-1', 10);
                    if (slideIndex === current) {
                        continue;
                    }
                    refreshCursor = (index + 1) % refreshFrames.length;
                    reloadFrame(index);
                    return;
                }
            };

            const render = () => {
                track.style.transform = `translateX(-${current * 100}%)`;
                const item = pages[current];
                if (titleEl && item?.label) {
                    titleEl.textContent = `Livestream - ${item.label}`;
                }
            };

            const goTo = (index) => {
                current = (index + total) % total;
                render();
            };

            const next = () => goTo(current + 1);
            const prev = () => goTo(current - 1);

            const resetTimer = () => {
                if (timer) {
                    window.clearInterval(timer);
                }
                timer = window.setInterval(next, intervalMs);
            };

            const resetRefreshTimer = () => {
                if (refreshTimer) {
                    window.clearInterval(refreshTimer);
                }
                refreshTimer = window.setInterval(refreshHiddenFrame, liveRefreshMs);
            };

            const tryEnterFullscreen = () => {
                if (document.fullscreenElement || typeof document.documentElement.requestFullscreen !== 'function') {
                    return;
                }
                document.documentElement.requestFullscreen().catch(() => {
                    /* ignore */
                });
            };

            const exitLivestream = () => {
                if (timer) {
                    window.clearInterval(timer);
                }
                if (refreshTimer) {
                    window.clearInterval(refreshTimer);
                }
                if (document.fullscreenElement && typeof document.exitFullscreen === 'function') {
                    document.exitFullscreen().catch(() => {
                        /* ignore */
                    });
                }
                window.location.href = exitUrl;
            };

            document.addEventListener('keydown', (event) => {
                const key = event.key;
                if (key === 'Escape') {
                    event.preventDefault();
                    exitLivestream();
                    return;
                }
                if (key === 'ArrowRight') {
                    event.preventDefault();
                    next();
                    resetTimer();
                    return;
                }
                if (key === 'ArrowLeft') {
                    event.preventDefault();
                    prev();
                    resetTimer();
                }
            });

            root.addEventListener('touchstart', (event) => {
                const t = event.touches?.[0];
                if (!t) {
                    return;
                }
                touchStartX = t.clientX;
                touchStartY = t.clientY;
            }, { passive: true });

            root.addEventListener('touchend', (event) => {
                const t = event.changedTouches?.[0];
                if (!t || touchStartX === null || touchStartY === null) {
                    touchStartX = null;
                    touchStartY = null;
                    return;
                }

                const dx = t.clientX - touchStartX;
                const dy = t.clientY - touchStartY;
                touchStartX = null;
                touchStartY = null;

                if (Math.abs(dx) < 60 || Math.abs(dx) <= Math.abs(dy)) {
                    return;
                }

                if (dx < 0) {
                    next();
                } else {
                    prev();
                }
                resetTimer();
            });

            window.addEventListener('resize', applyViewportScale);
            window.addEventListener('orientationchange', applyViewportScale);

            applyViewportScale();
            render();
            resetTimer();
            resetRefreshTimer();
            tryEnterFullscreen();
        });
    </script>
@endsection
