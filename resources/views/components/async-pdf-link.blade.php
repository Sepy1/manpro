@props([
    'href',
])

<a
    {{ $attributes->merge([
        'href' => $href,
        'data-async-pdf' => 'true',
        'data-no-transition' => 'true',
    ]) }}
>{{ $slot }}</a>

@once
    <div
        id="async-pdf-link-overlay"
        class="fixed inset-0 z-[2147483646] hidden flex-col items-center justify-center gap-4 bg-black/45 px-4 backdrop-blur-[2px]"
        role="status"
        aria-live="polite"
        aria-busy="false"
        hidden
        data-async-pdf-overlay
    >
        <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white px-6 py-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-col items-center gap-4">
                <svg
                    class="h-9 w-9 shrink-0 animate-spin text-sky-600 dark:text-sky-400"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
                <p class="text-center text-sm font-semibold text-slate-800 dark:text-slate-100">Menyiapkan PDF</p>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            (function () {
                const OVERLAY_SELECTOR = '[data-async-pdf-overlay]';

                function overlayEl () {
                    return document.querySelector(OVERLAY_SELECTOR);
                }

                function showOverlay () {
                    var el = overlayEl();
                    if (! el) {
                        return;
                    }
                    el.hidden = false;
                    el.removeAttribute('hidden');
                    el.classList.remove('hidden');
                    el.classList.add('flex');
                    el.setAttribute('aria-busy', 'true');
                    document.documentElement.style.overflow = 'hidden';
                }

                function hideOverlay () {
                    var el = overlayEl();
                    if (! el) {
                        document.documentElement.style.overflow = '';

                        return;
                    }
                    el.hidden = true;
                    el.setAttribute('hidden', 'hidden');
                    el.classList.add('hidden');
                    el.classList.remove('flex');
                    el.removeAttribute('aria-busy');
                    document.documentElement.style.overflow = '';
                }

                function pdfTabWriteLoading (tab) {
                    try {
                        tab.document.open();
                        tab.document.write(
                            '<!DOCTYPE html><html lang="id"><meta charset="utf-8"/><title>Menyiapkan PDF</title>' +
                            '<body style="margin:0;font-family:system-ui,sans-serif;display:flex;min-height:100vh;' +
                            'align-items:center;justify-content:center;background:#f8fafc;color:#334155;">' +
                            '<p style="margin:16px;text-align:center">Menyiapkan PDF</p></body></html>',
                        );
                        tab.document.close();
                    } catch (ignore) {}
                }

                /** Buka tautan navigasi biasa selalu di tab baru (tanpa meninggalkan tab ini). */
                function openPdfUrlInNewTab (absoluteUrl) {
                    var fallback = document.createElement('a');
                    fallback.href = absoluteUrl;
                    fallback.target = '_blank';
                    fallback.rel = 'noopener noreferrer';
                    fallback.style.display = 'none';
                    document.body.appendChild(fallback);
                    fallback.click();
                    document.body.removeChild(fallback);
                }

                document.addEventListener('click', function (e) {
                    if (e.defaultPrevented) {
                        return;
                    }
                    if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                        return;
                    }

                    var a = e.target.closest && e.target.closest('a[data-async-pdf][href]');
                    if (! a || a.closest('[data-async-pdf-skip]')) {
                        return;
                    }

                    var url = a.getAttribute('href');
                    if (! url || url.charAt(0) === '#' || url.indexOf('javascript:') === 0) {
                        return;
                    }

                    var absoluteUrl =
                        url.charAt(0) === '/' ? window.location.origin + url : url;

                    e.preventDefault();

                    // Harus dibuka sinkron bersama gesture klik — kalau baru window.open(setelah fetch) sering diblokir dan memaksa pakai tab yang sama.
                    var newTab = window.open('about:blank', '_blank');
                    if (! newTab || newTab.closed) {
                        return;
                    }

                    pdfTabWriteLoading(newTab);
                    showOverlay();

                    fetch(url, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/pdf',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                        .then(function (resp) {
                            if (! resp.ok) {
                                throw new Error('Server mengembalikan ' + resp.status);
                            }

                            return resp.blob();
                        })
                        .then(function (blob) {
                            var t = blob.type || '';
                            if (
                                blob.size < 8 ||
                                (t !== '' &&
                                    t.indexOf('pdf') === -1 &&
                                    t.indexOf('octet-stream') === -1)
                            ) {
                                throw new Error('Bukan file PDF atau sesi Anda sudah kedaluwarsa.');
                            }

                            var u = URL.createObjectURL(blob);
                            try {
                                newTab.location.replace(u);
                            } catch (err) {
                                URL.revokeObjectURL(u);
                                throw err;
                            }

                            window.setTimeout(function () {
                                URL.revokeObjectURL(u);
                            }, 120000);
                        })
                        .catch(function () {
                            try {
                                newTab.location.replace(absoluteUrl);
                            } catch (ignore) {
                                try {
                                    newTab.close();
                                } catch (_) {}
                                openPdfUrlInNewTab(absoluteUrl);
                            }
                        })
                        .finally(function () {
                            hideOverlay();
                        });
                }, true);
            })();
        </script>
    @endpush
@endonce
