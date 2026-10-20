<div class="mb-3">
    <label for="name" class="form-label fw-semibold">Name</label>
    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $college?->name) }}" placeholder="College of Engineering">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="row g-2 mb-3">
    <div class="col-5">
        <label for="code" class="form-label fw-semibold">Code</label>
        <input id="code" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $college?->code) }}" placeholder="COE">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-7">
        <label for="kind" class="form-label fw-semibold">Type</label>
        <select id="kind" name="kind" class="form-select @error('kind') is-invalid @enderror">
            <option value="college" @selected(old('kind', $college?->kind) === 'college')>College</option>
            <option value="office" @selected(old('kind', $college?->kind) === 'office')>Office</option>
        </select>
        @error('kind')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
