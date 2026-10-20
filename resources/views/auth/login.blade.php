@extends('layouts.guest')
@section('title', 'Log in')
@if ($samples->isNotEmpty())
    @section('wide', '1')
@endif

@section('content')
    <div class="row g-4">
        <div class="{{ $samples->isNotEmpty() ? 'col-lg-6' : 'col-12' }}">
            <x-card title="Log in">
                <p class="text-secondary small">Sign in with the account the Administrator created for you.</p>
                @if (session('status'))
                    <div class="alert alert-success py-2" role="status">{{ session('status') }}</div>
                @endif
                <form method="POST" action="{{ route('login.attempt') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="email">Email</label>
                        <input id="email" name="email" type="email" autocomplete="username" required autofocus
                               value="{{ old('email') }}" @class(['form-control', 'is-invalid' => $errors->has('email')])>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="password">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               @class(['form-control', 'is-invalid' => $errors->has('password')])>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                            <label class="form-check-label small" for="remember">Keep me signed in</label>
                        </div>
                        <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Log in</button>
                </form>
            </x-card>
        </div>

        @if ($samples->isNotEmpty())
            <div class="col-lg-6">
                <x-card title="Sample accounts" flush>
                    <p class="small text-secondary px-3 pt-3 mb-2">Shown only in the local development environment. Click an account to fill in the fields. The password for each is <code>password</code>.</p>
                    <div class="list-group list-group-flush">
                        @foreach ($samples as $sample)
                            <button type="button" class="list-group-item list-group-item-action sample-account" data-email="{{ $sample->email }}">
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="fw-semibold">{{ $sample->name }}</span>
                                    <span class="badge badge-soft">{{ $sample->role->label() }}</span>
                                </div>
                                <div class="small text-secondary">{{ $sample->unitLabel() }} · {{ $sample->email }}</div>
                            </button>
                        @endforeach
                    </div>
                </x-card>
            </div>
        @endif
    </div>
@endsection

@if ($samples->isNotEmpty())
    @push('scripts')
        <script>
            document.querySelectorAll('.sample-account').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById('email').value = button.dataset.email;
                    document.getElementById('password').value = 'password';
                    document.getElementById('password').focus();
                });
            });
        </script>
    @endpush
@endif
