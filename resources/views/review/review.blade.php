@extends('layouts.app')
@section('title', 'Review Queue')
@section('heading', ($isHr ? 'HR Review · ' : 'Review IPCR · ').$ipcr->user->name)
@section('subtitle', $isHr
    ? 'Check the ratings, then give final approval or return the IPCR for correction.'
    : 'Rate each indicator, then approve and forward, or return the IPCR for correction.')

@section('actions')
    <x-status :status="$ipcr->status" class="fs-6" />
@endsection

@section('content')
    <x-card title="Approval route">
        @include('ipcr.partials.timeline')
    </x-card>

    @include('ipcr.partials.header')

    <form method="POST" action="{{ route(auth()->user()->routePrefix().'.review.decide', $ipcr) }}" novalidate>
        @csrf
        @if ($errors->any() && ! $errors->has('ipcr'))
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> Please fix the following:
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @include('ipcr.partials.sections', ['mode' => 'review'])

        @if ($isHr)
            @include('ipcr.partials.final-rating', ['result' => $preview])
        @endif

        @include('ipcr.partials.history')

        <x-card title="Your decision">
            <label for="remarks" class="form-label fw-semibold">Remarks</label>
            <textarea id="remarks" name="remarks" rows="3" @class(['form-control', 'is-invalid' => $errors->has('remarks')])
                      placeholder="Required when you return the IPCR. Tell the employee what to correct.">{{ old('remarks') }}</textarea>
            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="small text-secondary mt-2 mb-3">
                @if ($isHr)
                    <strong>Approve (final)</strong> completes the IPCR and computes the final rating. Every indicator needs a rating.
                @else
                    <strong>Approve &amp; forward</strong> sends it to the next step. Every indicator needs a rating.
                @endif
                <strong>Return for correction</strong> sends it back to {{ $ipcr->user->name }}; after resubmission it starts again at the Department Head.
            </p>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a href="{{ route(auth()->user()->routePrefix().'.queue') }}" class="btn btn-outline-secondary">Back to queue</a>
                <button type="submit" name="action" value="return" class="btn btn-outline-danger"><i class="bi bi-arrow-return-left"></i> Return for correction</button>
                <button type="submit" name="action" value="approve" class="btn btn-primary">
                    <i class="bi bi-check2-circle"></i> {{ $isHr ? 'Approve (final)' : 'Approve & forward' }}
                </button>
            </div>
        </x-card>
    </form>
@endsection
