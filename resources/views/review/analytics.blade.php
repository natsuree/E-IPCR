@extends('layouts.app')
@section('title', 'Analytics')
@section('subtitle', 'IPCR progress, ratings and review performance for '.$analytics->scope->label.($period ? ' · '.$period->name : '').'.')

@php
    $prefix = auth()->user()->routePrefix();
    $route = $prefix.'.analytics';
    $counts = $summary['counts'];
    $pct = fn (int $n) => $analytics->percent($n);
    $exportFilters = array_diff_key($filters, array_flip(['page', 'missing_page']));
@endphp

@section('actions')
    <div class="d-flex flex-wrap gap-2 no-print">
        <a class="btn btn-gold" href="{{ route($prefix.'.analytics.export', $exportFilters) }}"><i class="bi bi-download"></i> Export CSV</a>
        <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
    </div>
@endsection

@section('content')
    {{-- Filters --}}
    <x-card title="Filters" class="no-print">
        <form method="GET" action="{{ route($route) }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="period" class="form-label small fw-semibold">Rating period</label>
                <select id="period" name="period" class="form-select">
                    @foreach ($periods as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $period?->id)>{{ $option->name }}{{ $option->isOpen() ? ' (open)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label small fw-semibold">Employee type</label>
                <select id="type" name="type" class="form-select">
                    <option value="">All</option>
                    <option value="teaching" @selected(($filters['type'] ?? null) === 'teaching')>Teaching</option>
                    <option value="nonteaching" @selected(($filters['type'] ?? null) === 'nonteaching')>Non-teaching</option>
                </select>
            </div>
            @if ($isDean)
                <div class="col-md-3">
                    <label for="department" class="form-label small fw-semibold">Department</label>
                    <select id="department" name="department" class="form-select">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($filters['department'] ?? null) == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    @foreach (\App\Services\IpcrAnalytics::STATUS_LABELS as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="q" class="form-label small fw-semibold">Employee</label>
                <input id="q" name="q" class="form-control" placeholder="Name or ID" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary">Apply filters</button>
                <a href="{{ route($route) }}" class="btn btn-outline-secondary">Clear</a>
                <span class="small text-secondary align-self-center ms-2">The address bar keeps these filters, so you can bookmark or share this view with someone in the same unit.</span>
            </div>
        </form>
    </x-card>

    @if (! $period)
        <div class="empty-state notice-soft"><i class="bi bi-calendar-x"></i>No rating period has been set up yet.</div>
    @else
        {{-- 1. Summary --}}
        <div class="row g-3 mb-2">
            <div class="col-6 col-md-4 col-xl-2"><x-stat label="Employees" :value="$summary['employees']" :hint="$analytics->scope->label" /></div>
            <div class="col-6 col-md-4 col-xl-2"><x-stat label="Submitted" :value="$summary['submitted']" :hint="$pct($summary['submitted']).'% of employees'" /></div>
            <div class="col-6 col-md-4 col-xl-2"><x-stat label="Completed" :value="$summary['completed']" :hint="$pct($summary['completed']).'% of employees'" /></div>
            <div class="col-6 col-md-4 col-xl-2"><x-stat label="Returned for correction" :value="$summary['returned']" hint="Back with the employee" /></div>
            <div class="col-6 col-md-4 col-xl-2"><x-stat label="Not yet submitted" :value="$summary['not_submitted']" :hint="$pct($summary['not_submitted']).'% · not started or draft'" /></div>
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat label="Average final rating" :small="$summary['average'] === null"
                        :value="$summary['average'] === null ? 'No completed IPCRs yet' : number_format($summary['average'], 2)"
                        :hint="$summary['adjectival'] ?? 'Completed IPCRs only'" />
            </div>
        </div>
        <p class="small text-secondary mb-4">
            Percentages are out of all {{ $summary['employees'] }} employees in {{ $analytics->scope->label }}{{ ($filters['type'] ?? null) ? ' ('.($filters['type'] === 'teaching' ? 'Teaching' : 'Non-teaching').')' : '' }}, not just those who submitted.
            The status and employee search filters narrow the tables below; they do not change these cards.
            Average ratings count completed IPCRs only and use the rating stored when HR approved each one.
        </p>

        {{-- 2. Submission and status --}}
        <div class="row g-4">
            <div class="col-xl-8">
                <x-card title="Submission progress · {{ $period->name }}">
                    @php
                        $segments = [
                            ['Not started', $counts['not_started'], '#c9d2cb'],
                            ['Draft', $counts['draft'], '#9fb0a3'],
                            ['Returned', $counts['returned'], '#e0a526'],
                            ['Submitted', $counts['submitted'], '#6fa275'],
                            ['In review', $summary['in_review'], '#3d8445'],
                            ['Completed', $counts['completed'], '#104D03'],
                        ];
                    @endphp
                    <div class="progress-stacked mb-2" style="height: 1.4rem">
                        @foreach ($segments as [$label, $n, $color])
                            @if ($n > 0)
                                <div class="progress" role="progressbar" aria-label="{{ $label }}" aria-valuenow="{{ $pct($n) }}" aria-valuemin="0" aria-valuemax="100" style="width: {{ $summary['employees'] ? $n / $summary['employees'] * 100 : 0 }}%; height: 1.4rem">
                                    <div class="progress-bar" style="background: {{ $color }}">{{ $pct($n) >= 8 ? $n : '' }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap gap-3 small">
                        @foreach ($segments as [$label, $n, $color])
                            <span><span class="legend-swatch" style="background: {{ $color }}"></span> {{ $label }}: <strong>{{ $n }}</strong> ({{ $pct($n) }}%)</span>
                        @endforeach
                    </div>
                    @if (! $isDean)
                        <p class="small text-secondary mt-2 mb-0"><i class="bi bi-arrow-right-circle"></i> {{ $summary['passed_head'] }} IPCR(s) have passed your step (with the Dean/Director, with HR or completed).</p>
                    @endif
                </x-card>
            </div>
            <div class="col-xl-4">
                <x-card title="Submission deadline">
                    @if ($daysToDeadline === null)
                        <p class="mb-0 text-secondary">No deadline is set for {{ $period->name }}.</p>
                    @else
                        <div class="stat-value">{{ abs($daysToDeadline) }} {{ abs($daysToDeadline) === 1 ? 'day' : 'days' }}</div>
                        <p class="mb-1">
                            @if ($daysToDeadline > 0) until the deadline
                            @elseif ($daysToDeadline === 0) The deadline is today
                            @else overdue
                            @endif
                            ({{ $period->submission_deadline->format('F j, Y') }}).
                        </p>
                        @if ($daysToDeadline < 0 && $summary['not_submitted'] > 0)
                            <p class="small text-danger mb-0"><i class="bi bi-exclamation-triangle"></i> {{ $summary['not_submitted'] }} employee(s) have not submitted.</p>
                        @endif
                    @endif
                </x-card>
            </div>
            <div class="col-lg-6">
                <x-card title="Status distribution">
                    @include('partials.bar-chart', ['id' => 'statusChart', 'title' => 'Employees', 'axis' => 'Status',
                        'labels' => array_values(\App\Services\IpcrAnalytics::STATUS_LABELS), 'values' => array_values($counts)])
                </x-card>
            </div>

            {{-- 3. Ratings --}}
            <div class="col-lg-6">
                <x-card title="Final rating distribution">
                    @if (array_sum($ratings) === 0)
                        <div class="empty-state"><i class="bi bi-bar-chart"></i>No completed IPCRs yet for {{ $period->name }}.</div>
                    @else
                        @include('partials.bar-chart', ['id' => 'ratingChart', 'title' => 'Completed IPCRs', 'axis' => 'Adjectival rating',
                            'labels' => array_keys($ratings), 'values' => array_values($ratings)])
                    @endif
                </x-card>
            </div>
            <div class="col-lg-6">
                <x-card title="Average rating per section">
                    @if (count(array_filter($sections, fn ($v) => $v !== null)) === 0)
                        <div class="empty-state"><i class="bi bi-bar-chart"></i>No completed IPCRs yet for {{ $period->name }}.</div>
                    @else
                        @include('partials.bar-chart', ['id' => 'sectionChart', 'title' => 'Average rating', 'axis' => 'Section',
                            'labels' => array_values(\App\Models\Ipcr::SECTIONS), 'values' => array_values($sections), 'max' => 5, 'decimals' => 2])
                    @endif
                </x-card>
            </div>
            <div class="col-lg-6">
                <x-card title="Five lowest-rated indicators" flush>
                    @if ($lowest->isEmpty())
                        <div class="empty-state"><i class="bi bi-list-ol"></i>No completed IPCRs yet for {{ $period->name }}.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-eipcr">
                                <thead><tr><th>Indicator</th><th>Section</th><th class="text-end">Average</th><th class="text-end">Ratings</th></tr></thead>
                                <tbody>
                                @foreach ($lowest as $row)
                                    <tr>
                                        <td class="small">{{ $row->indicator }}</td>
                                        <td class="small text-nowrap">{{ $row->section }}</td>
                                        <td class="text-end fw-semibold">{{ number_format($row->average, 2) }}</td>
                                        <td class="text-end">{{ $row->ratings }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="small text-secondary px-3 py-2 mb-0">Where employees in your scope scored lowest, from completed IPCRs in {{ $period->name }}.</p>
                    @endif
                </x-card>
            </div>

            {{-- 4. Trends --}}
            <div class="col-lg-6">
                <x-card title="Average final rating by rating period">
                    @if ($trends->isEmpty())
                        <div class="empty-state"><i class="bi bi-graph-up"></i>No IPCRs in your scope yet.</div>
                    @else
                        @include('partials.line-chart', ['id' => 'trendRating', 'title' => 'Average final rating', 'axis' => 'Rating period',
                            'labels' => $trends->pluck('period')->all(), 'values' => $trends->pluck('average')->all(), 'max' => 5, 'decimals' => 2])
                    @endif
                </x-card>
            </div>
            <div class="col-lg-6">
                <x-card title="Completion rate by rating period">
                    @if ($trends->isEmpty())
                        <div class="empty-state"><i class="bi bi-graph-up"></i>No IPCRs in your scope yet.</div>
                    @else
                        @include('partials.line-chart', ['id' => 'trendCompletion', 'title' => 'Completed', 'axis' => 'Rating period',
                            'labels' => $trends->pluck('period')->all(), 'values' => $trends->pluck('completion_rate')->all(), 'max' => 100, 'decimals' => 1, 'suffix' => '%'])
                        <p class="small text-secondary mb-0 mt-1">Completed IPCRs ÷ employees in the period. Up to the last six periods with data.</p>
                    @endif
                </x-card>
            </div>

            {{-- 5. Review performance --}}
            <div class="col-12">
                <x-card title="Your review step ({{ auth()->user()->role->label() }})">
                    <div class="row g-3">
                        <div class="col-6 col-lg-3"><x-stat label="Waiting now" :value="$performance['waiting']"
                            :hint="$performance['oldest_days'] === null ? 'Nothing waiting' : 'Oldest: '.$performance['oldest_days'].' day(s)'" /></div>
                        <div class="col-6 col-lg-3"><x-stat label="Average days at your step" :small="$performance['average_days'] === null"
                            :value="$performance['average_days'] === null ? 'No decisions yet' : number_format($performance['average_days'], 1)" :hint="'Before you approved or returned · '.$period->name" /></div>
                        <div class="col-6 col-lg-3"><x-stat label="Approved" :value="$performance['approved']" :hint="$period->name" /></div>
                        <div class="col-6 col-lg-3"><x-stat label="Returned" :value="$performance['returned']" :hint="$period->name" /></div>
                    </div>
                    <h3 class="h6 fw-semibold mt-4">Latest return remarks</h3>
                    @if ($performance['return_remarks']->isEmpty())
                        <p class="small text-secondary mb-0">You have not returned any IPCRs in {{ $period->name }}.</p>
                    @else
                        <ul class="list-group">
                            @foreach ($performance['return_remarks'] as $remark)
                                <li class="list-group-item small">
                                    <span class="text-secondary">{{ \Illuminate\Support\Carbon::parse($remark->created_at)->format('M j, Y') }} · {{ $remark->employee }}:</span>
                                    {{ $remark->remarks }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($performance['waiting'] > 0)
                        <a href="{{ route($prefix.'.queue') }}" class="btn btn-outline-primary btn-sm mt-3 no-print">Open Review Queue</a>
                    @endif
                </x-card>
            </div>
        </div>

        {{-- 6. Department comparison (Dean/Director) --}}
        @if ($byDepartment->isNotEmpty())
            <x-card title="Department comparison · {{ $period->name }}" flush>
                <div class="table-responsive">
                    <table class="table table-eipcr table-hover">
                        <thead>
                        <tr>
                            <th>Department</th><th class="text-end">Employees</th><th class="text-end">Not started</th><th class="text-end">Draft</th>
                            <th class="text-end">Submitted</th><th class="text-end">Returned</th><th class="text-end">In review</th><th class="text-end">Completed</th>
                            <th class="text-end">Completion %</th><th class="text-end">Average rating</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($byDepartment as $row)
                            <tr>
                                <td>
                                    @if ($row['id'])
                                        <a href="{{ route($route, array_merge($exportFilters, ['department' => $row['id']])) }}">{{ $row['name'] }}</a>
                                    @else
                                        {{ $row['name'] }}
                                    @endif
                                </td>
                                <td class="text-end">{{ $row['employees'] }}</td>
                                <td class="text-end">{{ $row['counts']['not_started'] }}</td>
                                <td class="text-end">{{ $row['counts']['draft'] }}</td>
                                <td class="text-end">{{ $row['counts']['submitted'] }}</td>
                                <td class="text-end">{{ $row['counts']['returned'] }}</td>
                                <td class="text-end">{{ $row['in_review'] }}</td>
                                <td class="text-end">{{ $row['completed'] }}</td>
                                <td class="text-end">{{ $row['completed_pct'] }}%</td>
                                <td class="text-end">{{ $row['average'] === null ? '—' : number_format($row['average'], 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-secondary px-3 py-2 mb-0">Click a department to see its analytics. "Submitted" is waiting at the Department Head.</p>
            </x-card>
        @endif

        {{-- Employee table --}}
        <x-card title="Employees ({{ $table->total() }})" flush id="employees">
            @if ($table->isEmpty())
                <div class="empty-state"><i class="bi bi-people"></i>No employees match these filters.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-eipcr table-hover">
                        <thead>
                        <tr>
                            <th>@include('partials.sort-link', ['key' => 'name', 'label' => 'Employee'])</th>
                            <th>Type</th>
                            <th>@include('partials.sort-link', ['key' => 'department', 'label' => 'Department'])</th>
                            <th>@include('partials.sort-link', ['key' => 'status', 'label' => 'Status'])</th>
                            <th>Current reviewer</th>
                            <th>@include('partials.sort-link', ['key' => 'submitted', 'label' => 'Submitted on'])</th>
                            <th class="text-end">@include('partials.sort-link', ['key' => 'rating', 'label' => 'Final rating'])</th>
                            <th>Adjectival rating</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($table as $row)
                            @php($viewable = $row->ipcr_id && $row->ipcr_status !== 'draft')
                            <tr>
                                <td>
                                    @if ($viewable)
                                        <a href="{{ route($prefix.'.ipcrs.show', $row->ipcr_id) }}" class="fw-semibold">{{ $row->name }}</a>
                                    @else
                                        <span class="fw-semibold">{{ $row->name }}</span>
                                    @endif
                                    @if ($row->employee_id)<div class="small text-secondary">{{ $row->employee_id }}</div>@endif
                                </td>
                                <td>{{ $row->employeeTypeLabel() }}</td>
                                <td>{{ $row->department_name ?? '—' }}</td>
                                <td><x-status :status="$row->ipcr_status ?? 'not_started'" /></td>
                                <td>{{ \App\Services\IpcrAnalytics::currentReviewer($row->ipcr_status) }}</td>
                                <td class="text-nowrap">{{ $row->ipcr_submitted_at ? \Illuminate\Support\Carbon::parse($row->ipcr_submitted_at)->format('M j, Y') : '—' }}</td>
                                <td class="text-end">{{ $row->ipcr_final_rating !== null ? number_format((float) $row->ipcr_final_rating, 2) : '—' }}</td>
                                <td>{{ $row->ipcr_adjectival_rating ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer no-print">{{ $table->links() }}</div>
                <p class="small text-secondary px-3 pb-2 mb-0">Click a name to open that IPCR (read-only). Drafts stay private to the employee until they submit.</p>
            @endif
        </x-card>

        {{-- Not yet submitted --}}
        <x-card title="Not yet submitted ({{ $notSubmitted->total() }})" flush id="not-submitted">
            @if ($notSubmitted->isEmpty())
                <div class="empty-state"><i class="bi bi-check2-all"></i>Every employee in this view has submitted.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-eipcr">
                        <thead><tr><th>Employee</th><th>Type</th><th>Department</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($notSubmitted as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->employeeTypeLabel() }}</td>
                                <td>{{ $row->department_name ?? '—' }}</td>
                                <td><x-status :status="$row->ipcr_status ?? 'not_started'" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer no-print">{{ $notSubmitted->links() }}</div>
            @endif
        </x-card>
    @endif
@endsection
