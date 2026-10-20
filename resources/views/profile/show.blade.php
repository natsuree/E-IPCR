@extends('layouts.app')
@section('title', 'Profile')
@section('subtitle', 'Your account details. Contact the Administrator to change your role or unit.')

@section('content')
    <div class="row g-4">
        <div class="col-lg-6">
            <x-card title="Account">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $user->name }}</dd>
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $user->email }}</dd>
                    <dt class="col-sm-4">Role</dt><dd class="col-sm-8">{{ $user->role->label() }}</dd>
                    <dt class="col-sm-4">Position</dt><dd class="col-sm-8">{{ $user->position ?? '—' }}</dd>
                    <dt class="col-sm-4">Unit</dt><dd class="col-sm-8">{{ $user->unitLabel() }}</dd>
                    @if ($user->employee_type)
                        <dt class="col-sm-4">Employee type</dt><dd class="col-sm-8">{{ $user->employeeTypeLabel() }}</dd>
                    @endif
                </dl>
            </x-card>
            <x-card title="Log out">
                <p class="text-secondary">To use another sample account, log out and log in again with that account.</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Log out</button>
                </form>
            </x-card>
        </div>
        <div class="col-lg-6">
            <x-card title="Change password">
                <form method="POST" action="{{ route($user->routePrefix().'.profile.password') }}" novalidate>
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-semibold">Current password</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="form-control @error('current_password') is-invalid @enderror">
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">New password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label fw-semibold">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="form-control">
                    </div>
                    <button class="btn btn-primary">Change password</button>
                </form>
            </x-card>
        </div>
    </div>
@endsection
