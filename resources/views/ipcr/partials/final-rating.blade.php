{{-- Section averages and final rating. Expects $ipcr; optional $result (a preview from FinalRating::compute). --}}
@php
    $result ??= null;
    $sections = $result['sections'] ?? $ipcr->section_averages ?? [];
    $overall = $result['overall'] ?? $ipcr->final_rating;
    $adjectival = $result['adjectival'] ?? $ipcr->adjectival_rating;
    $basis = $result['basis'] ?? $ipcr->rating_basis ?? \App\Services\FinalRating::settings();
@endphp
<x-card :title="$result ? 'Rating summary (preview)' : 'Final rating'">
    <div class="row g-3">
        @foreach (\App\Models\Ipcr::SECTIONS as $key => $label)
            <div class="col-6 col-md-3">
                <x-stat :label="$label.' average'" :value="isset($sections[$key]) ? number_format((float) $sections[$key], 2) : '—'" />
            </div>
        @endforeach
        <div class="col-6 col-md-3">
            <x-stat label="Final rating" :value="$overall !== null ? number_format((float) $overall, 2) : '—'" :hint="$adjectival ?? 'Not yet computed'" />
        </div>
    </div>
    <p class="small text-secondary mb-0 mt-3">
        <i class="bi bi-info-circle"></i>
        {{ $result ? 'Computed with the current rating settings' : 'Computed with the rating settings in effect when HR approved it' }}:
        {{ \App\Services\FinalRating::describeMethod($basis) }}
        {{ \App\Services\FinalRating::describeBands($basis['bands']) }}.
    </p>
</x-card>
