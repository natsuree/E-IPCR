@extends('layouts.app')
@section('title', 'Report preview')
@section('subtitle', $analytics->scope->label.' · '.$analytics->period?->name)
@section('content')
    <div class="no-print mb-3"><a href="{{ route($route, $filters) }}">Back to reports</a>
        <button class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
        <a href="{{ route($route.'.export', $filters) }}">Export CSV</a></div>
    <x-card title="Employee progress">
        <p>{{ $summary['employees'] }} eligible · {{ $summary['submitted'] }} submitted ({{ $analytics->percent($summary['submitted']) }}%) · {{ $summary['completed'] }} completed ({{ $analytics->percent($summary['completed']) }}%)</p>
        <table class="table table-eipcr"><thead><tr><th>Employee</th><th>Unit</th><th>Status</th><th>Current reviewer</th><th>Final rating</th></tr></thead>
        <tbody>@foreach ($rows as $row)
            <tr><td>{{ $row->name }}</td><td>{{ $row->college_name }} · {{ $row->department_name }}</td><td>{{ $analytics::statusLabel($row->ipcr_status) }}</td><td>{{ $analytics::currentReviewer($row->ipcr_status) }}</td><td>{{ $row->ipcr_final_rating ?? '—' }}</td></tr>
        @endforeach</tbody></table>
    </x-card>
    @include('partials.leaders')
@endsection
