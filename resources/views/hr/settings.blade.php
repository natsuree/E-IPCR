@extends('layouts.app')
@section('title', 'Rating Settings')
@section('subtitle', 'How the final rating is computed when HR gives final approval. Completed IPCRs keep the rating they were given.')

@section('content')
    <div class="notice-returned mb-4 small">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>These settings are not yet confirmed by MMSU HR.</strong> Update them once the official computation, section weights and adjectival ranges are approved. Every change is recorded below.
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <x-card title="Final rating computation">
                <form method="POST" action="{{ route('hr.settings.update') }}" novalidate>
                    @csrf
                    @method('PUT')

                    <fieldset class="mb-4">
                        <legend class="fs-6 fw-semibold">Method</legend>
                        @foreach (\App\Services\FinalRating::METHODS as $value => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="method" id="method-{{ $value }}" value="{{ $value }}" @checked(old('method', $settings['method']) === $value)>
                                <label class="form-check-label" for="method-{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        @error('method')<div class="text-danger small">{{ $message }}</div>@enderror
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="fs-6 fw-semibold">Section weights (%)</legend>
                        <p class="small text-secondary">Used only with the weighted method. They must add up to 100.</p>
                        <div class="row g-2">
                            @foreach (\App\Models\Ipcr::SECTIONS as $key => $label)
                                <div class="col-md-4">
                                    <label for="weight-{{ $key }}" class="form-label small">{{ $label }}</label>
                                    <div class="input-group">
                                        <input id="weight-{{ $key }}" name="weights[{{ $key }}]" type="number" step="0.01" min="0" max="100"
                                               class="form-control @error('weights.'.$key) is-invalid @enderror" value="{{ old('weights.'.$key, $settings['weights'][$key]) }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    @error('weights.'.$key)<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                        @error('weights')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </fieldset>

                    <fieldset class="mb-3">
                        <legend class="fs-6 fw-semibold">Adjectival ranges</legend>
                        <p class="small text-secondary">The lowest final rating for each adjectival rating. Anything below the last one is "Poor".</p>
                        @foreach (array_keys(\App\Services\FinalRating::DEFAULT_BANDS) as $i => $label)
                            <div class="input-group mb-2" style="max-width: 360px">
                                <span class="input-group-text" style="width: 11rem">{{ $label }} from</span>
                                <input name="bands[{{ $i }}]" type="number" step="0.01" min="1" max="5" aria-label="Lowest rating for {{ $label }}"
                                       class="form-control @error('bands.'.$i) is-invalid @enderror" value="{{ old('bands.'.$i, number_format((float) $settings['bands'][$label], 2)) }}">
                            </div>
                            @error('bands.'.$i)<div class="text-danger small">{{ $message }}</div>@enderror
                        @endforeach
                        @error('bands')<div class="text-danger small">{{ $message }}</div>@enderror
                    </fieldset>

                    <div class="text-end"><button class="btn btn-primary">Save rating settings</button></div>
                </form>
            </x-card>
        </div>
        <div class="col-xl-5">
            <x-card title="In effect now">
                <p class="mb-2">{{ \App\Services\FinalRating::describeMethod($settings) }}</p>
                <p class="small mb-2">{{ \App\Services\FinalRating::describeBands($settings['bands']) }}</p>
                <p class="small text-secondary mb-0">
                    Last changed {{ $saved?->updated_at?->format('M j, Y g:i A') ?? 'never' }}@if ($saved?->updatedBy) by {{ $saved->updatedBy->name }}@endif.
                </p>
            </x-card>
            <x-card title="Change history" flush>
                @if ($history->isEmpty())
                    <div class="empty-state"><i class="bi bi-clock-history"></i>No changes yet. The starting settings are in use.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($history as $entry)
                            <li class="list-group-item small">
                                <div class="fw-semibold">{{ $entry->created_at->format('M j, Y g:i A') }} · {{ $entry->user?->name ?? 'System' }}</div>
                                <div>{{ $entry->description }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
            {{ $history->links() }}
        </div>
    </div>
@endsection
