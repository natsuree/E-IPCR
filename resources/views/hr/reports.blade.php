@extends('layouts.app')
@section('title', 'Reports & Analytics')
@section('subtitle', 'Submission and completion progress, ratings and follow-up lists for '.$analytics->scope->label.' · '.$period->name.'.')

@php
    $route = 'hr.reports';
    $counts = $summary['counts'];
    $exportFilters = $filters;
@endphp

@section('actions')
    <div class="d-flex flex-wrap gap-2 no-print">
        <a class="btn btn-gold" href="{{ route('hr.reports.export', $exportFilters) }}"><i class="bi bi-download"></i> Export CSV</a>
        <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
    </div>
@endsection

@section('content')
    <x-card title="Filters" class="no-print">
        <form method="GET" action="{{ route('hr.reports') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="period" class="form-label small fw-semibold">Rating period</label>
                <select id="period" name="period" class="form-select">
                    @foreach ($periods as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $period->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="college" class="form-label small fw-semibold">College / office</label>
                <select id="college" name="college" class="form-select">
                    <option value="">All colleges and offices</option>
                    @foreach ($colleges as $college)
                        <option value="{{ $college->id }}" @selected(($filters['college'] ?? null) == $college->id)>{{ $college->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="department" class="form-label small fw-semibold">Department</label>
                <select id="department" name="department" class="form-select">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(($filters['department'] ?? null) == $department->id)>{{ $department->name }} ({{ $department->college->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="type" class="form-label small fw-semibold">Employee type</label>
                <select id="type" name="type" class="form-select">
                    <option value="">All</option>
                    <option value="teaching" @selected(($filters['type'] ?? null) === 'teaching')>Teaching</option>
                    <option value="nonteaching" @selected(($filters['type'] ?? null) === 'nonteaching')>Non-teaching</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    @foreach (\App\Services\IpcrAnalytics::STATUS_LABELS as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="q" class="form-label small fw-semibold">Employee</label>
                <input id="q" name="q" class="form-control" placeholder="Name or ID" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Apply</button>
                <a href="{{ route('hr.reports') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </x-card>

    <div class="row g-3 mb-2">
        <div class="col-6 col-lg"><x-stat label="Employees" :value="$summary['employees']" /></div>
        <div class="col-6 col-lg"><x-stat label="Submitted" :value="$summary['submitted']" :hint="$analytics->percent($summary['submitted']).'%'" /></div>
        <div class="col-6 col-lg"><x-stat label="Completed" :value="$summary['completed']" :hint="$analytics->percent($summary['completed']).'%'" /></div>
        <div class="col-6 col-lg"><x-stat label="Has not submitted" :value="$summary['not_submitted']" :hint="$period->submission_deadline ? 'Deadline '.$period->submission_deadline->format('M j, Y') : null" /></div>
        <div class="col-6 col-lg"><x-stat label="Average final rating" :small="$summary['average'] === null"
            :value="$summary['average'] === null ? 'No completed IPCRs yet' : number_format($summary['average'], 2)" :hint="$summary['adjectival'] ?? 'Completed IPCRs only'" /></div>
    </div>
    <p class="small text-secondary mb-4">Percentages are out of all employees in the filters, not just those who submitted. The status and employee search filters narrow the employee table only.</p>

    <x-card title="Progress by college and office" flush>
        @include('partials.progress-table', ['rows' => $byCollege, 'unitLabel' => 'College / office'])
    </x-card>
    <x-card title="Progress by department" flush>
        @include('partials.progress-table', ['rows' => $byDepartment, 'unitLabel' => 'College / office · department', 'showCollege' => true])
    </x-card>

    <div class="row g-4">
        <div class="col-lg-6">
            <x-card title="Status distribution">
                @include('partials.bar-chart', ['id' => 'statusChart', 'title' => 'Employees', 'axis' => 'Status',
                    'labels' => array_values(\App\Services\IpcrAnalytics::STATUS_LABELS), 'values' => array_values($counts)])
            </x-card>
        </div>
        <div class="col-lg-6">
            <x-card title="Final rating distribution">
                @if (array_sum($ratings) === 0)
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>No completed IPCRs for these filters yet.</div>
                @else
                    @include('partials.bar-chart', ['id' => 'ratingChart', 'title' => 'Completed IPCRs', 'axis' => 'Adjectival rating',
                        'labels' => array_keys($ratings), 'values' => array_values($ratings)])
                @endif
            </x-card>
        </div>
        <div class="col-lg-6">
            <x-card title="Average final rating by rating period">
                @if ($trends->isEmpty())
                    <div class="empty-state"><i class="bi bi-graph-up"></i>No IPCRs yet.</div>
                @else
                    @include('partials.line-chart', ['id' => 'trendRating', 'title' => 'Average final rating', 'axis' => 'Rating period',
                        'labels' => $trends->pluck('period')->all(), 'values' => $trends->pluck('average')->all(), 'max' => 5, 'decimals' => 2])
                @endif
            </x-card>
        </div>
        <div class="col-lg-6">
            <x-card title="Completion rate by rating period">
                @if ($trends->isEmpty())
                    <div class="empty-state"><i class="bi bi-graph-up"></i>No IPCRs yet.</div>
                @else
                    @include('partials.line-chart', ['id' => 'trendCompletion', 'title' => 'Completed', 'axis' => 'Rating period',
                        'labels' => $trends->pluck('period')->all(), 'values' => $trends->pluck('completion_rate')->all(), 'max' => 100, 'decimals' => 1, 'suffix' => '%'])
                @endif
            </x-card>
        </div>
    </div>

    <x-card title="Has not submitted ({{ $notSubmitted->total() }})" flush id="not-submitted">
        @if ($notSubmitted->isEmpty())
            <div class="empty-state"><i class="bi bi-check2-all"></i>Every employee in these filters has submitted.</div>
        @else
            <div class="table-responsive">
                <table class="table table-eipcr">
                    <thead><tr><th>Employee / type</th><th>College / office · department</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($notSubmitted as $row)
                        <tr>
                            <td>{{ $row->name }}<div class="small text-secondary">{{ $row->employeeTypeLabel() }}</div></td>
                            <td>{{ $row->college_name }}@if ($row->department_name) · {{ $row->department_name }}@endif</td>
                            <td><x-status :status="$row->ipcr_status ?? 'not_started'" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer no-print">{{ $notSubmitted->links() }}</div>
        @endif
    </x-card>

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
                        <th>College / office · @include('partials.sort-link', ['key' => 'department', 'label' => 'department'])</th>
                        <th>@include('partials.sort-link', ['key' => 'status', 'label' => 'Status'])</th>
                        <th>Current reviewer</th>
                        <th>@include('partials.sort-link', ['key' => 'submitted', 'label' => 'Submitted on'])</th>
                        <th class="text-end">@include('partials.sort-link', ['key' => 'rating', 'label' => 'Final rating'])</th>
                        <th>Adjectival rating</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($table as $row)
                        <tr>
                            <td>
                                @if ($row->ipcr_id && $row->ipcr_status !== 'draft')
                                    <a href="{{ route('hr.ipcrs.show', $row->ipcr_id) }}" class="fw-semibold">{{ $row->name }}</a>
                                @else
                                    <span class="fw-semibold">{{ $row->name }}</span>
                                @endif
                            </td>
                            <td>{{ $row->employeeTypeLabel() }}</td>
                            <td>{{ $row->college_name }}@if ($row->department_name) · {{ $row->department_name }}@endif</td>
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
        @endif
    </x-card>
@endsection
