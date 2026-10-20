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
    <div style="max-width: 520px; width: 100%">
        <x-card :title="$__env->yieldContent('title')">
            <p>@yield('message')</p>
            <p class="text-secondary small">@yield('hint')</p>
            @auth
                <a href="{{ auth()->user()->dashboardRoute() }}" class="btn btn-primary">Go to my dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">Log in</a>
            @endauth
        </x-card>
    </div>
</main>
</body>
</html>
