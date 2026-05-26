@extends('layouts.fullscreen-layout')

@section('content')
    <div
        id="livestream-player-root"
        class="relative h-screen w-screen overflow-hidden bg-slate-950"
        data-pages='@json($pages)'
        data-swipe-interval-ms="{{ $swipeIntervalMs }}"
        data-exit-url="{{ $exitUrl }}"
    >
        <div
            id="livestream-player-track"
            class="flex h-full w-full transition-transform duration-500 ease-[cubic-bezier(0.22,0.61,0.36,1)] will-change-transform"
            style="transform: translateX(0);"
        >
            @foreach ($pages as $page)
                <iframe
                    src="{{ $page['url'] }}"
                    title="Livestream - {{ $page['label'] }}"
                    class="h-full w-full shrink-0 border-0"
                    loading="eager"
                    referrerpolicy="same-origin"
                ></iframe>
            @endforeach
        </div>

        <div class="pointer-events-none absolute inset-x-0 top-0 z-10 flex items-center justify-between bg-gradient-to-b from-black/45 to-transparent px-4 py-3 text-xs text-white/90">
            <div id="livestream-player-title" class="rounded-md bg-black/35 px-2 py-1">Livestream</div>
            <div class="rounded-md bg-black/35 px-2 py-1">Esc keluar | ← → pindah</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.getElementById('livestream-player-root');
            const track = document.getElementById('livestream-player-track');
            const titleEl = document.getElementById('livestream-player-title');
            if (!root || !track) {
                return;
            }

            const pages = JSON.parse(root.dataset.pages || '[]');
            const total = Array.isArray(pages) ? pages.length : 0;
            if (!total) {
                return;
            }

            const intervalMs = Math.max(parseInt(root.dataset.swipeIntervalMs || '120000', 10) || 120000, 5000);
            const exitUrl = root.dataset.exitUrl || '/admin/dashboard';
            let current = 0;
            let touchStartX = null;
            let touchStartY = null;
            let timer = null;

            const render = () => {
                track.style.transform = `translateX(-${current * 100}vw)`;
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

            render();
            resetTimer();
            tryEnterFullscreen();
        });
    </script>
@endsection
