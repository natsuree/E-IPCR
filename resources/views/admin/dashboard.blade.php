@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', 'Accounts and units in E-IPCR. Administrators manage accounts only, not IPCR content.')

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="{{ route('admin.users.create') }}"><i class="bi bi-person-plus"></i> Add user</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.users.import') }}"><i class="bi bi-upload"></i> Import CSV</a>
    </div>
@endsection

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat label="Accounts" :value="$counts['accounts']" /></div>
        <div class="col-6 col-lg-3"><x-stat label="Active" :value="$counts['active']" /></div>
        <div class="col-6 col-lg-3"><x-stat label="Inactive" :value="$counts['inactive']" hint="Cannot sign in; records kept" /></div>
        <div class="col-6 col-lg-3"><x-stat label="Colleges & offices" :value="$counts['units']" hint="Active units" /></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <x-card title="Active accounts by role" flush>
                <ul class="list-group list-group-flush">
                    @foreach (\App\Enums\Role::cases() as $role)
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="{{ route('admin.users.index', ['role' => $role->value, 'status' => 'active']) }}">{{ $role->label() }}</a>
                            <span class="fw-semibold">{{ $byRole[$role->value] ?? 0 }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>
        <div class="col-lg-7">
            <x-card title="Recent activity" flush>
                @if ($recent->isEmpty())
                    <div class="empty-state"><i class="bi bi-journal-text"></i>No activity recorded yet.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($recent as $log)
                            <li class="list-group-item small">
                                <div class="text-secondary">{{ $log->created_at->format('M j, Y g:i A') }} · {{ $log->groupLabel() }}</div>
                                <div>{{ $log->description }}</div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="card-footer"><a href="{{ route('admin.audit') }}">Open the audit log</a></div>
                @endif
            </x-card>
        </div>
    </div>
@endsection
