{{-- Add-indicator row. Params: $template, $section, $newOutcome (true = type a new program/outcome), $programOutcomes. --}}
<form method="POST" action="{{ route('hr.templates.items.store', $template) }}" class="row g-2 p-3 border-top" style="background: var(--eipcr-soft)">
    @csrf
    <input type="hidden" name="section" value="{{ $section }}">
    @if ($newOutcome)<input type="hidden" name="new_outcome" value="1">@endif
    <div class="col-md-3">
        <label class="form-label small fw-semibold" for="po-{{ $section }}-{{ $newOutcome ? 'new' : 'add' }}">{{ $newOutcome ? 'New program / outcome' : 'Program / outcome' }}</label>
        @if ($section === 'strategic' && ! $newOutcome && $programOutcomes->isNotEmpty())
            <select id="po-{{ $section }}-add" name="program_outcome" class="form-select form-select-sm">
                @foreach ($programOutcomes as $outcome)
                    <option @selected(old('program_outcome') === $outcome)>{{ $outcome }}</option>
                @endforeach
            </select>
        @else
            <input id="po-{{ $section }}-{{ $newOutcome ? 'new' : 'add' }}" name="program_outcome" class="form-control form-control-sm @if (old('section') === $section) @error('program_outcome') is-invalid @enderror @endif"
                   value="{{ old('section') === $section ? old('program_outcome') : '' }}" @if ($section === 'strategic') required @endif>
        @endif
        @if (old('section') === $section) @error('program_outcome')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold" for="ind-{{ $section }}-{{ $newOutcome ? 'new' : 'add' }}">Performance indicator</label>
        <textarea id="ind-{{ $section }}-{{ $newOutcome ? 'new' : 'add' }}" name="indicator" rows="2" required class="form-control form-control-sm @if (old('section') === $section) @error('indicator') is-invalid @enderror @endif">{{ old('section') === $section ? old('indicator') : '' }}</textarea>
        @if (old('section') === $section) @error('indicator')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold" for="tgt-{{ $section }}-{{ $newOutcome ? 'new' : 'add' }}">Target</label>
        <textarea id="tgt-{{ $section }}-{{ $newOutcome ? 'new' : 'add' }}" name="target" rows="2" required class="form-control form-control-sm @if (old('section') === $section) @error('target') is-invalid @enderror @endif">{{ old('section') === $section ? old('target') : '' }}</textarea>
        @if (old('section') === $section) @error('target')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
    </div>
    <div class="col-md-1 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100">Add</button></div>
</form>
