@extends('layouts.app')
@section('title', 'My IPCRs')
@section('subtitle', 'Every IPCR you have prepared, by rating period.')

@section('content')
    <x-card title="My IPCRs" flush>
        @if ($ipcrs->isEmpty())
            <div class="empty-state"><i class="bi bi-folder2-open"></i>You have no IPCRs yet. Use <a href="{{ route('employee.ipcrs.current') }}">Create / Edit IPCR</a> to start one.</div>
        @else
            <div class="table-responsive">
                <table class="table table-eipcr table-hover">
                    <thead><tr><th>Rating period</th><th>Template</th><th>Status</th><th>Final rating</th><th>Last updated</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    @foreach ($ipcrs as $ipcr)
                        <tr>
                            <td class="fw-semibold">{{ $ipcr->period->name }} @if ($period && $ipcr->rating_period_id === $period->id)<span class="badge badge-soft ms-1">Current</span>@endif</td>
                            <td>Version {{ $ipcr->template->version }}</td>
                            <td><x-status :status="$ipcr->status" /></td>
                            <td>{{ $ipcr->final_rating !== null ? number_format((float) $ipcr->final_rating, 2).' · '.$ipcr->adjectival_rating : '—' }}</td>
                            <td class="text-nowrap">{{ $ipcr->updated_at->format('M j, Y') }}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('employee.ipcrs.show', $ipcr) }}">View</a>
                                @if ($ipcr->status->isEditableByEmployee())
                                    <a class="btn btn-sm btn-primary" href="{{ route('employee.ipcrs.edit', $ipcr) }}">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    {{ $ipcrs->links() }}
@endsection
