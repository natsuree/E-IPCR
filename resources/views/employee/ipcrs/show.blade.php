@extends('layouts.app')
@section('title', 'My IPCRs')
@section('heading', 'IPCR · '.$ipcr->period->name)
@section('subtitle', 'Read-only view of this IPCR.')

@section('actions')
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <x-status :status="$ipcr->status" class="fs-6" />
        <a class="btn btn-outline-primary" href="{{ route('employee.tracking', ['ipcr' => $ipcr->id]) }}"><i class="bi bi-signpost-split"></i> Status tracking</a>
        <a class="btn btn-outline-primary" href="{{ route('employee.ipcrs.print', $ipcr) }}"><i class="bi bi-printer"></i> Print preview</a>
        <a class="btn btn-outline-primary" href="{{ route('employee.ipcrs.pdf', $ipcr) }}"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
        @can('update', $ipcr)
            <a class="btn btn-primary" href="{{ route('employee.ipcrs.edit', $ipcr) }}"><i class="bi bi-pencil-square"></i> Edit</a>
        @endcan
    </div>
@endsection

@section('content')
    @include('ipcr.partials.header')
    @include('ipcr.partials.sections', ['mode' => 'readonly'])
    @if ($ipcr->status->value === 'completed')
        @include('ipcr.partials.final-rating')
    @endif
    <x-card title="Signatories">@include('ipcr.partials.signatories')</x-card>
    @include('ipcr.partials.history')
@endsection
