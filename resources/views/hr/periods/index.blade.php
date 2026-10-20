@extends('layouts.app')
@section('title', 'Rating Periods')
@section('subtitle', 'Create rating periods, set the submission deadline, and open or close them. Only one period can be open at a time.')

@section('content')
    <div class="row g-4">
        <div class="col-xl-8">
            <x-card title="Rating periods" flush>
                @if ($periods->isEmpty())
                    <div class="empty-state"><i class="bi bi-calendar3"></i>No rating periods yet. Create the first one.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-eipcr">
                            <thead><tr><th>Rating period</th><th>Dates</th><th>Deadline</th><th>Status</th><th class="text-end">IPCRs</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            @foreach ($periods as $period)
                                <tr>
                                    <td class="fw-semibold">{{ $period->name }}</td>
                                    <td class="text-nowrap small">{{ $period->starts_on->format('M j, Y') }} – {{ $period->ends_on->format('M j, Y') }}</td>
                                    <td class="text-nowrap small">
                                        {{ $period->submission_deadline?->format('M j, Y') ?? '—' }}
                                        @if ($period->isOpen() && $period->deadlinePassed())<span class="badge badge-status badge-status-returned">Passed</span>@endif
                                    </td>
                                    <td>
                                        <span @class(['badge badge-status', 'badge-status-completed' => $period->isOpen(), 'badge-status-draft' => ! $period->isOpen()])>{{ $period->statusLabel() }}</span>
                                    </td>
                                    <td class="text-end small">{{ $period->ipcrs_count }} <span class="text-secondary">({{ $period->completed_count }} completed)</span></td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('hr.periods.index', ['edit' => $period->id]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="{{ route('hr.templates', ['period' => $period->id]) }}" class="btn btn-sm btn-outline-primary">Templates</a>
                                        @if ($period->isOpen())
                                            <form method="POST" action="{{ route('hr.periods.close', $period) }}" class="d-inline" onsubmit="return confirm('Close {{ $period->name }}? Employees will not be able to create new IPCRs for it.')">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-danger">Close</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('hr.periods.open', $period) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-primary">Open</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
            {{ $periods->links() }}
        </div>

        <div class="col-xl-4">
            <x-card :title="$editing ? 'Edit '.$editing->name : 'New rating period'">
                <form method="POST" action="{{ $editing ? route('hr.periods.update', $editing) : route('hr.periods.store') }}" novalidate>
                    @csrf
                    @if ($editing) @method('PUT') @endif
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Name</label>
                        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="January – June 2027" value="{{ old('name', $editing?->name) }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="starts_on" class="form-label fw-semibold">Start date</label>
                            <input id="starts_on" name="starts_on" type="date" class="form-control @error('starts_on') is-invalid @enderror" value="{{ old('starts_on', $editing?->starts_on?->format('Y-m-d')) }}">
                            @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label for="ends_on" class="form-label fw-semibold">End date</label>
                            <input id="ends_on" name="ends_on" type="date" class="form-control @error('ends_on') is-invalid @enderror" value="{{ old('ends_on', $editing?->ends_on?->format('Y-m-d')) }}">
                            @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="submission_deadline" class="form-label fw-semibold">Submission deadline</label>
                        <input id="submission_deadline" name="submission_deadline" type="date" class="form-control @error('submission_deadline') is-invalid @enderror" value="{{ old('submission_deadline', $editing?->submission_deadline?->format('Y-m-d')) }}">
                        @error('submission_deadline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">After this date, dashboards flag employees who have not submitted.</div>
                    </div>
                    <div class="d-flex gap-2 justify-content-end">
                        @if ($editing)<a href="{{ route('hr.periods.index') }}" class="btn btn-outline-secondary">Cancel</a>@endif
                        <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create rating period' }}</button>
                    </div>
                </form>
            </x-card>
            <div class="notice-soft small">
                <strong>Steps for a new period:</strong> create it here, publish its Teaching and Non-teaching templates in
                <a href="{{ route('hr.templates') }}">IPCR Templates</a> (start a draft copied from the previous period), close the old period, then open the new one.
            </div>
        </div>
    </div>
@endsection
