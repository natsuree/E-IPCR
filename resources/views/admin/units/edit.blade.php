@extends('layouts.app')
@section('title', 'Colleges & Offices')
@section('heading', $college->name)
@section('subtitle', 'Edit this '.($college->isOffice() ? 'office' : 'college').' and its departments.')

@section('actions')
    <a href="{{ route('admin.units.index') }}" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> All units</a>
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-xl-5">
            <x-card title="Details">
                <form method="POST" action="{{ route('admin.units.update', $college) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('admin.units.fields')
                    <div class="text-end"><button class="btn btn-primary">Save</button></div>
                </form>
            </x-card>
            <x-card title="Status">
                <p class="small">{{ $college->active
                    ? 'Active. It can be chosen for accounts.'
                    : 'Inactive. It cannot be chosen for new or edited accounts; existing accounts and records keep it.' }}</p>
                <form method="POST" action="{{ route('admin.units.toggle', $college) }}"
                      @if ($college->active) onsubmit="return confirm('Deactivate {{ $college->name }}?')" @endif>
                    @csrf
                    <button @class(['btn', 'btn-outline-danger' => $college->active, 'btn-outline-success' => ! $college->active])>{{ $college->active ? 'Deactivate' : 'Reactivate' }}</button>
                </form>
            </x-card>
        </div>
        <div class="col-xl-7">
            <x-card title="Departments ({{ $departments->count() }})" flush>
                @if ($departments->isEmpty())
                    <div class="empty-state"><i class="bi bi-diagram-3"></i>{{ $college->name }} has no departments. Offices usually have none.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-eipcr">
                            <thead><tr><th>Department</th><th class="text-end">Accounts</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            @foreach ($departments as $department)
                                <tr>
                                    <td>
                                        <form method="POST" action="{{ route('admin.units.departments.update', [$college, $department]) }}" class="d-flex gap-2" id="dept-{{ $department->id }}">
                                            @csrf
                                            @method('PUT')
                                            <label class="visually-hidden" for="dept-name-{{ $department->id }}">Department name</label>
                                            <input id="dept-name-{{ $department->id }}" name="name" class="form-control form-control-sm" value="{{ $department->name }}">
                                            <button class="btn btn-sm btn-outline-primary">Save</button>
                                        </form>
                                    </td>
                                    <td class="text-end">{{ $department->users_count }}</td>
                                    <td><span @class(['badge badge-status', 'badge-status-completed' => $department->active, 'badge-status-draft' => ! $department->active])>{{ $department->active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.units.departments.toggle', [$college, $department]) }}">
                                            @csrf
                                            <button @class(['btn btn-sm', 'btn-outline-danger' => $department->active, 'btn-outline-success' => ! $department->active])>{{ $department->active ? 'Deactivate' : 'Reactivate' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.units.departments.store', $college) }}" class="d-flex gap-2 p-3 border-top" style="background: var(--bs-tertiary-bg)" novalidate>
                    @csrf
                    <label class="visually-hidden" for="new-department">New department name</label>
                    <input id="new-department" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="New department name">
                    <button class="btn btn-primary text-nowrap">+ Add department</button>
                </form>
                @error('name')<div class="text-danger small px-3 pb-3">{{ $message }}</div>@enderror
            </x-card>
        </div>
    </div>
@endsection
