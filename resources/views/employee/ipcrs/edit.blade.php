@extends('layouts.app')
@section('title', 'Create / Edit IPCR')
@section('subtitle', 'Enter your actual accomplishment for each indicator, save your draft, then submit.')

@section('actions')
    <x-status :status="$ipcr->status" class="fs-6" />
@endsection

@section('content')
    @if ($returned)
        <div class="notice-returned mb-4">
            <div class="fw-semibold mb-1"><i class="bi bi-arrow-return-left"></i> Returned by {{ $returned->user?->name }} ({{ $returned->role->label() }}) on {{ $returned->created_at->format('M j, Y') }}</div>
            <div style="white-space: pre-line">{{ $returned->remarks }}</div>
        </div>
    @endif

    @include('ipcr.partials.header')

    <form method="POST" action="{{ route('employee.ipcrs.update', $ipcr) }}" id="ipcr-form">
        @csrf
        @method('PUT')

        @include('ipcr.partials.sections', ['mode' => 'employee'])

        @foreach (['retiredTargets' => ['Targets from an earlier template', 'HR published a newer template version. Your entries for these targets are kept for reference and excluded from the current rating.'],
                   'removedTargets' => ['Added targets you removed', 'These had already been seen by a reviewer, so they are kept in the record. They are excluded from the current rating.']] as $relation => [$title, $hint])
            @if ($ipcr->$relation->isNotEmpty())
                <x-card :title="$title">
                    <p class="small text-secondary">{{ $hint }}</p>
                    @foreach ($ipcr->$relation as $previous)
                        <p><strong>{{ $previous->indicator }}</strong> · {{ $previous->target }}<br>{{ $previous->actual_accomplishment }}
                            @if ($previous->evidence_url)<br><a href="{{ $previous->evidence_url }}" target="_blank" rel="noopener noreferrer">View MOV</a>@endif
                        </p>
                    @endforeach
                </x-card>
            @endif
        @endforeach
        <x-card title="Additional target">
            <p class="small text-secondary">Add a target to your own IPCR. HR sets the template targets.</p>
            @if ($errors->has('new_target.*') || $errors->has('personal.*'))
                <div class="alert alert-danger">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            @endif
            <input type="hidden" name="new_target[key]" value="{{ old('new_target.key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="row g-2">
                <label class="col-md-6">Function
                    <select class="form-select" name="new_target[section]">
                        @foreach (\App\Models\Ipcr::SECTIONS as $key => $label)
                            <option value="{{ $key }}" @selected(old('new_target.section') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="col-md-6">Program / outcome (optional)
                    <input class="form-control" name="new_target[program_outcome]" maxlength="255" value="{{ old('new_target.program_outcome') }}">
                </label>
                <label class="col-md-6">Performance indicator
                    <textarea class="form-control" name="new_target[indicator]" maxlength="2000">{{ old('new_target.indicator') }}</textarea>
                </label>
                <label class="col-md-6">Target
                    <textarea class="form-control" name="new_target[target]" maxlength="2000">{{ old('new_target.target') }}</textarea>
                </label>
            </div>
            <button class="btn btn-outline-primary mt-3" name="action" value="add_target">Add target</button>
        </x-card>

        <x-card title="Signatories">
            @include('ipcr.partials.signatories')
        </x-card>

        <div class="d-flex flex-wrap gap-2 justify-content-end mb-4">
            <button type="submit" name="action" value="save" class="btn btn-outline-primary"><i class="bi bi-save"></i> Save draft</button>
            <button type="submit" name="action" value="print" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print preview</button>
            <button type="submit" name="action" value="submit" class="btn btn-primary"><i class="bi bi-send"></i> Submit IPCR</button>
        </div>
    </form>

    {{-- One small form per added target; the Remove buttons in the tables point here (forms cannot be nested). --}}
    @foreach ($ipcr->items->whereNotNull('employee_target_key') as $added)
        <form method="POST" action="{{ route('employee.ipcrs.targets.destroy', [$ipcr, $added->id]) }}" id="remove-target-{{ $added->id }}"
              onsubmit="return confirm('Remove this added target? Unsaved changes on this page will be lost.')">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
@endsection
