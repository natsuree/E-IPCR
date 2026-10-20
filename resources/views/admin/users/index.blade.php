@extends('layouts.app')
@section('title', 'Users')
@section('subtitle', 'Add, edit, deactivate or reactivate accounts, and send set-password emails.')

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="{{ route('admin.users.create') }}"><i class="bi bi-person-plus"></i> Add user</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.users.import') }}"><i class="bi bi-upload"></i> Import CSV</a>
    </div>
@endsection

@section('content')
    <x-card title="Find accounts">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="q" class="form-label small fw-semibold">Name, email or employee ID</label>
                <input id="q" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label for="role" class="form-label small fw-semibold">Role</label>
                <select id="role" name="role" class="form-select">
                    <option value="">All roles</option>
                    @foreach (\App\Enums\Role::options() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="college" class="form-label small fw-semibold">Unit</label>
                <select id="college" name="college" class="form-select">
                    <option value="">All colleges and offices</option>
                    @foreach ($colleges as $college)
                        <option value="{{ $college->id }}" @selected(($filters['college'] ?? null) == $college->id)>{{ $college->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Active and inactive</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Search</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </x-card>

    <x-card title="Accounts ({{ $users->total() }})" flush>
        @if ($users->isEmpty())
            <div class="empty-state"><i class="bi bi-people"></i>No accounts match this search.</div>
        @else
            <div class="table-responsive">
                <table class="table table-eipcr table-hover">
                    <thead><tr><th>Name</th><th>Employee ID</th><th>Role</th><th>Unit</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td><span class="fw-semibold">{{ $u->name }}</span><div class="small text-secondary">{{ $u->email }}</div></td>
                            <td>{{ $u->employee_id ?? '—' }}</td>
                            <td>{{ $u->role->label() }}</td>
                            <td>{{ $u->unitLabel() }}</td>
                            <td>
                                <span @class(['badge badge-status', 'badge-status-completed' => $u->active, 'badge-status-draft' => ! $u->active])>{{ $u->active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.edit', $u) }}">Edit</a>
                                @unless ($u->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.users.toggle', $u) }}" class="d-inline"
                                          @if ($u->active) onsubmit="return confirm('Deactivate {{ $u->name }}? They will not be able to sign in. Their IPCRs and records stay.')" @endif>
                                        @csrf
                                        <button @class(['btn btn-sm', 'btn-outline-danger' => $u->active, 'btn-outline-success' => ! $u->active])>{{ $u->active ? 'Deactivate' : 'Reactivate' }}</button>
                                    </form>
                                @endunless
                                @if ($u->active)
                                    <form method="POST" action="{{ route('admin.users.resend', $u) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" title="Email a new set-password link">Resend link</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    {{ $users->links() }}
@endsection
