<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Project Management</title>
    <style>
        @page { margin: 10mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #0f172a;
            background: #ffffff;
        }
        .sheet {
            width: 188mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 6mm 4mm 4mm;
        }
        .title-box {
            border: 1px solid #111827;
            text-align: center;
            padding: 11px 8px;
            margin-bottom: 20px;
        }
        .title-1 {
            margin: 0 0 5px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }
        .title-2 {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 16px;
        }
        .status-table td { padding: 0 4px; }
        .status-card {
            border: 1px solid #334155;
            border-radius: 11px;
            text-align: center;
            padding: 8px 4px;
        }
        .status-label {
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }
        .status-value {
            margin-top: 4px;
            font-size: 24px;
            font-weight: 700;
            line-height: 1.1;
        }
        .planned { background: #d1d5db; }
        .progress { background: #e5e700; }
        .completed { background: #00b140; color: #ffffff; }
        .delayed { background: #e10600; color: #ffffff; }

        .section-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 6px;
        }
        .section-table td { padding: 0 4px; }
        .section-head {
            border: 1px solid #9ca3af;
            text-align: center;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            padding: 4px 6px;
        }
        .content-table {
            width: 100%;
            border-collapse: collapse;
        }
        .content-table td {
            width: 50%;
            padding: 0 4px;
            vertical-align: top;
        }
        .big-card {
            border: 1px solid #9ca3af;
            padding: 7px 8px;
            page-break-inside: avoid;
        }
        .project-row {
            margin: 0 0 9px;
            padding: 0 0 7px;
            border-bottom: 1px solid #d1d5db;
        }
        .project-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: 0;
        }
        .project-title {
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 4px;
        }
        .project-progress {
            float: right;
            display: inline-block;
            font-weight: 700;
            margin-left: 6px;
        }
        .line-row {
            width: 100%;
            border-collapse: collapse;
            margin: 1px 0;
        }
        .line-row td {
            padding: 0;
            line-height: 1.35;
        }
        .line-left {
            width: 74%;
            padding-right: 6px;
        }
        .line-right {
            width: 26%;
            text-align: right;
            white-space: nowrap;
        }
        .step-status-text {
            font-size: 8px;
            font-weight: 700;
            text-transform: capitalize;
        }
        .step-status-planned {
            color: #6b7280;
        }
        .step-status-in_progress {
            color: #92400e;
        }
        .step-status-completed {
            color: #166534;
        }
        .step-status-delayed {
            color: #b91c1c;
        }
        .empty {
            border: 1px solid #9ca3af;
            padding: 7px 8px;
            font-style: italic;
            color: #475569;
        }
        .summary-card {
            margin-top: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
            padding: 8px 9px;
        }
        .summary-head {
            margin: 0 0 5px;
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
        }
        .summary-source {
            font-size: 8px;
            color: #64748b;
            font-weight: 600;
        }
        .summary-body {
            font-size: 9px;
            color: #334155;
            line-height: 1.45;
            white-space: normal;
        }
        .page-break {
            page-break-before: always;
        }
        .project-list-page {
            width: 188mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 6mm 4mm 4mm;
        }
        .project-list-title {
            margin: 0 0 10px;
            padding: 8px;
            border: 1px solid #111827;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .project-list-head {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 8px;
            border: 1px solid #cbd5e1;
            background: #f1f5f9;
        }
        .project-list-head th {
            padding: 5px 4px;
            text-align: left;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
        }
        .project-list-head th.align-right {
            text-align: right;
        }
        .project-card-dark {
            margin: 0 0 10px;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
            color: #1e293b;
            page-break-inside: avoid;
        }
        .project-list-master-card {
            margin: 0;
            page-break-inside: auto;
        }
        .project-list-project-block {
            margin: 0 0 9px;
            padding: 0 0 8px;
            border-bottom: 1px solid #d1d5db;
        }
        .project-list-project-block:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: 0;
        }
        .project-head-dark {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 7px;
        }
        .project-head-dark td {
            padding: 0 4px 2px 0;
            vertical-align: top;
            font-size: 9px;
            line-height: 1.35;
            color: #334155;
        }
        .project-name-dark {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }
        .pill-dark {
            display: inline-block;
            margin-left: 4px;
            padding: 1px 5px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            font-size: 8px;
            color: #1e293b;
            background: #ffffff;
        }
        .status-pill {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            font-size: 8px;
            font-weight: 700;
            text-transform: capitalize;
            background: #ffffff;
        }
        .status-planned-pill { color: #b45309; border-color: #f59e0b; }
        .status-progress-pill { color: #0369a1; border-color: #0ea5e9; }
        .status-completed-pill { color: #15803d; border-color: #22c55e; }
        .status-delayed-pill { color: #be123c; border-color: #f43f5e; }
        .progress-dark-wrap {
            width: 100%;
            height: 6px;
            border-radius: 999px;
            background: #e2e8f0;
            margin-top: 2px;
        }
        .progress-dark-fill {
            height: 6px;
            border-radius: 999px;
            background: #f59e0b;
        }
        .steps-label-dark {
            margin: 0 0 6px;
            font-size: 9px;
            color: #475569;
        }
        .step-card-dark {
            margin: 0 0 5px;
            padding: 6px 7px;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #ffffff;
        }
        .step-card-dark:last-child { margin-bottom: 0; }
        .step-head-dark {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 2px;
        }
        .step-head-dark td {
            padding: 0;
            vertical-align: top;
            font-size: 9px;
            line-height: 1.35;
        }
        .step-name-dark {
            font-weight: 700;
            color: #0f172a;
        }
        .step-meta-dark {
            font-size: 8px;
            color: #64748b;
            line-height: 1.3;
        }
    </style>
</head>
<body>
@php
    $monthMap = [
        1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
        5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
        9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
    ];

    $plannedCount = $projects->where('status', 'planned')->count();
    $inProgressCount = $projects->where('status', 'in_progress')->count();
    $completedCount = $projects->where('status', 'completed')->count();
    $delayedCount = $projects->where('status', 'delayed')->count();

    $periodStartDate = !empty($filters['period_start'])
        ? \Illuminate\Support\Carbon::parse($filters['period_start'])
        : $projects->pluck('period_start')->filter()->sort()->first();
    $periodEndDate = !empty($filters['period_end'])
        ? \Illuminate\Support\Carbon::parse($filters['period_end'])
        : $projects->pluck('period_end')->filter()->sortDesc()->first();

    $periodStartLabel = $periodStartDate
        ? $periodStartDate->format('j') . ' ' . ($monthMap[(int) $periodStartDate->format('n')] ?? strtoupper($periodStartDate->format('F'))) . ' ' . $periodStartDate->format('Y')
        : '-';
    $periodEndLabel = $periodEndDate
        ? $periodEndDate->format('j') . ' ' . ($monthMap[(int) $periodEndDate->format('n')] ?? strtoupper($periodEndDate->format('F'))) . ' ' . $periodEndDate->format('Y')
        : '-';

    $maxProjectsPerSection = 8;
    $maxStepsPerProject = 2;

    $todoProjects = $projects
        ->filter(function ($project) {
            return $project->steps->where('status', '!=', 'completed')->isNotEmpty();
        });

    $deadlineProjects = $projects
        ->map(function ($project) use ($maxStepsPerProject) {
            $steps = $project->steps
                ->where('status', '!=', 'completed')
                ->filter(function ($step) {
                    return !is_null($step->deadline);
                })
                ->sortBy('deadline')
                ->take($maxStepsPerProject)
                ->values();

            return ['project' => $project, 'steps' => $steps];
        })
        ->filter(function ($row) {
            return $row['steps']->isNotEmpty();
        })
        ->sortBy(function ($row) {
            return optional($row['steps']->first())->deadline;
        });

    $todoList = $todoProjects->values();
    $deadlineList = $deadlineProjects->values();
    $todoChunks = $todoList->chunk($maxProjectsPerSection)->values();
    $deadlineChunks = $deadlineList->chunk($maxProjectsPerSection)->values();
    $totalPages = max($todoChunks->count(), $deadlineChunks->count(), 1);
@endphp

@for ($page = 0; $page < $totalPages; $page++)
    <div class="sheet {{ $page > 0 ? 'page-break' : '' }}">
        @if ($page === 0)
            <div class="title-box">
                <p class="title-1">LAPORAN PROJECT MANAGEMENT DIVISI OPERASIONAL</p>
                <p class="title-2">PERIODE {{ $periodStartLabel }} - {{ $periodEndLabel }}</p>
            </div>

            <table class="status-table">
                <tr>
                    <td width="25%">
                        <div class="status-card planned">
                            <div class="status-label">PLANNED</div>
                            <div class="status-value">{{ $plannedCount }}</div>
                        </div>
                    </td>
                    <td width="25%">
                        <div class="status-card progress">
                            <div class="status-label">IN - PROGRESS</div>
                            <div class="status-value">{{ $inProgressCount }}</div>
                        </div>
                    </td>
                    <td width="25%">
                        <div class="status-card completed">
                            <div class="status-label">COMPLETED</div>
                            <div class="status-value">{{ $completedCount }}</div>
                        </div>
                    </td>
                    <td width="25%">
                        <div class="status-card delayed">
                            <div class="status-label">DELAYED</div>
                            <div class="status-value">{{ $delayedCount }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        @endif

        <table class="section-table">
            <tr>
                <td width="50%"><div class="section-head">TO DO PROJECT + STEP</div></td>
                <td width="50%"><div class="section-head">MENDEKATI DEADLINE</div></td>
            </tr>
        </table>

        @php
            $todoPageList = $todoChunks->get($page, collect());
            $deadlinePageList = $deadlineChunks->get($page, collect());
        @endphp
        <table class="content-table">
            <tr>
            <td>
                @if ($todoPageList->isEmpty())
                    <div class="empty">Tidak ada project dengan step aktif.</div>
                @else
                    <div class="big-card">
                        @foreach ($todoPageList as $todoProject)
                            @php
                                $totalSteps = $todoProject->steps->count();
                                $completedSteps = $todoProject->steps->where('status', 'completed')->count();
                                $progress = $totalSteps > 0 ? (int) round(($completedSteps / $totalSteps) * 100) : 0;
                                $steps = $todoProject->steps
                                    ->where('status', '!=', 'completed')
                                    ->sortBy('deadline')
                                    ->take($maxStepsPerProject);
                            @endphp
                            <div class="project-row">
                                <div class="project-title">
                                    {{ $todoProject->name }}
                                    <span class="project-progress">{{ $progress }} %</span>
                                </div>
                                @foreach ($steps as $step)
                                    <table class="line-row">
                                        <tr>
                                            <td class="line-left">- {{ $step->step_name }}</td>
                                            <td class="line-right">
                                                <span class="step-status-text step-status-{{ $step->status }}">
                                                    {{ str_replace('_', ' ', $step->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </td>
            <td>
                @if ($deadlinePageList->isEmpty())
                    <div class="empty">Tidak ada step yang mendekati deadline.</div>
                @else
                    <div class="big-card">
                        @foreach ($deadlinePageList as $deadlineProject)
                            <div class="project-row">
                                <div class="project-title">{{ $deadlineProject['project']->name }}</div>
                                @foreach ($deadlineProject['steps'] as $step)
                                    <table class="line-row">
                                        <tr>
                                            <td class="line-left">- {{ $step->step_name }}</td>
                                            <td class="line-right">
                                                <span class="step-status-text step-status-{{ $step->status }}">
                                                    {{ str_replace('_', ' ', $step->status) }}
                                                </span>
                                                {{ optional($step->deadline)->format('d M Y') ?: '-' }}
                                            </td>
                                        </tr>
                                    </table>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </td>
            </tr>
        </table>
        @if ($page === 0)
            <div class="summary-card">
                <p class="summary-head">
                    Summary Project
                    <span class="summary-source">({{ $summarySource ?? 'Sistem' }})</span>
                </p>
                <div class="summary-body">{!! nl2br(e($summaryProjectText ?? '-')) !!}</div>
            </div>
        @endif
    </div>
@endfor

<div class="project-list-page page-break">
    @if ($projects->isEmpty())
        <div class="empty">Tidak ada data project sesuai filter.</div>
    @else
        <div class="project-card-dark project-list-master-card">
            @foreach ($projects as $project)
                @php
                    $totalStepsProject = $project->steps->count();
                    $completedStepsProject = $project->steps->where('status', 'completed')->count();
                    $progressProject = $totalStepsProject > 0 ? (int) round(($completedStepsProject / $totalStepsProject) * 100) : 0;
                    $projectStatusClass = match ($project->status) {
                        'planned' => 'status-planned-pill',
                        'in_progress' => 'status-progress-pill',
                        'completed' => 'status-completed-pill',
                        'delayed' => 'status-delayed-pill',
                        default => 'status-planned-pill',
                    };
                @endphp
                <div class="project-list-project-block">
                    <table class="project-head-dark">
                        <tr>
                            <td width="28%">
                                <div class="project-name-dark">
                                    {{ $project->name }}
                                    <span class="pill-dark">{{ $project->category }}</span>
                                </div>
                            </td>
                            <td width="11%"><span class="status-pill {{ $projectStatusClass }}">{{ str_replace('_', ' ', $project->status) }}</span></td>
                            <td width="10%">Start: {{ optional($project->period_start)->format('d M Y') ?: '-' }}</td>
                            <td width="10%">End: {{ optional($project->period_end)->format('d M Y') ?: '-' }}</td>
                            <td width="10%">Deadline: {{ optional($project->deadline)->format('d M Y') ?: '-' }}</td>
                            <td width="12%">PIC: {{ $project->pic ?: '-' }}</td>
                            <td width="8%">Step: {{ $totalStepsProject }}</td>
                            <td width="11%">
                                {{ $progressProject }}%
                                <div class="progress-dark-wrap">
                                    <div class="progress-dark-fill" style="width: {{ $progressProject }}%"></div>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div class="steps-label-dark">Step Project</div>
                    @forelse ($project->steps as $step)
                        @php
                            $stepStatusClass = match ($step->status) {
                                'planned' => 'status-planned-pill',
                                'in_progress' => 'status-progress-pill',
                                'completed' => 'status-completed-pill',
                                'delayed' => 'status-delayed-pill',
                                default => 'status-planned-pill',
                            };
                        @endphp
                        <div class="step-card-dark">
                            <table class="step-head-dark">
                                <tr>
                                    <td width="72%" class="step-name-dark">{{ $loop->iteration }}. {{ $step->step_name }}</td>
                                    <td width="28%" style="text-align: right;">
                                        <span class="status-pill {{ $stepStatusClass }}">{{ str_replace('_', ' ', $step->status) }}</span>
                                    </td>
                                </tr>
                            </table>
                            <div class="step-meta-dark">
                                Start: {{ optional($step->start_date)->format('d M Y') ?: '-' }} |
                                End: {{ optional($step->end_date)->format('d M Y') ?: '-' }} |
                                Deadline: {{ optional($step->deadline)->format('d M Y') ?: '-' }}
                            </div>
                        </div>
                    @empty
                        <div class="step-meta-dark">Belum ada step pada project ini.</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    @endif
</div>
</body>
</html>

