@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', $period ? 'University-wide IPCR progress for '.$period->name.'.' : 'University-wide IPCR progress.')

@section('actions')
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="{{ route('hr.queue') }}"><i class="bi bi-inbox"></i> Review Queue
            @if ($waiting)<span class="badge text-bg-light ms-1">{{ $waiting }}</span>@endif</a>
        <a class="btn btn-outline-primary" href="{{ route('hr.reports') }}"><i class="bi bi-bar-chart-line"></i> Reports</a>
    </div>
@endsection

@section('content')
    @if (! $summary)
        <div class="empty-state notice-soft"><i class="bi bi-calendar-x"></i>No rating period yet. <a href="{{ route('hr.periods.index') }}">Create the first rating period</a>.</div>
    @else
    @include('partials.summary-cards')

    @include('partials.overdue')

    <x-card title="Progress by college and office" flush>
        @include('partials.progress-table', ['rows' => $byCollege, 'unitLabel' => 'College / office'])
    </x-card>

    <div class="row g-4">
        <div class="col-lg-6">
            <x-card title="Status breakdown" flush>
                @include('partials.status-breakdown')
            </x-card>
        </div>
        <div class="col-lg-6">
            <x-card title="Templates in use">
                @foreach (['teaching' => 'Teaching', 'nonteaching' => 'Non-teaching'] as $type => $label)
                    @php($active = \App\Models\IpcrTemplate::activeFor($period->id, $type))
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $label }}</span>
                        <span class="fw-semibold">{{ $active ? 'Version '.$active->version.' · published '.$active->published_at->format('M j, Y') : 'Not published' }}</span>
                    </div>
                @endforeach
                <a href="{{ route('hr.templates') }}" class="btn btn-outline-primary mt-3">Manage IPCR Templates</a>
            </x-card>
        </div>
    </div>
    @endif
@endsection
