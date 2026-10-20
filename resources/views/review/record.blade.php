@extends('layouts.app')
@section('title', 'IPCR Records')
@section('heading', $ipcr->user->name.' · '.$ipcr->period->name)
@section('subtitle', 'Completed IPCR record. Read-only.')

@section('actions')
    @php($prefix = auth()->user()->routePrefix())
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route($prefix.'.records') }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Back to records</a>
        <a href="{{ route($prefix.'.records.print', $ipcr) }}" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</a>
        <a href="{{ route($prefix.'.records.pdf', $ipcr) }}" class="btn btn-primary"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
    </div>
@endsection

@section('content')
    @include('ipcr.partials.header')
    @include('ipcr.partials.sections', ['mode' => 'readonly'])
    @include('ipcr.partials.final-rating')
    <x-card title="Signatories">@include('ipcr.partials.signatories')</x-card>
    @include('ipcr.partials.history')
@endsection
