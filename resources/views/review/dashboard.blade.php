@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', $period ? 'IPCR progress for '.$scopeLabel.' in '.$period->name.'.' : 'IPCR progress for '.$scopeLabel.'.')

@section('actions')
    @php($prefix = auth()->user()->routePrefix())
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="{{ route($prefix.'.queue') }}"><i class="bi bi-inbox"></i> Review Queue
            @if ($waiting)<span class="badge text-bg-light ms-1">{{ $waiting }}</span>@endif</a>
        <a class="btn btn-outline-primary" href="{{ route($prefix.'.analytics') }}"><i class="bi bi-bar-chart-line"></i> View full analytics</a>
    </div>
@endsection

@section('content')
    @if (! $summary)
        <div class="empty-state notice-soft"><i class="bi bi-calendar-x"></i>No rating period has been set up yet.</div>
    @else
        @include('partials.summary-cards')
        @include('partials.overdue')

        <x-card title="Waiting for your review">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="stat-value">{{ $waiting }}</div>
                <div class="text-secondary me-auto">IPCR{{ $waiting === 1 ? ' is' : 's are' }} at the {{ auth()->user()->role->label() }} step in your unit.</div>
                <a href="{{ route(auth()->user()->routePrefix().'.queue') }}" class="btn btn-outline-primary">Open Review Queue</a>
                <a href="{{ route(auth()->user()->routePrefix().'.analytics') }}" class="btn btn-primary">View full analytics</a>
            </div>
        </x-card>
    @endif
@endsection
