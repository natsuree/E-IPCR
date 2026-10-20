@extends('layouts.app')
@section('title', 'Create / Edit IPCR')
@section('subtitle', 'Start your IPCR for the open rating period.')

@section('content')
    @if (! $period)
        <div class="empty-state notice-soft"><i class="bi bi-calendar-x"></i>No rating period is open right now, so a new IPCR cannot be created. HR will open the next period.</div>
    @elseif (! $template)
        <div class="empty-state notice-soft"><i class="bi bi-hourglass"></i>HR has not published your IPCR template for {{ $period->name }} yet. Check again later.</div>
    @else
        <x-card title="Create your IPCR for {{ $period->name }}">
            <p>Your IPCR will be built from the published <strong>{{ $template->employeeTypeLabel() }} template, Version {{ $template->version }}</strong>.
                It has {{ $template->items->count() }} performance indicators:</p>
            <ul>
                @foreach (\App\Models\Ipcr::SECTIONS as $key => $label)
                    <li>{{ $label }}: {{ $template->items->where('section', $key)->count() }} indicators</li>
                @endforeach
            </ul>
            @if ($period->submission_deadline)
                <p class="mb-2">Submission deadline: <strong>{{ $period->submission_deadline->format('F j, Y') }}</strong>.</p>
            @endif
            <p class="small text-secondary">Your IPCR keeps this version even if HR changes the template later.</p>
            <form method="POST" action="{{ route('employee.ipcrs.store') }}">
                @csrf
                <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create IPCR</button>
            </form>
        </x-card>
    @endif
@endsection
