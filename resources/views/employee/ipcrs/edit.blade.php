@extends('layouts.app')
@section('title', 'Create / Edit IPCR')
@section('subtitle', 'Enter your actual accomplishment for each indicator, save your draft, then submit.')

@section('actions')
    <x-status :status="$ipcr->status" class="fs-6" />
@endsection

@section('content')
    @if ($returned)
        <div class="notice-returned mb-4">
            <div class="fw-semibold mb-1"><i class="bi bi-arrow-return-left"></i> Returned by {{ $returned->user?->name }} ({{ $returned->role->label() }}) on {{ $returned->created_at->format('M j, Y') }}</div>
            <div style="white-space: pre-line">{{ $returned->remarks }}</div>
        </div>
    @endif

    @include('ipcr.partials.header')

    <form method="POST" action="{{ route('employee.ipcrs.update', $ipcr) }}" id="ipcr-form">
        @csrf
        @method('PUT')

        @include('ipcr.partials.sections', ['mode' => 'employee'])

        <x-card title="Signatories">
            @include('ipcr.partials.signatories')
        </x-card>

        <div class="d-flex flex-wrap gap-2 justify-content-end mb-4">
            <button type="submit" name="action" value="save" class="btn btn-outline-primary"><i class="bi bi-save"></i> Save draft</button>
            <button type="submit" name="action" value="print" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print preview</button>
            <button type="submit" name="action" value="submit" class="btn btn-primary"><i class="bi bi-send"></i> Submit IPCR</button>
        </div>
    </form>
@endsection
