{{-- Signatory block filled from the current review cycle. Expects $ipcr. --}}
@php($signoffs = $ipcr->signoffs())
<div class="signatories">
    @foreach (\App\Models\IpcrTemplate::SIGNATORY_FIELDS as $key => $fallback)
        @php($review = $signoffs[$key])
        <div>
            <div class="signatory">
                <div class="who">{{ $key === 'ratee' ? $ipcr->user->name : ($review?->user?->name ?? 'Pending') }}</div>
                <div class="label">{{ $ipcr->template->signatory($key) }}</div>
                <div class="label">Date: {{ $review?->created_at?->format('M j, Y') ?? '__________' }}</div>
            </div>
        </div>
    @endforeach
</div>
