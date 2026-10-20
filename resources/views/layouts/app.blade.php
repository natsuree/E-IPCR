@php($user = auth()->user())
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · E-IPCR</title>
    @include('layouts.partials.head')
    @stack('head')
</head>
<body>
<a href="#main" class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-light">Skip to content</a>
<div class="app-shell">
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Main menu">
        <div class="offcanvas-body">
            <div class="sidebar-brand">
                @include('partials.seal')
                <div>
                    <div class="brand-name">E-IPCR</div>
                    <div class="brand-sub">PERFORMANCE MANAGEMENT</div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
            </div>
            <div class="sidebar-label">WORKSPACE</div>
            <nav class="sidebar-nav nav flex-column">
                @foreach (\App\Support\Navigation::for($user) as $item)
                    <a href="{{ $item['url'] }}" @class(['nav-link', 'active' => $item['active'], 'indent' => $item['indent']]) @if ($item['active']) aria-current="page" @endif>
                        <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                        @if ($item['badge'] > 0)
                            <span class="badge rounded-pill" aria-label="{{ $item['badge'] }} unread">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
            <div class="sidebar-footer">Mariano Marcos State University · Batac City, Ilocos Norte</div>
        </div>
    </aside>

    <div class="app-main">
        <header class="topbar">
            <button class="btn btn-sm btn-outline-light menu-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="crumb text-truncate min-w-0">Workspace / <strong>@yield('title', 'Dashboard')</strong></div>
            @if ($currentPeriod)
                <span class="period-chip d-none d-md-inline" title="Current rating period">
                    <i class="bi bi-calendar3" aria-hidden="true"></i> {{ $currentPeriod->name }}
                    @unless ($currentPeriod->isOpen()) · {{ $currentPeriod->statusLabel() }} @endunless
                </span>
            @endif
            <a href="{{ route($user->routePrefix().'.profile') }}" class="user-link ms-auto">
                <div class="user-name">{{ $user->name }}</div>
                <div class="user-role">{{ $user->role->label() }}</div>
            </a>
        </header>

        <main class="app-content" id="main">
            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="status">
                    <i class="bi bi-check-circle me-1"></i> {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @foreach (['ipcr', 'template', 'active', 'file'] as $key)
                @error($key)
                    <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-1"></i> {{ $message }}</div>
                @enderror
            @endforeach

            <div class="page-header d-flex flex-wrap align-items-start gap-3">
                <div class="me-auto">
                    <h1 class="page-title">@yield('heading', $__env->yieldContent('title', 'Dashboard'))</h1>
                    <p class="page-subtitle">@yield('subtitle')</p>
                </div>
                @yield('actions')
            </div>

            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
