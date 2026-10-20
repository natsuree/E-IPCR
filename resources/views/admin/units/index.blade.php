@extends('layouts.app')
@section('title', 'Colleges & Offices')
@section('subtitle', 'Colleges, offices and their departments. Units are deactivated, not deleted, so past records keep them.')

@section('content')
    <div class="row g-4">
        <div class="col-xl-8">
            <x-card title="Find a unit">
                <form method="GET" action="{{ route('admin.units.index') }}" class="d-flex gap-2">
                    <label for="q" class="visually-hidden">Name or code</label>
                    <input id="q" name="q" class="form-control" placeholder="Name or code" value="{{ request('q') }}">
                    <button class="btn btn-primary">Search</button>
                    <a href="{{ route('admin.units.index') }}" class="btn btn-outline-secondary">Clear</a>
                </form>
            </x-card>
            <x-card title="Colleges and offices ({{ $colleges->total() }})" flush>
                @if ($colleges->isEmpty())
                    <div class="empty-state"><i class="bi bi-building"></i>No colleges or offices match.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-eipcr table-hover">
                            <thead><tr><th>Name</th><th>Code</th><th>Type</th><th class="text-end">Departments</th><th class="text-end">Accounts</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            @foreach ($colleges as $college)
                                <tr>
                                    <td class="fw-semibold">{{ $college->name }}</td>
                                    <td>{{ $college->code ?? '—' }}</td>
                                    <td>{{ $college->isOffice() ? 'Office' : 'College' }}</td>
                                    <td class="text-end">{{ $college->departments_count }}</td>
                                    <td class="text-end">{{ $college->users_count }}</td>
                                    <td><span @class(['badge badge-status', 'badge-status-completed' => $college->active, 'badge-status-draft' => ! $college->active])>{{ $college->active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="text-end"><a href="{{ route('admin.units.edit', $college) }}" class="btn btn-sm btn-outline-primary">Manage</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
            {{ $colleges->links() }}
        </div>
        <div class="col-xl-4">
            <x-card title="Add a college or office">
                <form method="POST" action="{{ route('admin.units.store') }}" novalidate>
                    @csrf
                    @include('admin.units.fields', ['college' => null])
                    <div class="text-end"><button class="btn btn-primary">Add</button></div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
