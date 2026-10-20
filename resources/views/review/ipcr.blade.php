@extends('layouts.app')
@section('title', 'Analytics')
@section('heading', $ipcr->user->name.' · '.$ipcr->period->name)
@section('subtitle', 'Read-only view of this IPCR.')

@section('actions')
    @php($prefix = auth()->user()->routePrefix())
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <x-status :status="$ipcr->status" class="fs-6" />
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route($prefix.'.dashboard') }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Back</a>
        @can('review', $ipcr)
            <a href="{{ route($prefix.'.review', $ipcr) }}" class="btn btn-primary">Review this IPCR</a>
        @endcan
    </div>
@endsection

@section('content')
    <x-card title="Approval route">
        @include('ipcr.partials.timeline')
    </x-card>
    @include('ipcr.partials.header')
    @include('ipcr.partials.sections', ['mode' => 'readonly'])
    @if ($ipcr->status->value === 'completed')
        @include('ipcr.partials.final-rating')
    @endif
    @include('ipcr.partials.history')
@endsection
