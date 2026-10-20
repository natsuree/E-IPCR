@extends('layouts.app')
@section('title', 'Review Queue')
@section('subtitle', $grouped !== null
    ? 'IPCRs waiting for final HR approval, grouped by college/office, then department, then employee.'
    : 'IPCRs waiting at your step, from employees in your unit.')

@section('content')
    @php($prefix = auth()->user()->routePrefix())
    <x-card title="Search">
        <form method="GET" action="{{ route($prefix.'.queue') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="q" class="form-label small fw-semibold">Employee name or ID</label>
                <input id="q" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}">
            </div>
            @if ($colleges->isNotEmpty())
                <div class="col-md-3">
                    <label for="college" class="form-label small fw-semibold">College / office</label>
                    <select id="college" name="college" class="form-select">
                        <option value="">All colleges and offices</option>
                        @foreach ($colleges as $college)
                            <option value="{{ $college->id }}" @selected(($filters['college'] ?? null) == $college->id)>{{ $college->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($departments->isNotEmpty())
                <div class="col-md-3">
                    <label for="department" class="form-label small fw-semibold">Department</label>
                    <select id="department" name="department" class="form-select">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($filters['department'] ?? null) == $department->id)>{{ $department->name }}@if ($department->relationLoaded('college')) ({{ $department->college->code }})@endif</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Search</button>
                <a href="{{ route($prefix.'.queue') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </x-card>

    @if ($ipcrs->isEmpty())
        <x-card title="Waiting for your review">
            <div class="empty-state"><i class="bi bi-inbox"></i>{{ $filters ? 'No IPCRs waiting for your review match this search.' : 'No IPCRs are waiting for your review.' }}</div>
        </x-card>
    @elseif ($grouped !== null)
        @foreach ($grouped as $college => $departments)
            <x-card :title="$college" flush>
                <x-slot:actions><span class="badge text-bg-light">{{ $departments->flatten()->count() }} on this page</span></x-slot:actions>
                <div class="table-responsive">
                    <table class="table table-eipcr">
                        <thead><tr><th>Department</th><th>Employee</th><th>Rating period</th><th>Received</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                        @foreach ($departments as $department => $items)
                            <tr class="group-row"><td colspan="5">{{ $department }}</td></tr>
                            @foreach ($items as $ipcr)
                                @include('review.partials.queue-row', ['showDept' => false])
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach
    @else
        <x-card title="Waiting for your review ({{ $ipcrs->total() }})" flush>
            <div class="table-responsive">
                <table class="table table-eipcr">
                    <thead><tr><th>Department</th><th>Employee</th><th>Rating period</th><th>Received</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    @foreach ($ipcrs as $ipcr)
                        @include('review.partials.queue-row', ['showDept' => true])
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
    {{ $ipcrs->links() }}
@endsection
