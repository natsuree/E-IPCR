@extends('layouts.app')
@section('title', 'Audit Log')
@section('subtitle', 'A read-only record of sign-ins, account changes, IPCR actions, template changes and settings changes.')

@section('content')
    <x-card title="Search">
        <form method="GET" action="{{ route('admin.audit') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="q" class="form-label small fw-semibold">Text or person</label>
                <input id="q" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label for="group" class="form-label small fw-semibold">Type</label>
                <select id="group" name="group" class="form-select">
                    <option value="">All events</option>
                    @foreach (\App\Models\AuditLog::EVENT_GROUPS as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['group'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label small fw-semibold">From</label>
                <input id="from" name="from" type="date" class="form-control @error('from') is-invalid @enderror" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label small fw-semibold">To</label>
                <input id="to" name="to" type="date" class="form-control @error('to') is-invalid @enderror" value="{{ $filters['to'] ?? '' }}">
                @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button class="btn btn-primary w-100">Go</button>
            </div>
        </form>
    </x-card>

    <x-card title="Events ({{ $logs->total() }})" flush>
        @if ($logs->isEmpty())
            <div class="empty-state"><i class="bi bi-journal-text"></i>No events match this search.</div>
        @else
            <div class="table-responsive">
                <table class="table table-eipcr">
                    <thead><tr><th>Date</th><th>Person</th><th>Type</th><th>What happened</th><th>IP address</th></tr></thead>
                    <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="text-nowrap small">{{ $log->created_at->format('M j, Y g:i:s A') }}</td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td class="small text-nowrap">{{ $log->groupLabel() }}</td>
                            <td class="small">{{ $log->description }}</td>
                            <td class="small text-secondary">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    {{ $logs->links() }}
@endsection
