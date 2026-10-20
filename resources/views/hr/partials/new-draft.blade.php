{{-- Start a new draft version. Params: $source (template to copy by default, or null), $compact (optional). --}}
<form method="POST" action="{{ route('hr.templates.store') }}" class="d-flex flex-wrap gap-2 align-items-center">
    @csrf
    <input type="hidden" name="period" value="{{ $period->id }}">
    <input type="hidden" name="type" value="{{ $type }}">
    <label for="copy_from" class="small text-secondary text-nowrap">Copy from</label>
    <select id="copy_from" name="copy_from" class="form-select form-select-sm" style="max-width: 320px">
        @unless ($source)<option value="">Start empty</option>@endunless
        @foreach ($copySources as $option)
            <option value="{{ $option->id }}" @selected($source ? $option->id === $source->id : ($option->employee_type === $type && $loop->first))>
                {{ $option->period->name }} · {{ $option->employeeTypeLabel() }} · Version {{ $option->version }}
            </option>
        @endforeach
    </select>
    <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Start new draft (Version {{ ($versions->max('version') ?? 0) + 1 }})</button>
</form>
