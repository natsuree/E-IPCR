@extends('layouts.guest')
@section('title', 'Forgot password')

@section('content')
    <x-card title="Forgot your password?">
        <p class="text-secondary small">Enter the email of your E-IPCR account. We will email you a link to set a new password.</p>
        @if (session('status'))
            <div class="alert alert-success py-2" role="status">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold" for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus
                       value="{{ old('email') }}" @class(['form-control', 'is-invalid' => $errors->has('email')])>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary w-100">Email me a reset link</button>
        </form>
        <div class="text-center mt-3"><a href="{{ route('login') }}" class="small">Back to log in</a></div>
    </x-card>
@endsection
