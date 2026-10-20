<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · E-IPCR</title>
    @include('layouts.partials.head')
</head>
<body>
<main class="login-page">
    <div class="login-card" style="max-width: {{ $__env->hasSection('wide') ? '920px' : '480px' }}">
        <div class="d-flex align-items-center gap-3 text-white mb-4">
            @include('partials.seal')
            <div>
                <div class="brand-name">E-IPCR</div>
                <div class="brand-sub">PERFORMANCE MANAGEMENT · MARIANO MARCOS STATE UNIVERSITY</div>
            </div>
        </div>
        @yield('content')
        <p class="text-center small text-secondary mt-3">Mariano Marcos State University · Batac City, Ilocos Norte</p>
    </div>
</main>
@stack('scripts')
</body>
</html>
