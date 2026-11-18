{{--
    IPCRs waiting at a stage that no active reviewer can act on. Expects $stalled.
    HR opens the IPCR; the Administrator (no IPCR content) opens the employee's account to fix the assignment.
--}}
@if ($stalled->isNotEmpty())
    <x-card title="IPCRs waiting for a reviewer to be assigned ({{ $stalled->count() }})" flush>
        <p class="small px-3 pt-3 mb-2">Nobody can act on these IPCRs until the missing reviewer or unit assignment is fixed under <strong>Users</strong>. Every approval stage still applies.</p>
        <div class="table-responsive">
            <table class="table table-eipcr mb-0">
                <thead><tr><th>Employee</th><th>Unit</th><th>Rating period</th><th>Waiting at</th><th>What is missing</th></tr></thead>
                <tbody>
                @foreach ($stalled as $row)
                    <tr>
                        <td>
                            @if (auth()->user()->role === \App\Enums\Role::Hr)
                                <a href="{{ route('hr.ipcrs.show', $row) }}" class="fw-semibold">{{ $row->user->name }}</a>
                            @elseif (auth()->user()->role === \App\Enums\Role::Admin)
                                <a href="{{ route('admin.users.edit', $row->user) }}" class="fw-semibold">{{ $row->user->name }}</a>
                            @else
                                {{ $row->user->name }}
                            @endif
                        </td>
                        <td>{{ $row->user->unitLabel() }}</td>
                        <td>{{ $row->period->name }}</td>
                        <td>{{ \App\Services\IpcrAnalytics::currentReviewer($row->status->value) }}</td>
                        <td class="small">{{ $row->stallReason() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
@endif
