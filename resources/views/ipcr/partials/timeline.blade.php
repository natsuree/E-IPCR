{{-- Employee → Department Head → Dean/Director → HR. Expects $ipcr. --}}
@php($icons = ['done' => 'check-lg', 'current' => 'hourglass-split', 'returned' => 'arrow-return-left', 'skipped' => 'dash', 'pending' => 'circle'])
<div class="timeline" role="list" aria-label="Approval steps">
    @foreach ($ipcr->timeline() as $step)
        <div class="timeline-step {{ $step['state'] }}" role="listitem" @if ($step['state'] === 'current') aria-current="step" @endif>
            <div class="timeline-dot"><i class="bi bi-{{ $icons[$step['state']] }}" aria-hidden="true"></i></div>
            <div class="timeline-name">{{ $step['label'] }}</div>
            <div class="timeline-meta">{{ $step['note'] }}</div>
            @if ($step['review']?->user && $step['state'] !== 'current')
                <div class="timeline-meta">{{ $step['review']->user->name }}</div>
            @endif
            @if ($step['state'] === 'current')
                <span class="badge rounded-pill mt-1" style="background:var(--eipcr-gold);color:#2b2300">Current step</span>
            @endif
        </div>
    @endforeach
</div>
