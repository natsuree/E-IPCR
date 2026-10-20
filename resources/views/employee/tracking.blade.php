@extends('layouts.app')
@section('title', 'Status Tracking')
@section('subtitle', 'Where your IPCR is in the approval route, and everything that has happened to it.')

@section('actions')
    @if ($ipcrs->count() > 1)
        <form method="GET" action="{{ route('employee.tracking') }}" class="d-flex gap-2 align-items-center">
            <label for="ipcr" class="small text-secondary text-nowrap">Rating period</label>
            <select id="ipcr" name="ipcr" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($ipcrs as $option)
                    <option value="{{ $option->id }}" @selected($ipcr?->id === $option->id)>{{ $option->period->name }}</option>
                @endforeach
            </select>
            <noscript><button class="btn btn-sm btn-primary">Show</button></noscript>
        </form>
    @endif
@endsection

@section('content')
    @if (! $ipcr)
        <div class="notice-soft">You have no IPCR to track yet. <a href="{{ route('employee.ipcrs.current') }}">Create your IPCR</a> first.</div>
    @else
        <x-card title="{{ $ipcr->period->name }}">
            <x-slot:actions>
                <x-status :status="$ipcr->status" />
            </x-slot:actions>
            @include('ipcr.partials.timeline')
            @if ($ipcr->status->value === 'completed')
                <div class="notice-soft mt-3">
                    <i class="bi bi-patch-check"></i> Completed on {{ $ipcr->completed_at?->format('M j, Y') }}.
                    Final rating: <strong>{{ number_format((float) $ipcr->final_rating, 2) }} · {{ $ipcr->adjectival_rating }}</strong>
                </div>
            @endif
        </x-card>

        @if ($returned)
            <div class="notice-returned mb-4">
                <div class="fw-semibold mb-1"><i class="bi bi-arrow-return-left"></i> Returned by {{ $returned->user?->name }} ({{ $returned->role->label() }}) on {{ $returned->created_at->format('M j, Y g:i A') }}</div>
                <div class="mb-3" style="white-space: pre-line">{{ $returned->remarks }}</div>
                <a href="{{ route('employee.ipcrs.edit', $ipcr) }}" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Revise and resubmit</a>
            </div>
        @elseif ($ipcr->status->value === 'draft')
            <div class="notice-soft mb-4">This IPCR is still a draft. <a href="{{ route('employee.ipcrs.edit', $ipcr) }}">Finish and submit it</a>.</div>
        @endif

        @include('ipcr.partials.history')

        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="{{ route('employee.ipcrs.show', $ipcr) }}">View IPCR</a>
            <a class="btn btn-outline-primary" href="{{ route('employee.ipcrs.print', $ipcr) }}"><i class="bi bi-printer"></i> Print preview</a>
        </div>
    @endif
@endsection
