{{-- Review history: every workflow action with date, person, role and remarks. Expects $ipcr. --}}
<x-card title="Review history" flush>
    <div class="table-responsive">
        <table class="table table-eipcr">
            <thead><tr><th>Date</th><th>Person</th><th>Role</th><th>Action</th><th>Remarks</th></tr></thead>
            <tbody>
            @forelse ($ipcr->reviews as $review)
                <tr>
                    <td class="text-nowrap">{{ $review->created_at->format('M j, Y g:i A') }}</td>
                    <td>{{ $review->user?->name ?? 'Former account' }}</td>
                    <td>{{ $review->role->label() }}</td>
                    <td>
                        <span @class(['fw-semibold', 'text-warning-emphasis' => $review->action === 'returned', 'text-accent' => $review->action !== 'returned'])>{{ $review->actionLabel() }}</span>
                    </td>
                    <td style="white-space: pre-line">{{ $review->remarks ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-secondary">No actions yet. The history starts when the IPCR is submitted.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
