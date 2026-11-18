@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', 'Institution-wide IPCR progress')
@section('content')
    <x-card title="University progress · {{ $period?->name ?? 'No evaluation period' }}">
        <dl class="row">
            @foreach (['employees' => 'Eligible employees', 'submitted' => 'Submitted at least once', 'completed' => 'Completed', 'returned' => 'Returned for correction'] as $key => $label)
                <dt class="col-8">{{ $label }}</dt><dd class="col-4">{{ $summary[$key] }}</dd>
            @endforeach
        </dl>
        <a class="btn btn-primary" href="{{ route('vpaa.queue') }}">Review queue ({{ $waiting }})</a>
    </x-card>
@endsection
