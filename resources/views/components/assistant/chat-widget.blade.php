@props([])

@guest
@else
    @if (auth()->user()?->role === 'admin')
        <div
            x-data="assistantChat({
                endpoint: @js(route('admin.assistant.chat')),
                csrf: @js(csrf_token()),
            })"
            class="pointer-events-none fixed bottom-5 right-5 z-[100002] flex flex-col items-end gap-3 print:hidden"
            x-cloak
        >
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-y-2 opacity-0 scale-95"
                x-transition:enter-end="translate-y-0 opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="pointer-events-auto content-card flex max-h-[min(44rem,82vh)] w-[min(100vw-2.5rem,30rem)] flex-col overflow-hidden shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-slate-200/80 bg-slate-50/90 px-4 py-3.5 dark:border-gray-800 dark:bg-gray-900/60">
                    <div class="flex min-w-0 flex-1 items-center gap-3 sm:gap-4">
                        <span class="inline-flex shrink-0 items-center justify-center rounded-full bg-gradient-to-r from-sky-500 via-sky-600 to-indigo-600 px-4 py-2 text-center shadow-md ring-1 ring-white/25 dark:ring-white/10">
                            <span class="text-sm font-semibold leading-none tracking-tight text-white sm:text-[0.95rem]">Asisten AI</span>
                        </span>
                        <p class="min-w-0 truncate text-xs leading-snug text-slate-500 sm:text-sm dark:text-gray-400">
                            Data Center, CCTV, Proyek
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-200/80 dark:text-gray-400 dark:hover:bg-white/10"
                        @click="open = false"
                        aria-label="Tutup chat"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div
                    class="min-h-0 flex-1 space-y-4 overflow-y-auto bg-white/95 p-4 dark:bg-gray-900/80"
                    x-ref="scroll"
                >
                    <p class="text-sm text-slate-500 dark:text-gray-400" x-show="messages.length === 0">
                        Tanya tentang metrik Data Center (PRTG), ringkasan CCTV, pencarian perangkat CCTV, atau daftar proyek.
                    </p>
                    <template x-for="(m, idx) in messages" :key="idx">
                        <div
                            class="text-sm leading-relaxed"
                            :class="m.role === 'user'
                                ? 'ml-3 rounded-2xl rounded-br-md bg-sky-600 px-4 py-2.5 text-white shadow-sm sm:ml-5 sm:px-4 sm:py-3'
                                : 'mr-3 rounded-2xl rounded-bl-md border border-slate-200/90 bg-slate-50 px-4 py-2.5 text-slate-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 sm:mr-5 sm:px-4 sm:py-3'"
                        >
                            <span class="whitespace-pre-wrap" x-text="m.content"></span>
                        </div>
                    </template>
                    <div x-show="loading" class="flex items-center gap-2 text-sm text-slate-500 dark:text-gray-400">
                        <span class="inline-flex h-2.5 w-2.5 animate-pulse rounded-full bg-sky-500"></span>
                        Memproses…
                    </div>
                    <div x-show="error" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-200" x-text="error"></div>
                </div>

                <form class="pointer-events-auto border-t border-slate-200/80 bg-white p-3 sm:p-4 dark:border-gray-800 dark:bg-gray-900/90" @submit.prevent="send()">
                    <label for="assistant-chat-input" class="sr-only">Pesan ke asisten</label>
                    <div class="flex gap-3">
                        <textarea
                            id="assistant-chat-input"
                            x-model="input"
                            rows="3"
                            placeholder="Ketik pertanyaan…"
                            class="min-h-[3.25rem] flex-1 resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/25 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500"
                            @keydown.enter.prevent="if (!$event.shiftKey) send()"
                        ></textarea>
                        <button
                            type="submit"
                            class="shrink-0 self-end rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 disabled:opacity-50 sm:px-5 sm:py-3"
                            :disabled="loading || !input.trim()"
                        >
                            Kirim
                        </button>
                    </div>
                </form>
            </div>

            <button
                type="button"
                class="pointer-events-auto inline-flex min-h-[3.5rem] max-w-[calc(100vw-2.5rem)] items-center justify-center rounded-full bg-gradient-to-r from-sky-500 via-sky-600 to-indigo-600 px-6 py-3.5 text-white shadow-lg shadow-slate-900/25 ring-2 ring-white/35 transition hover:scale-[1.02] hover:shadow-xl active:scale-[0.98] dark:ring-gray-900/50 sm:min-h-[3.75rem] sm:px-7 sm:py-4"
                @click="open = !open"
                :aria-expanded="open"
                aria-label="Buka atau tutup Asisten AI"
            >
                <span x-show="!open" class="text-sm font-semibold leading-none tracking-tight whitespace-nowrap sm:text-base">Asisten AI</span>
                <svg x-show="open" x-cloak class="h-7 w-7 shrink-0 sm:h-8 sm:w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>
    @endif
@endguest
