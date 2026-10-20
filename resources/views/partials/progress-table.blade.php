{{-- Submission and completion progress per unit. Params: $rows (IpcrAnalytics::groupedProgress), $unitLabel, $showCollege (optional). --}}
<div class="table-responsive">
    <table class="table table-eipcr">
        <thead>
        <tr><th>{{ $unitLabel }}</th><th class="text-end">Employees</th><th class="text-end">Submitted</th><th class="text-end">Completed</th><th class="text-end">Not yet submitted</th><th class="text-end">Average rating</th><th style="min-width:180px">Progress</th></tr>
        </thead>
        <tbody>
        @forelse ($rows as $row)
            @php($name = ($showCollege ?? false) && $row['college'] && $row['college'] !== $row['name'] ? $row['college'].' · '.$row['name'] : $row['name'])
            <tr>
                <td class="fw-semibold">{{ $name }}</td>
                <td class="text-end">{{ $row['employees'] }}</td>
                <td class="text-end">{{ $row['submitted'] }}</td>
                <td class="text-end">{{ $row['completed'] }}</td>
                <td class="text-end">{{ $row['not_submitted'] }}</td>
                <td class="text-end">{{ $row['average'] === null ? '—' : number_format($row['average'], 2) }}</td>
                <td>
                    <div class="small text-secondary mb-1">{{ $row['submitted_pct'] }}% submitted · {{ $row['completed_pct'] }}% completed</div>
                    <div class="progress" role="progressbar" aria-label="{{ $name }} submitted" aria-valuenow="{{ $row['submitted_pct'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ $row['submitted_pct'] }}%"></div>
                    </div>
                    <div class="progress mt-1" role="progressbar" aria-label="{{ $name }} completed" aria-valuenow="{{ $row['completed_pct'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar gold" style="width: {{ $row['completed_pct'] }}%"></div>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i>No employees match these filters.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="small text-secondary px-3 py-2 mb-0"><span class="legend-swatch" style="background:#256A2B"></span> Submitted
    <span class="legend-swatch ms-3" style="background:#E5BD21"></span> Completed · Average rating counts completed IPCRs only.</p>
