{{--
    Review history: every workflow action with date, person, role and remarks.
    Each reviewer decision shows the per-target ratings and remarks it was made with.
    Decisions recorded before those were saved are labelled as such. Expects $ipcr.
--}}
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
                @if ($review->isDecision())
                    <tr class="history-ratings">
                        <td colspan="5" class="pt-0">
                            @if ($review->lacksRatingSnapshot())
                                <span class="small text-secondary"><i class="bi bi-info-circle"></i> Per-target ratings were not recorded for this decision (made before they were saved with each review).</span>
                            @else
                                <details>
                                    <summary class="small">Ratings and remarks given with this decision ({{ count($review->item_ratings) }} targets)</summary>
                                    <table class="table table-sm small mb-0 mt-2">
                                        <thead><tr><th>Target</th><th>Rating</th><th>Remarks</th></tr></thead>
                                        <tbody>
                                        @foreach ($review->item_ratings as $row)
                                            <tr>
                                                <td>{{ \App\Models\Ipcr::SECTIONS[$row['section']] ?? $row['section'] }} #{{ $row['number'] }} · {{ \Illuminate\Support\Str::limit($row['indicator'], 90) }}</td>
                                                <td>{{ $row['rating'] ?? 'Not rated' }}</td>
                                                <td style="white-space: pre-line">{{ $row['remarks'] ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="5" class="text-secondary">No actions yet. The history starts when the IPCR is submitted.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
