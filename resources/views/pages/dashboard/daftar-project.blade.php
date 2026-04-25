@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Daftar Project" />

    <div
        x-data="{
            openProjectId: null,
            showProjectModal: false,
            showStepModal: false,
            projectAction: '',
            stepAction: '',
            projectForm: { name: '', description: '', url: '', pic: '', deadline: '', period_start: '', period_end: '', status: 'planned' },
            stepForm: { step_name: '', start_date: '', end_date: '', deadline: '', description: '', pic: '', follow_up: '', status: 'planned' },
            openProjectModal(el) {
                this.projectAction = el.dataset.action;
                this.projectForm = {
                    name: el.dataset.name || '',
                    description: el.dataset.description || '',
                    url: el.dataset.url || '',
                    pic: el.dataset.pic || '',
                    deadline: el.dataset.deadline || '',
                    period_start: el.dataset.periodStart || '',
                    period_end: el.dataset.periodEnd || '',
                    status: el.dataset.status || 'planned',
                };
                this.showProjectModal = true;
            },
            openStepModal(el) {
                this.stepAction = el.dataset.action;
                this.stepForm = {
                    step_name: el.dataset.stepName || '',
                    start_date: el.dataset.startDate || '',
                    end_date: el.dataset.endDate || '',
                    deadline: el.dataset.deadline || '',
                    description: el.dataset.description || '',
                    pic: el.dataset.pic || '',
                    follow_up: el.dataset.followUp || '',
                    status: el.dataset.status || 'planned',
                };
                this.showStepModal = true;
            },
        }"
        class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
    >
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Daftar Project</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Klik salah satu baris project untuk menampilkan daftar langkah project.
            </p>
        </div>

        <div>
            <table class="min-w-full table-fixed border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="w-[20%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama Project</th>
                        <th class="w-[10%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                        <th class="w-[10%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Start Date</th>
                        <th class="w-[10%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">End Date</th>
                        <th class="w-[10%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Deadline</th>
                        <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">PIC</th>
                        <th class="w-[8%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Step</th>
                        <th class="w-[15%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Progress</th>
                        <th class="w-[5%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($projects as $project)
                        @php
                            $totalSteps = $project->steps->count();
                            $completedSteps = $project->steps->where('status', 'completed')->count();
                            $progressPct = $totalSteps > 0 ? (int) round(($completedSteps / $totalSteps) * 100) : 0;
                            $progressBarClass = match (true) {
                                $progressPct >= 100 => 'bg-green-500',
                                $progressPct >= 70 => 'bg-emerald-500',
                                $progressPct >= 40 => 'bg-yellow-500',
                                $progressPct > 0 => 'bg-red-500',
                                default => 'bg-gray-400',
                            };
                        @endphp
                        <tr
                            class="cursor-pointer border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]"
                            @click="openProjectId = openProjectId === {{ $project->id }} ? null : {{ $project->id }}"
                        >
                            <td class="truncate px-3 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $project->name }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', ucfirst($project->status)) }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ optional($project->period_start)->format('d M Y') ?? '-' }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ optional($project->period_end)->format('d M Y') ?? '-' }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ optional($project->deadline)->format('d M Y') ?? '-' }}</td>
                            <td class="truncate px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $project->pic ?: '-' }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $totalSteps }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div
                                            class="h-2 rounded-full {{ $progressBarClass }}"
                                            style="width: {{ $progressPct }}%"
                                        ></div>
                                    </div>
                                    <span class="w-10 shrink-0 text-right text-xs font-medium">{{ $progressPct }}%</span>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <button
                                    type="button"
                                    @click.stop="openProjectModal($el)"
                                    data-action="{{ route('admin.daftar-project.update', $project) }}"
                                    data-name="{{ $project->name }}"
                                    data-description="{{ $project->description }}"
                                    data-url="{{ $project->url }}"
                                    data-pic="{{ $project->pic }}"
                                    data-deadline="{{ optional($project->deadline)->format('Y-m-d') }}"
                                    data-period-start="{{ optional($project->period_start)->format('Y-m-d') }}"
                                    data-period-end="{{ optional($project->period_end)->format('Y-m-d') }}"
                                    data-status="{{ $project->status }}"
                                    class="rounded-lg border border-brand-500 px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10"
                                >
                                    Update
                                </button>
                            </td>
                        </tr>

                        <tr x-show="openProjectId === {{ $project->id }}" x-transition.opacity class="border-b border-gray-100 dark:border-gray-800">
                            <td colspan="9" class="px-3 pb-4 pt-2">
                                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-white/[0.02]">
                                    <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Step Project</h4>

                                    @if ($project->steps->isEmpty())
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada langkah untuk project ini.</p>
                                    @else
                                        <div class="space-y-2">
                                            @foreach ($project->steps as $step)
                                                @php
                                                    $stepStatusClasses = match ($step->status) {
                                                        'in_progress' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                                                        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                                        'delayed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                                    };
                                                @endphp
                                                <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900/40">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                                            {{ $loop->iteration }}. {{ $step->step_name }}
                                                        </p>
                                                        <div class="flex items-center gap-2">
                                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $stepStatusClasses }}">
                                                                {{ str_replace('_', ' ', ucfirst($step->status)) }}
                                                            </span>
                                                            <button
                                                                type="button"
                                                                @click.stop="openStepModal($el)"
                                                                data-action="{{ route('admin.daftar-project.step.update', $step) }}"
                                                                data-step-name="{{ $step->step_name }}"
                                                                data-start-date="{{ optional($step->start_date)->format('Y-m-d') }}"
                                                                data-end-date="{{ optional($step->end_date)->format('Y-m-d') }}"
                                                                data-deadline="{{ optional($step->deadline)->format('Y-m-d') }}"
                                                                data-description="{{ $step->description }}"
                                                                data-pic="{{ $step->pic }}"
                                                                data-follow-up="{{ $step->follow_up }}"
                                                                data-status="{{ $step->status }}"
                                                                class="rounded-lg border border-brand-500 px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10"
                                                            >
                                                                Update
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                                        Start: {{ optional($step->start_date)->format('d M Y') ?? '-' }} |
                                                        End: {{ optional($step->end_date)->format('d M Y') ?? '-' }} |
                                                        Deadline: {{ optional($step->deadline)->format('d M Y') ?? '-' }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Belum ada data project.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-800">
            {{ $projects->links() }}
        </div>

        <div x-show="showProjectModal" x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showProjectModal = false">
            <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Update Project</h4>
                    <button type="button" @click="showProjectModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>
                <form :action="projectAction" method="POST" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Project</label>
                        <input type="text" name="name" x-model="projectForm.name" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">PIC</label>
                        <input type="text" name="pic" x-model="projectForm.pic" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Keterangan</label>
                        <textarea name="description" rows="3" x-model="projectForm.description" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">URL</label>
                        <input type="url" name="url" x-model="projectForm.url" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Deadline Project</label>
                        <input type="date" name="deadline" x-model="projectForm.deadline" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Periode mulai</label>
                        <input type="date" name="period_start" x-model="projectForm.period_start" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Periode selesai</label>
                        <input type="date" name="period_end" x-model="projectForm.period_end" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Status Project</label>
                        <select name="status" x-model="projectForm.status" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                            <option value="planned">Planned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="delayed">Delayed</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Simpan Update Project</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showStepModal" x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showStepModal = false">
            <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Update Step Project</h4>
                    <button type="button" @click="showStepModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>
                <form :action="stepAction" method="POST" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Step</label>
                        <input type="text" name="step_name" x-model="stepForm.step_name" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Start Date</label>
                        <input type="date" name="start_date" x-model="stepForm.start_date" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">End Date</label>
                        <input type="date" name="end_date" x-model="stepForm.end_date" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Deadline</label>
                        <input type="date" name="deadline" x-model="stepForm.deadline" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">PIC</label>
                        <input type="text" name="pic" x-model="stepForm.pic" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Keterangan</label>
                        <textarea name="description" rows="2" x-model="stepForm.description" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Tindak Lanjut</label>
                        <textarea name="follow_up" rows="2" x-model="stepForm.follow_up" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Status</label>
                        <select name="status" x-model="stepForm.status" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                            <option value="planned">Planned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="delayed">Delayed</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Simpan Update Step</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
