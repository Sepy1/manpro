@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Dashboard" />
    @php
        $timelineProjects = $projectsForModal->sortBy(function ($project) {
            return $project->period_start ?? $project->deadline ?? now();
        })->values();
        $timelinePalette = [
            'bg-sky-500',
            'bg-violet-500',
            'bg-emerald-500',
            'bg-amber-500',
            'bg-rose-500',
            'bg-cyan-500',
            'bg-fuchsia-500',
            'bg-lime-500',
        ];
    @endphp

    <div
        x-data="{
            showStatusModal: false,
            showTodoProjectModal: false,
            showTodoStepModal: false,
            selectedStatus: '',
            statusLabel: '',
            todoProjectAction: '',
            picNames: @js($picUsers->pluck('name')->values()),
            todoProjectPicError: '',
            todoStepPicError: '',
            todoProjectForm: { name: '', category: 'Development Aplikasi', vendor_id: '', description: '', url: '', pic: '', deadline: '', period_start: '', period_end: '', status: 'planned' },
            todoStepAction: '',
            todoStepForm: { step_name: '', start_date: '', end_date: '', deadline: '', description: '', pic: '', follow_up: '', status: 'planned' },
            projects: @js($projectsForModal->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'status' => $p->status,
                'period_start' => optional($p->period_start)->format('d M Y'),
                'period_end' => optional($p->period_end)->format('d M Y'),
                'deadline' => optional($p->deadline)->format('d M Y'),
                'pic' => $p->pic ?: '-',
                'steps' => $p->total_steps_count,
                'progress' => $p->total_steps_count > 0 ? (int) round(($p->completed_steps_count / $p->total_steps_count) * 100) : 0,
            ])->values()),
            openStatusModal(status, label) {
                this.selectedStatus = String(status || '').toLowerCase();
                this.statusLabel = label;
                this.showStatusModal = true;
            },
            goToDaftarProject(projectId) {
                const baseUrl = @js(route('admin.daftar-project.index'));
                window.location.href = `${baseUrl}?open_project=${projectId}`;
            },
            openTodoProjectModal(el) {
                this.todoProjectAction = el.dataset.action;
                this.todoProjectForm = {
                    name: el.dataset.name || '',
                    category: el.dataset.category || 'Development Aplikasi',
                    vendor_id: el.dataset.vendorId || '',
                    description: el.dataset.description || '',
                    url: el.dataset.url || '',
                    pic: el.dataset.pic || '',
                    deadline: el.dataset.deadline || '',
                    period_start: el.dataset.periodStart || '',
                    period_end: el.dataset.periodEnd || '',
                    status: el.dataset.status || 'planned',
                };
                this.showTodoProjectModal = true;
                this.todoProjectPicError = '';
            },
            openTodoStepModal(el) {
                this.todoStepAction = el.dataset.action;
                this.todoStepForm = {
                    step_name: el.dataset.stepName || '',
                    start_date: el.dataset.startDate || '',
                    end_date: el.dataset.endDate || '',
                    deadline: el.dataset.deadline || '',
                    description: el.dataset.description || '',
                    pic: el.dataset.pic || '',
                    follow_up: el.dataset.followUp || '',
                    status: el.dataset.status || 'planned',
                };
                this.showTodoStepModal = true;
                this.todoStepPicError = '';
            },
            isRegisteredPic(value) {
                if (!String(value || '').trim()) return true;
                return this.picNames.includes(String(value).trim());
            },
            validateTodoProjectPic() {
                this.todoProjectPicError = this.isRegisteredPic(this.todoProjectForm.pic) ? '' : 'PIC belum terdaftar.';
            },
            validateTodoStepPic() {
                this.todoStepPicError = this.isRegisteredPic(this.todoStepForm.pic) ? '' : 'PIC belum terdaftar.';
            },
            get filteredProjects() {
                return this.projects.filter((project) => String(project.status || '').toLowerCase() === this.selectedStatus);
            },
            progressClass(progress) {
                if (progress >= 100) return 'bg-green-500';
                if (progress >= 70) return 'bg-emerald-500';
                if (progress >= 40) return 'bg-yellow-500';
                if (progress > 0) return 'bg-red-500';
                return 'bg-gray-400';
            },
            categoryClass(category) {
                const map = {
                    'Development Aplikasi': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                    'Change Request': 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                    'Audit': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                    'Infrastruktur': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                    'Pengadaan': 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
                    'Kerjasama Vendor': 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
                };
                return map[category] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
            },
        }"
        class="flex min-h-0 h-full flex-col overflow-hidden text-base leading-relaxed md:text-lg"
    >
    <div class="grid shrink-0 grid-cols-1 gap-4 lg:grid-cols-4">
        <button type="button" @click="openStatusModal('planned', 'Planned')" class="rounded-2xl border border-gray-200 bg-white p-4 text-left dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-base font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-400">Planned</p>
            <p class="mt-2 text-4xl font-bold text-gray-800 dark:text-white/90">{{ $projectsByStatus['planned'] }}</p>
        </button>
        <button type="button" @click="openStatusModal('in_progress', 'In Progress')" class="rounded-2xl border border-yellow-200 bg-yellow-50/60 p-4 text-left dark:border-yellow-800/60 dark:bg-yellow-900/10">
            <p class="text-base font-semibold uppercase tracking-wide text-yellow-700 dark:text-yellow-300">In Progress</p>
            <p class="mt-2 text-4xl font-bold text-yellow-800 dark:text-yellow-200">{{ $projectsByStatus['in_progress'] }}</p>
        </button>
        <button type="button" @click="openStatusModal('completed', 'Completed')" class="rounded-2xl border border-green-200 bg-green-50/60 p-4 text-left dark:border-green-800/60 dark:bg-green-900/10">
            <p class="text-base font-semibold uppercase tracking-wide text-green-700 dark:text-green-300">Completed</p>
            <p class="mt-2 text-4xl font-bold text-green-800 dark:text-green-200">{{ $projectsByStatus['completed'] }}</p>
        </button>
        <button type="button" @click="openStatusModal('delayed', 'Delayed')" class="rounded-2xl border border-red-200 bg-red-50/60 p-4 text-left dark:border-red-800/60 dark:bg-red-900/10">
            <p class="text-base font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">Delayed</p>
            <p class="mt-2 text-4xl font-bold text-red-800 dark:text-red-200">{{ $projectsByStatus['delayed'] }}</p>
        </button>
    </div>

    <div class="mt-4 grid min-h-0 flex-1 grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="grid min-h-0 grid-cols-1 gap-4 xl:col-span-2 xl:grid-cols-2">
            <div class="flex min-h-0 flex-col rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Todo Project & Step</h3>
                </div>

                <div class="min-h-0 flex-1">
                    <div class="h-full space-y-3 overflow-y-auto pr-1">
                        @forelse ($todoProjects as $project)
                            @php
                                $todoProgress = $project->total_steps_count > 0
                                    ? (int) round(($project->completed_steps_count / $project->total_steps_count) * 100)
                                    : 0;
                                $todoProgressClass = match (true) {
                                    $todoProgress >= 100 => 'bg-green-500',
                                    $todoProgress >= 70 => 'bg-emerald-500',
                                    $todoProgress >= 40 => 'bg-yellow-500',
                                    $todoProgress > 0 => 'bg-red-500',
                                    default => 'bg-gray-400',
                                };
                            @endphp
                            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="min-w-0 flex-1 truncate text-left text-lg font-semibold text-gray-800 hover:underline dark:text-white/90"
                                        @click="openTodoProjectModal($el)"
                                        data-action="{{ route('admin.daftar-project.update', $project) }}"
                                        data-name="{{ $project->name }}"
                                        data-category="{{ $project->category }}"
                                        data-vendor-id="{{ $project->vendor_id }}"
                                        data-description="{{ $project->description }}"
                                        data-url="{{ $project->url }}"
                                        data-pic="{{ $project->pic }}"
                                        data-deadline="{{ optional($project->deadline)->format('Y-m-d') }}"
                                        data-period-start="{{ optional($project->period_start)->format('Y-m-d') }}"
                                        data-period-end="{{ optional($project->period_end)->format('Y-m-d') }}"
                                        data-status="{{ $project->status }}"
                                    >
                                        {{ $project->name }}
                                    </button>
                                    @php
                                        $todoCategoryTone = $categoryBadgeColors[$project->category] ?? 'gray';
                                        $todoCategoryClass = match ($todoCategoryTone) {
                                            'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                            'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                                            'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                            'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                            'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
                                            'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
                                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                        };
                                    @endphp
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $todoCategoryClass }}">
                                        {{ $project->category }}
                                    </span>
                                    <div class="w-32 shrink-0">
                                        <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div class="h-2 rounded-full {{ $todoProgressClass }}" style="width: {{ $todoProgress }}%"></div>
                                        </div>
                                        <p class="mt-1 text-right text-sm text-gray-500 dark:text-gray-400">{{ $todoProgress }}%</p>
                                    </div>
                                </div>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($project->steps as $step)
                                        <li class="flex items-start justify-between gap-2 text-lg">
                                            <button
                                                type="button"
                                                class="text-left text-gray-700 hover:underline dark:text-gray-300"
                                                @click="openTodoStepModal($el)"
                                                data-action="{{ route('admin.daftar-project.step.update', $step) }}"
                                                data-step-name="{{ $step->step_name }}"
                                                data-start-date="{{ optional($step->start_date)->format('Y-m-d') }}"
                                                data-end-date="{{ optional($step->end_date)->format('Y-m-d') }}"
                                                data-deadline="{{ optional($step->deadline)->format('Y-m-d') }}"
                                                data-description="{{ $step->description }}"
                                                data-pic="{{ $step->pic }}"
                                                data-follow-up="{{ $step->follow_up }}"
                                                data-status="{{ $step->status }}"
                                            >
                                                - {{ $step->step_name }}
                                            </button>
                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs
                                                {{ $step->status === 'delayed' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300' }}">
                                                {{ str_replace('_', ' ', ucfirst($step->status)) }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @empty
                            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-300">
                                Semua step sudah completed. Mantap!
                            </div>
                        @endforelse
                    </div>
                </div>
                <div class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-800">
                    {{ $todoProjects->onEachSide(2)->links() }}
                </div>
            </div>

            <div class="flex min-h-0 flex-col rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Mendekati Deadline</h3>
                <p class="mt-1 text-base text-gray-500 dark:text-gray-400">
                    Project dan step prioritas dengan deadline terdekat.
                </p>
                <div class="mt-3 min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                    @php
                        $deadlineGroups = $upcomingDeadlines->groupBy(fn ($step) => $step->project_id ?? 'no_project');
                    @endphp
                    @forelse ($deadlineGroups as $projectSteps)
                        @php
                            $firstStep = $projectSteps->first();
                            $project = $firstStep?->project;
                        @endphp
                        <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                            @if ($project)
                                <button
                                    type="button"
                                    class="text-left text-base font-semibold text-gray-800 hover:underline dark:text-white/90"
                                    @click="openTodoProjectModal($el)"
                                    data-action="{{ route('admin.daftar-project.update', $project) }}"
                                    data-name="{{ $project->name }}"
                                    data-category="{{ $project->category }}"
                                    data-vendor-id="{{ $project->vendor_id }}"
                                    data-description="{{ $project->description }}"
                                    data-url="{{ $project->url }}"
                                    data-pic="{{ $project->pic }}"
                                    data-deadline="{{ optional($project->deadline)->format('Y-m-d') }}"
                                    data-period-start="{{ optional($project->period_start)->format('Y-m-d') }}"
                                    data-period-end="{{ optional($project->period_end)->format('Y-m-d') }}"
                                    data-status="{{ $project->status }}"
                                >
                                    {{ $project->name }}
                                </button>
                                @php
                                    $deadlineCategoryTone = $categoryBadgeColors[$project->category] ?? 'gray';
                                    $deadlineCategoryClass = match ($deadlineCategoryTone) {
                                        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                        'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                                        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                        'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
                                        'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
                                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                    };
                                @endphp
                                <span class="ml-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $deadlineCategoryClass }}">
                                    {{ $project->category }}
                                </span>
                            @else
                                <p class="text-base font-semibold text-gray-700 dark:text-gray-300">Project tidak ditemukan</p>
                            @endif

                            <div class="mt-2 space-y-1">
                                @foreach ($projectSteps as $step)
                                    <button
                                        type="button"
                                        class="block w-full text-left text-base text-gray-700 hover:underline dark:text-gray-300"
                                        @click="openTodoStepModal($el)"
                                        data-action="{{ route('admin.daftar-project.step.update', $step) }}"
                                        data-step-name="{{ $step->step_name }}"
                                        data-start-date="{{ optional($step->start_date)->format('Y-m-d') }}"
                                        data-end-date="{{ optional($step->end_date)->format('Y-m-d') }}"
                                        data-deadline="{{ optional($step->deadline)->format('Y-m-d') }}"
                                        data-description="{{ $step->description }}"
                                        data-pic="{{ $step->pic }}"
                                        data-follow-up="{{ $step->follow_up }}"
                                        data-status="{{ $step->status }}"
                                    >
                                        - {{ $step->step_name }}
                                        <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">
                                            (Deadline: {{ optional($step->deadline)->format('d M Y') ?? '-' }})
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada project atau step yang mendekati deadline.</p>
                    @endforelse
                </div>
                <div class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-800">
                    {{ $upcomingDeadlines->onEachSide(2)->links() }}
                </div>
            </div>
        </div>

        <div class="min-h-0"
            x-data="{
                selectedMonth: new Date().getMonth() + 1,
                selectedYear: new Date().getFullYear(),
                monthNames: ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'],
                weekdayNames: ['Sen','Sel','Rab','Kam','Jum','Sab','Min'],
                timelineProjects: @js($timelineProjects->values()->map(function ($project, $idx) use ($timelinePalette) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'start_date' => optional($project->period_start)->format('Y-m-d'),
                        'deadline' => optional($project->deadline)->format('Y-m-d'),
                        'color' => $timelinePalette[$idx % count($timelinePalette)],
                    ];
                })),
                get monthLabel() {
                    return this.monthNames[this.selectedMonth - 1] + ' ' + this.selectedYear;
                },
                get yearOptions() {
                    const current = new Date().getFullYear();
                    return [current - 1, current, current + 1, current + 2];
                },
                daysInMonth() {
                    return new Date(this.selectedYear, this.selectedMonth, 0).getDate();
                },
                firstWeekday() {
                    const jsDay = new Date(this.selectedYear, this.selectedMonth - 1, 1).getDay();
                    return jsDay === 0 ? 7 : jsDay;
                },
                formatIso(day) {
                    const mm = String(this.selectedMonth).padStart(2, '0');
                    const dd = String(day).padStart(2, '0');
                    return `${this.selectedYear}-${mm}-${dd}`;
                },
                dayBars(day) {
                    const dateIso = this.formatIso(day);
                    return this.timelineProjects.filter((project) => {
                        if (!project.start_date || !project.deadline) return false;
                        return dateIso >= project.start_date && dateIso <= project.deadline;
                    }).slice(0, 2).map((project) => ({
                        ...project,
                        isStart: dateIso === project.start_date,
                        isEnd: dateIso === project.deadline,
                    }));
                },
                init() {
                    // Force default filter to current month/year on every page load.
                    const now = new Date();
                    this.selectedMonth = now.getMonth() + 1;
                    this.selectedYear = now.getFullYear();
                    this.$nextTick(() => {
                        if (this.$refs.monthFilter) this.$refs.monthFilter.value = String(this.selectedMonth);
                        if (this.$refs.yearFilter) this.$refs.yearFilter.value = String(this.selectedYear);
                    });
                },
            }">
            <div class="flex h-full min-h-0 flex-col rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Timeline Projects</h3>
                    <div class="flex items-center gap-2">
                        <select
                            x-ref="monthFilter"
                            :value="selectedMonth"
                            @change="selectedMonth = Number($event.target.value)"
                            autocomplete="off"
                            class="rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm font-medium dark:border-gray-700"
                        >
                            <template x-for="(monthName, idx) in monthNames" :key="monthName">
                                <option :value="idx + 1" x-text="monthName"></option>
                            </template>
                        </select>
                        <select
                            x-ref="yearFilter"
                            :value="selectedYear"
                            @change="selectedYear = Number($event.target.value)"
                            autocomplete="off"
                            class="rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm font-medium dark:border-gray-700"
                        >
                            <template x-for="year in yearOptions" :key="year">
                                <option :value="year" x-text="year"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                    Kalender <span x-text="monthLabel"></span> - marker bar menunjukkan rentang mulai hingga deadline.
                </p>

                <div class="grid flex-1 grid-cols-7 gap-1 text-center text-xs text-gray-500 dark:text-gray-400">
                    <template x-for="dayName in weekdayNames" :key="dayName">
                        <div class="font-semibold" x-text="dayName"></div>
                    </template>

                    <template x-for="blank in Math.max(firstWeekday() - 1, 0)" :key="'blank-' + blank">
                        <div class="h-11 rounded-md bg-transparent"></div>
                    </template>

                    <template x-for="day in daysInMonth()" :key="'day-' + day">
                        <div class="h-11 rounded-md border border-gray-200 p-0.5 dark:border-gray-700">
                            <div class="font-medium text-gray-700 dark:text-gray-300" x-text="day"></div>
                            <div class="mt-0.5 space-y-0.5">
                                <template x-for="(bar, barIdx) in dayBars(day)" :key="'bar-' + day + '-' + barIdx">
                                    <div
                                        class="h-1.5 w-full cursor-help"
                                        :class="[bar.color, bar.isStart ? 'rounded-l-full' : '', bar.isEnd ? 'rounded-r-full' : '']"
                                        :title="bar.name"
                                    ></div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showTodoProjectModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showTodoProjectModal = false">
        <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Update Project</h4>
                <button type="button" @click="showTodoProjectModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
            </div>
            <form :action="todoProjectAction" method="POST" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @csrf
                @method('PUT')
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Project</label>
                    <input type="text" name="name" x-model="todoProjectForm.name" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">PIC</label>
                    <input type="text" name="pic" list="pic-user-options" x-model="todoProjectForm.pic" @blur="validateTodoProjectPic()" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <p x-show="todoProjectPicError" x-cloak class="mt-1 text-sm text-red-600 dark:text-red-400" x-text="todoProjectPicError"></p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Kategori</label>
                    <select name="category" x-model="todoProjectForm.category" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                        @foreach ($projectCategories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="todoProjectForm.category === 'Kerjasama Vendor'" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Vendor</label>
                    <select name="vendor_id" x-model="todoProjectForm.vendor_id" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                        <option value="">Pilih vendor</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Keterangan</label>
                    <textarea name="description" rows="3" x-model="todoProjectForm.description" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">URL</label>
                    <input type="url" name="url" x-model="todoProjectForm.url" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Deadline Project</label>
                    <input type="date" name="deadline" x-model="todoProjectForm.deadline" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Periode mulai</label>
                    <input type="date" name="period_start" x-model="todoProjectForm.period_start" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Periode selesai</label>
                    <input type="date" name="period_end" x-model="todoProjectForm.period_end" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Status Project</label>
                    <select name="status" x-model="todoProjectForm.status" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
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

    <div x-show="showTodoStepModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showTodoStepModal = false">
        <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Update Step Project</h4>
                <button type="button" @click="showTodoStepModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
            </div>
            <form :action="todoStepAction" method="POST" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @csrf
                @method('PUT')
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Step</label>
                    <input type="text" name="step_name" x-model="todoStepForm.step_name" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Start Date</label>
                    <input type="date" name="start_date" x-model="todoStepForm.start_date" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">End Date</label>
                    <input type="date" name="end_date" x-model="todoStepForm.end_date" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Deadline</label>
                    <input type="date" name="deadline" x-model="todoStepForm.deadline" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">PIC</label>
                    <input type="text" name="pic" list="pic-user-options" x-model="todoStepForm.pic" @blur="validateTodoStepPic()" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <p x-show="todoStepPicError" x-cloak class="mt-1 text-sm text-red-600 dark:text-red-400" x-text="todoStepPicError"></p>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Keterangan</label>
                    <textarea name="description" rows="2" x-model="todoStepForm.description" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Tindak Lanjut</label>
                    <textarea name="follow_up" rows="2" x-model="todoStepForm.follow_up" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Status</label>
                    <select name="status" x-model="todoStepForm.status" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
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

    <div x-show="showStatusModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showStatusModal = false">
        <div class="max-h-[90vh] w-full max-w-7xl overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Daftar Project - <span x-text="statusLabel"></span></h4>
                <button type="button" @click="showStatusModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
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
                            <th class="w-[20%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="filteredProjects.length === 0">
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada project pada status ini.</td>
                            </tr>
                        </template>
                        <template x-for="project in filteredProjects" :key="project.id">
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="truncate px-3 py-3 text-sm font-medium text-gray-800 dark:text-white/90">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="truncate text-left text-brand-600 hover:underline dark:text-brand-400"
                                            @click="goToDaftarProject(project.id)"
                                            x-text="project.name"
                                        ></button>
                                        <span
                                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="categoryClass(project.category)"
                                            x-text="project.category"
                                        ></span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300" x-text="project.status.replace('_',' ')"></td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300" x-text="project.period_start || '-'"></td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300" x-text="project.period_end || '-'"></td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300" x-text="project.deadline || '-'"></td>
                                <td class="truncate px-3 py-3 text-sm text-gray-700 dark:text-gray-300" x-text="project.pic"></td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300" x-text="project.steps"></td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div class="h-2 rounded-full" :class="progressClass(project.progress)" :style="'width: ' + project.progress + '%'"></div>
                                        </div>
                                        <span class="w-10 shrink-0 text-right text-xs font-medium" x-text="project.progress + '%'"></span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
        <datalist id="pic-user-options">
            @foreach ($picUsers as $picUser)
                <option value="{{ $picUser->name }}"></option>
            @endforeach
        </datalist>
    </div>
@endsection
