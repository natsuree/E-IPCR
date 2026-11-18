{{-- Employee details, commitment statement and rating scale. Expects $ipcr. --}}
@php($template = $ipcr->template)
<x-card title="Individual Performance Commitment and Review (IPCR)">
    <x-slot:actions>
        <span class="badge text-bg-light">{{ $template->employeeTypeLabel() }} template · Version {{ $template->version }}</span>
    </x-slot:actions>
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-lg-3"><div class="small text-secondary">Employee</div><div class="fw-semibold">{{ $ipcr->employeeName() }}</div></div>
        <div class="col-sm-6 col-lg-3"><div class="small text-secondary">Position</div><div class="fw-semibold">{{ $ipcr->employeePosition() ?? '—' }}</div></div>
        <div class="col-sm-6 col-lg-3"><div class="small text-secondary">College / office · department</div><div class="fw-semibold">{{ $ipcr->employeeUnitLabel() }}</div></div>
        <div class="col-sm-6 col-lg-3"><div class="small text-secondary">Rating period</div><div class="fw-semibold">{{ $ipcr->period->name }}</div></div>
    </div>
    @if ($ipcr->usesLegacyEmployeeDetails())
        <p class="small text-secondary mb-2"><i class="bi bi-info-circle"></i> Submitted before employee details were saved with each IPCR; the name, position and unit shown are from the current account.</p>
    @elseif ($ipcr->hasEmployeeSnapshot())
        <p class="small text-secondary mb-2">Employee details as of submission on {{ $ipcr->snapshot_taken_at->format('M j, Y') }}.</p>
    @endif
    <p class="notice-soft mb-3">
        I, <strong>{{ $ipcr->employeeName() }}</strong>, {{ $ipcr->employeePosition() ?? 'employee' }} of {{ $ipcr->employeeUnitLabel() }},
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
