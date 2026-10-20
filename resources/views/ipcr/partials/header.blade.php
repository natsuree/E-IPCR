{{-- Employee details, commitment statement and rating scale. Expects $ipcr. --}}
@php($template = $ipcr->template)
<x-card title="Individual Performance Commitment and Review (IPCR)">
    <x-slot:actions>
        <span class="badge text-bg-light">{{ $template->employeeTypeLabel() }} template · Version {{ $template->version }}</span>
    </x-slot:actions>
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-lg-3"><div class="small text-secondary">Employee</div><div class="fw-semibold">{{ $ipcr->user->name }}</div></div>
        <div class="col-sm-6 col-lg-3"><div class="small text-secondary">Position</div><div class="fw-semibold">{{ $ipcr->user->position ?? '—' }}</div></div>
        <div class="col-sm-6 col-lg-3"><div class="small text-secondary">College / office · department</div><div class="fw-semibold">{{ $ipcr->user->unitLabel() }}</div></div>
        <div class="col-sm-6 col-lg-3"><div class="small text-secondary">Rating period</div><div class="fw-semibold">{{ $ipcr->period->name }}</div></div>
    </div>
    <p class="notice-soft mb-3">
        I, <strong>{{ $ipcr->user->name }}</strong>, {{ $ipcr->user->position ?? 'employee' }} of {{ $ipcr->user->unitLabel() }},
        {{ $template->commitmentClause() }} <strong>{{ $template->commitment_period }}</strong>.
    </p>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="small fw-semibold text-secondary">Rating scale:</span>
        <div class="rating-scale">
            @foreach ($template->scale() as $value => $label)
                <span><strong>{{ $value }}</strong> {{ $label }}</span>
            @endforeach
        </div>
    </div>
</x-card>
