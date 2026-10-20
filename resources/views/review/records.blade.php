@extends('layouts.app')
@section('title', 'IPCR Records')
@section('subtitle', 'Every completed IPCR within your scope, from all rating periods. Records are read-only.')

@section('content')
    @php($prefix = auth()->user()->routePrefix())
    <x-card title="Filters">
        <form method="GET" action="{{ route($prefix.'.records') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="q" class="form-label small fw-semibold">Employee name or ID</label>
                <input id="q" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label for="period" class="form-label small fw-semibold">Rating period</label>
                <select id="period" name="period" class="form-select">
                    <option value="">All rating periods</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}" @selected(($filters['period'] ?? null) == $period->id)>{{ $period->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="college" class="form-label small fw-semibold">College / office</label>
                <select id="college" name="college" class="form-select" @disabled($lockCollege)>
                    @unless ($lockCollege)<option value="">All</option>@endunless
                    @foreach ($colleges as $college)
                        <option value="{{ $college->id }}" @selected(($filters['college'] ?? null) == $college->id)>{{ $college->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="department" class="form-label small fw-semibold">Department</label>
                <select id="department" name="department" class="form-select" @disabled($lockDepartment)>
                    @unless ($lockDepartment)<option value="">All</option>@endunless
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected(($filters['department'] ?? null) == $department->id)>{{ $department->name }}@unless ($lockCollege) ({{ $department->college->code }})@endunless</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Apply</button>
                <a href="{{ route($prefix.'.records') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
            @if ($lockCollege)
                <div class="col-12 small text-secondary"><i class="bi bi-lock"></i> {{ $lockDepartment ? 'College and department are' : 'College is' }} fixed to your own unit.</div>
            @endif
        </form>
    </x-card>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><x-stat label="Records" :value="$summary['records']" /></div>
        <div class="col-md-4"><x-stat label="Rating periods" :value="$summary['periods']" /></div>
        <div class="col-md-4"><x-stat label="Average final rating" :value="$summary['average'] === null ? '—' : number_format((float) $summary['average'], 2)"
                                      :hint="$summary['average'] === null ? null : \App\Services\FinalRating::adjectival(round((float) $summary['average'], 2))" /></div>
    </div>

    <x-card title="Completed IPCRs" flush>
        @if ($records->isEmpty())
            <div class="empty-state"><i class="bi bi-archive"></i>No completed IPCRs match these filters.</div>
        @else
            <div class="table-responsive">
                <table class="table table-eipcr table-hover">
                    <thead><tr><th>Employee / type</th><th>College / office · department</th><th>Rating period</th><th class="text-end">Final rating</th><th>Adjectival rating</th><th>Approved on</th><th class="text-end">View</th></tr></thead>
                    <tbody>
                    @foreach ($records as $record)
                        <tr>
                            <td><span class="fw-semibold">{{ $record->user->name }}</span><div class="small text-secondary">{{ $record->user->employeeTypeLabel() }}</div></td>
                            <td>{{ $record->user->unitLabel() }}</td>
                            <td>{{ $record->period->name }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $record->final_rating, 2) }}</td>
                            <td>{{ $record->adjectival_rating }}</td>
                            <td class="text-nowrap">{{ $record->completed_at?->format('M j, Y') }}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route($prefix.'.records.show', $record) }}">View</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route($prefix.'.records.pdf', $record) }}" aria-label="Download PDF of {{ $record->user->name }}'s IPCR"><i class="bi bi-file-earmark-pdf"></i></a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    {{ $records->links() }}
@endsection
