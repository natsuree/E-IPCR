{{-- Employees who missed the submission deadline. Params: $period, $overdue (first 15 rows from IpcrAnalytics::notSubmitted), $overdueCount. --}}
@if ($overdue->isNotEmpty())
    <x-card title="Past the deadline: {{ $overdueCount }} not submitted" flush class="border-warning">
        <p class="small px-3 pt-3 mb-2">The submission deadline for {{ $period->name }} was <strong>{{ $period->submission_deadline->format('F j, Y') }}</strong>. These employees have not submitted.</p>
        <div class="table-responsive">
            <table class="table table-eipcr">
                <thead><tr><th>Employee</th><th>College / office · department</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($overdue as $row)
                    <tr>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->college_name }}@if ($row->department_name) · {{ $row->department_name }}@endif</td>
                        <td><x-status :status="$row->ipcr_status ?? 'not_started'" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($overdueCount > $overdue->count())
            <p class="small text-secondary px-3 py-2 mb-0">And {{ $overdueCount - $overdue->count() }} more. See the full list in
                @if (auth()->user()->role === \App\Enums\Role::Hr)<a href="{{ route('hr.reports') }}#not-submitted">Reports &amp; Analytics</a>@else<a href="{{ route(auth()->user()->routePrefix().'.analytics') }}#not-submitted">Analytics</a>@endif.</p>
        @endif
    </x-card>
@endif
