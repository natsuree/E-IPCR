{{--
    The IPCR in the MMSU layout, shared by the print preview and the PDF.
    Uses plain tables and inline styles so dompdf renders it the same way.
    Expects $ipcr and $seal (image URL or file path, or null).
--}}
@php($signoffs = $ipcr->signoffs())
<table style="width:100%; border-collapse:collapse; margin-bottom:8px">
    <tr>
        <td style="width:70px; vertical-align:middle">
            @if ($seal)<img src="{{ $seal }}" alt="MMSU seal" style="width:60px; height:60px">@endif
        </td>
        <td style="text-align:center; vertical-align:middle">
            <div style="font-size:9pt">Republic of the Philippines</div>
            <div style="font-weight:bold; font-size:12pt">MARIANO MARCOS STATE UNIVERSITY</div>
            <div style="font-size:9pt">Batac City, Ilocos Norte</div>
            <div style="font-weight:bold; font-size:12pt; margin-top:6px">INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)</div>
        </td>
        <td style="width:70px"></td>
    </tr>
</table>

<p style="font-size:10pt; margin:8px 0">
    I, <strong>{{ $ipcr->user->name }}</strong>, {{ $ipcr->user->position ?? 'employee' }} of {{ $ipcr->user->unitLabel() }},
    {{ $ipcr->template->commitmentClause() }} <strong>{{ $ipcr->template->commitment_period }}</strong>.
</p>

<table style="width:100%; border-collapse:collapse; font-size:9pt; margin-bottom:8px">
    <tr>
        <td style="width:50%; vertical-align:top">
            <strong>Ratee:</strong> {{ $ipcr->user->name }}@if ($ipcr->user->employee_id) ({{ $ipcr->user->employee_id }})@endif<br>
            <strong>Date submitted:</strong> {{ $signoffs['ratee']?->created_at->format('F j, Y') ?? '—' }}
        </td>
        <td style="width:50%; vertical-align:top">
            <strong>Rating scale:</strong>
            @foreach ($ipcr->template->scale() as $value => $label) {{ $value }} – {{ $label }}@if (! $loop->last); @endif @endforeach
        </td>
    </tr>
</table>

<table class="print-table" style="width:100%; border-collapse:collapse; font-size:8.5pt">
    <thead>
    <tr>
        @foreach (['Program / outcome' => '14%', 'Performance indicator' => '20%', 'Target' => '18%', 'Actual accomplishment' => '26%', 'Rating' => '7%', 'Remarks' => '15%'] as $head => $width)
            <th style="width:{{ $width }}; border:1px solid #555; padding:4px; background:#e9efe9; text-align:center">{{ $head }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach ($ipcr->itemsBySection() as $section => $items)
        <tr><td colspan="6" style="border:1px solid #555; padding:4px; background:#104D03; color:#fff; font-weight:bold">{{ \App\Models\Ipcr::SECTIONS[$section] }}</td></tr>
        @foreach ($items as $item)
            <tr>
                <td style="border:1px solid #555; padding:4px; vertical-align:top">{{ $item->program_outcome }}</td>
                <td style="border:1px solid #555; padding:4px; vertical-align:top">{{ $item->indicator }}</td>
                <td style="border:1px solid #555; padding:4px; vertical-align:top">{{ $item->target }}</td>
                <td style="border:1px solid #555; padding:4px; vertical-align:top">{{ $item->actual_accomplishment }}</td>
                <td style="border:1px solid #555; padding:4px; vertical-align:top; text-align:center">{{ $item->rating }}</td>
                <td style="border:1px solid #555; padding:4px; vertical-align:top">{{ $item->remarks }}</td>
            </tr>
        @endforeach
        @if (isset($ipcr->section_averages[$section]))
            <tr>
                <td colspan="4" style="border:1px solid #555; padding:4px; text-align:right; font-weight:bold">{{ \App\Models\Ipcr::SECTIONS[$section] }} average</td>
                <td style="border:1px solid #555; padding:4px; text-align:center; font-weight:bold">{{ number_format((float) $ipcr->section_averages[$section], 2) }}</td>
                <td style="border:1px solid #555; padding:4px"></td>
            </tr>
        @endif
    @endforeach
    <tr>
        <td colspan="4" style="border:1px solid #555; padding:4px; text-align:right; font-weight:bold">Final rating</td>
        <td style="border:1px solid #555; padding:4px; text-align:center; font-weight:bold">{{ $ipcr->final_rating !== null ? number_format((float) $ipcr->final_rating, 2) : '' }}</td>
        <td style="border:1px solid #555; padding:4px; font-weight:bold">{{ $ipcr->adjectival_rating }}</td>
    </tr>
    </tbody>
</table>

@if ($ipcr->rating_basis)
    <p style="font-size:8pt; color:#555; margin:4px 0">Final rating: {{ \App\Services\FinalRating::describeMethod($ipcr->rating_basis) }}</p>
@endif

<table style="width:100%; border-collapse:collapse; margin-top:28px; font-size:9pt; page-break-inside:avoid">
    <tr>
        @foreach (\App\Models\IpcrTemplate::SIGNATORY_FIELDS as $key => $fallback)
            @php($review = $signoffs[$key])
            <td style="width:25%; padding:0 10px; text-align:center; vertical-align:bottom">
                <div style="font-weight:bold; border-bottom:1px solid #28362B; padding-bottom:2px; min-height:14px">
                    {{ $key === 'ratee' ? $ipcr->user->name : ($review?->user?->name ?? '') }}
                </div>
                <div>{{ $ipcr->template->signatory($key) }}</div>
                <div style="color:#555">Date: {{ $review?->created_at?->format('M j, Y') ?? '__________' }}</div>
            </td>
        @endforeach
    </tr>
</table>
