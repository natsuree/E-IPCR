@extends('layouts.app')
@section('title', 'Users')
@section('heading', $user->exists ? 'Edit user' : 'Add user')
@section('subtitle', $user->exists ? 'Update '.$user->name.'\'s account.' : 'Create an account. The person gets an email with a link to set their own password.')

@section('content')
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="$user->exists ? $user->name : 'New account'">
                <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" novalidate>
                    @csrf
                    @if ($user->exists) @method('PUT') @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">Full name</label>
                            <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Email</label>
                            <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="employee_id" class="form-label fw-semibold">Employee ID</label>
                            <input id="employee_id" name="employee_id" class="form-control @error('employee_id') is-invalid @enderror" value="{{ old('employee_id', $user->employee_id) }}">
                            @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="position" class="form-label fw-semibold">Position</label>
                            <input id="position" name="position" class="form-control @error('position') is-invalid @enderror" value="{{ old('position', $user->position) }}">
                            @error('position')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label fw-semibold">Role</label>
                            <select id="role" name="role" class="form-select @error('role') is-invalid @enderror">
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Teaching and Non-Teaching Employees get the matching IPCR template. Changing a Department Head or Dean/Director moves the review queue to whoever holds the role for that unit.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="college_id" class="form-label fw-semibold">College / office</label>
                            <select id="college_id" name="college_id" class="form-select @error('college_id') is-invalid @enderror">
                                <option value="">None (system administration)</option>
                                @foreach ($colleges as $college)
                                    <option value="{{ $college->id }}" @selected((int) old('college_id', $user->college_id) === $college->id)>{{ $college->name }}</option>
                                @endforeach
                            </select>
                            @error('college_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label fw-semibold">Department</label>
                            <select id="department_id" name="department_id" class="form-select @error('department_id') is-invalid @enderror">
                                <option value="">None</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" data-college="{{ $department->college_id }}" @selected((int) old('department_id', $user->department_id) === $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Offices usually have no departments.</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div>
                                <input type="hidden" name="active" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1" @checked(old('active', $user->active))>
                                    <label class="form-check-label" for="active">Active (can sign in)</label>
                                </div>
                                @error('active')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    @unless ($user->exists)
                        <div class="notice-soft small mt-3"><i class="bi bi-envelope"></i> After you create the account, E-IPCR emails the person a link to set their own password.</div>
                    @endunless

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary">{{ $user->exists ? 'Save changes' : 'Create account' }}</button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Show only the departments of the chosen college/office.
        (function () {
            var college = document.getElementById('college_id');
            var department = document.getElementById('department_id');
            function sync() {
                Array.prototype.forEach.call(department.options, function (option) {
                    if (!option.dataset.college) { return; }
                    var match = option.dataset.college === college.value;
                    option.hidden = !match;
                    if (!match && option.selected) { department.value = ''; }
                });
            }
            college.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
