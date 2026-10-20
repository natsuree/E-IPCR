<tr>
    <td>{{ $showDept ? ($ipcr->user->department?->name ?? $ipcr->user->college?->name) : '' }}</td>
    <td>
        <span class="fw-semibold">{{ $ipcr->user->name }}</span>
        <div class="small text-secondary">{{ $ipcr->user->employeeTypeLabel() }}@if ($ipcr->user->position) · {{ $ipcr->user->position }}@endif</div>
    </td>
    <td>{{ $ipcr->period->name }}</td>
    <td class="text-nowrap">{{ $ipcr->updated_at->format('M j, Y') }}</td>
    <td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route(auth()->user()->routePrefix().'.review', $ipcr) }}">Review</a></td>
</tr>
