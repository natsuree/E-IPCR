@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', 'Your IPCR for the current rating period at a glance.')

@section('actions')
    @if (! $ipcr && $openPeriod)
        <form method="POST" action="{{ route('employee.ipcrs.store') }}">
            @csrf
            <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create IPCR</button>
        </form>
    @elseif ($ipcr?->status->isEditableByEmployee())
        <a class="btn btn-primary" href="{{ route('employee.ipcrs.edit', $ipcr) }}"><i class="bi bi-pencil-square"></i> Continue editing</a>
    @endif
@endsection

@section('content')
    @if (! $openPeriod)
        <div class="notice-soft mb-4"><i class="bi bi-info-circle"></i> No rating period is open right now, so new IPCRs cannot be created. HR will open the next period.</div>
    @elseif (! $ipcr && $openPeriod->deadlinePassed())
        <div class="notice-returned mb-4"><i class="bi bi-exclamation-triangle"></i> The submission deadline for {{ $openPeriod->name }} was {{ $openPeriod->submission_deadline->format('F j, Y') }}. Create and submit your IPCR as soon as possible.</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat">
                <div class="stat-label">Current IPCR status</div>
                <div class="mt-2"><x-status :status="$ipcr?->status ?? 'not_started'" class="fs-6" /></div>
                <div class="stat-hint mt-2"><a href="{{ route('employee.tracking') }}">Track status <i class="bi bi-arrow-right"></i></a></div>
            </div>
        </div>
        <div class="col-md-4">
            <x-stat label="Rating period" :value="$period?->name ?? 'None yet'" small
                    :hint="$period?->submission_deadline ? 'Submission deadline: '.$period->submission_deadline->format('M j, Y') : null" />
        </div>
        <div class="col-md-4">
            <x-stat label="Last action" small
                    :value="$lastAction ? $lastAction->actionLabel() : ($ipcr ? 'Draft saved' : 'None yet')"
                    :hint="$lastAction ? $lastAction->user?->name.' ('.$lastAction->role->label().') · '.$lastAction->created_at->format('M j, Y') : ($ipcr ? 'Last saved '.$ipcr->updated_at->format('M j, Y g:i A') : 'Create your IPCR to begin.')" />
        </div>
    </div>

    @if ($ipcr?->status->value === 'returned')
        <div class="notice-returned mb-4">
            <strong><i class="bi bi-arrow-return-left"></i> Your IPCR was returned for correction.</strong>
            Read the remarks on the <a href="{{ route('employee.tracking', ['ipcr' => $ipcr->id]) }}">Status Tracking</a> page, then revise and resubmit.
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <x-card title="How it works">
                <ol class="mb-0 ps-3">
                    <li class="mb-1"><strong>Create</strong> your IPCR. It is built from the template HR published for your employee type.</li>
                    <li class="mb-1"><strong>Fill in</strong> the actual accomplishment for every indicator in the Strategic, Core and Support Functions.</li>
                    <li class="mb-1"><strong>Submit</strong>. It goes to your Department Head, then the Dean/Director, then HR for final approval.</li>
                    <li><strong>Track</strong> each step. If a reviewer returns it, revise and resubmit; it starts again at the Department Head.</li>
                </ol>
            </x-card>
        </div>
        <div class="col-lg-5">
            <x-card title="Recent IPCRs" flush>
                <ul class="list-group list-group-flush">
                    @forelse ($history as $item)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <a href="{{ route('employee.ipcrs.show', $item) }}">{{ $item->period->name }}</a>
                            <span class="d-flex align-items-center gap-2">
                                @if ($item->final_rating !== null)<span class="small text-secondary">{{ number_format((float) $item->final_rating, 2) }}</span>@endif
                                <x-status :status="$item->status" />
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item"><div class="empty-state"><i class="bi bi-folder2-open"></i>You have no IPCRs yet.</div></li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
@endsection
