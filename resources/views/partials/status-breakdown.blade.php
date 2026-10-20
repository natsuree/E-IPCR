{{-- Count of employees per IPCR status. Params: $summary, $analytics (IpcrAnalytics). --}}
<ul class="list-group list-group-flush">
    @foreach (\App\Services\IpcrAnalytics::STATUS_LABELS as $key => $label)
        @php($n = $summary['counts'][$key])
        <li class="list-group-item d-flex align-items-center gap-3">
            <x-status :status="$key" style="min-width:130px" />
            <div class="progress flex-grow-1" role="progressbar" aria-label="{{ $label }}" aria-valuenow="{{ $analytics->percent($n) }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: {{ $analytics->percent($n) }}%"></div>
            </div>
            <span class="fw-semibold text-end" style="min-width:2.5rem">{{ $n }}</span>
        </li>
    @endforeach
</ul>
