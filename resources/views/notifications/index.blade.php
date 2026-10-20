@extends('layouts.app')
@section('title', 'Notifications')
@section('subtitle', 'Updates about IPCRs you prepared or need to review.')

@section('actions')
    @if (auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route(auth()->user()->routePrefix().'.notifications.read-all') }}">
            @csrf
            <button class="btn btn-outline-primary"><i class="bi bi-check2-all"></i> Mark all as read</button>
        </form>
    @endif
@endsection

@section('content')
    <x-card title="Notifications" flush>
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                <a href="{{ route(auth()->user()->routePrefix().'.notifications.open', $notification->id) }}"
                   class="list-group-item list-group-item-action d-flex gap-3 align-items-start {{ $notification->read_at ? '' : 'fw-semibold' }}">
                    <i class="bi {{ $notification->read_at ? 'bi-envelope-open text-secondary' : 'bi-envelope-fill text-accent' }} mt-1" aria-hidden="true"></i>
                    <div class="flex-grow-1">
                        <div>{{ $notification->data['message'] ?? 'Update' }}</div>
                        <div class="small text-secondary fw-normal">{{ $notification->created_at->format('M j, Y g:i A') }} · {{ $notification->created_at->diffForHumans() }}</div>
                    </div>
                    @unless ($notification->read_at)<span class="badge rounded-pill" style="background:var(--eipcr-gold);color:#2b2300">New</span>@endunless
                </a>
            @empty
                <div class="list-group-item text-secondary">You have no notifications.</div>
            @endforelse
        </div>
    </x-card>
    {{ $notifications->links('pagination::bootstrap-5') }}
@endsection
