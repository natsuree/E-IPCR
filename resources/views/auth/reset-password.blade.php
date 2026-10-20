@extends('layouts.guest')
@section('title', 'Set your password')

@section('content')
    <x-card title="Set your password">
        <p class="text-secondary small">Choose a password with at least 8 characters. You will use it with your email to log in.</p>
        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="mb-3">
                <label class="form-label fw-semibold" for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="username" required
                       value="{{ old('email', $email) }}" @class(['form-control', 'is-invalid' => $errors->has('email')])>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="password">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required autofocus
                       @class(['form-control', 'is-invalid' => $errors->has('password')])>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="password_confirmation">Type the new password again</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="form-control">
            </div>
            <button type="submit" class="btn btn-primary w-100">Save password</button>
        </form>
    </x-card>
@endsection
