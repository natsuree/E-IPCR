@extends('layouts.app')
@section('title', 'IPCR Templates')
@section('subtitle', 'Set the IPCR content for each rating period and employee type. Published versions are what new IPCRs use.')

@php
    $editable = $template?->isDraft();
    $query = fn (array $extra = []) => array_merge(['period' => $period->id, 'type' => $type, 'version' => $template?->version], $extra);
@endphp

@section('actions')
    @if ($template && $editable)
        <form method="POST" action="{{ route('hr.templates.publish', $template) }}" onsubmit="return confirm('Publish Version {{ $template->version }}? New IPCRs for {{ $period->name }} will use it. IPCRs already started keep their version.')">
            @csrf
            <button class="btn btn-gold"><i class="bi bi-megaphone"></i> Publish template</button>
        </form>
    @endif
@endsection

@section('content')
    <x-card title="Filters">
        <form method="GET" action="{{ route('hr.templates') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="period" class="form-label small fw-semibold">Rating period</label>
                <select id="period" name="period" class="form-select">
                    @foreach ($periods as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $period->id)>{{ $option->name }}{{ $option->is_current ? ' (current)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="type" class="form-label small fw-semibold">Employee type</label>
                <select id="type" name="type" class="form-select">
                    <option value="teaching" @selected($type === 'teaching')>Teaching</option>
                    <option value="nonteaching" @selected($type === 'nonteaching')>Non-teaching</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="version" class="form-label small fw-semibold">Version · copied from</label>
                <select id="version" name="version" class="form-select" @disabled($versions->isEmpty())>
                    <option value="">Latest version</option>
                    @foreach ($versions as $v)
                        <option value="{{ $v->version }}" @selected(request('version') == $v->version)>
                            {{ $v->versionLabel() }} · {{ $v->copiedFrom ? 'copied from '.($v->copiedFrom->rating_period_id === $v->rating_period_id ? 'Version '.$v->copiedFrom->version : $v->copiedFrom->period->name.' v'.$v->copiedFrom->version) : 'original' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Show</button></div>
        </form>
    </x-card>

    @if (! $template)
        <x-card title="No template yet">
            <p>There is no {{ $type === 'teaching' ? 'Teaching' : 'Non-teaching' }} template for {{ $period->name }}. Start a draft, optionally copied from an earlier version.</p>
            @include('hr.partials.new-draft', ['source' => null])
        </x-card>
    @else
        <div class="notice-soft d-flex flex-wrap align-items-center gap-3 mb-4">
            <div>
                <strong>{{ $template->employeeTypeLabel() }} · {{ $period->name }} · {{ $template->versionLabel() }}</strong>
                @if ($active?->is($template))<span class="badge bg-success ms-1">In use for new IPCRs</span>@endif
                <div class="small text-secondary">
                    {{ $template->copiedFrom ? 'Copied from '.app(\App\Services\TemplateManager::class)->describe($template->copiedFrom).'.' : 'Original version.' }}
                    {{ $template->published_at ? 'Published '.$template->published_at->format('M j, Y g:i A').'.' : 'Not published yet.' }}
                    Used by {{ $usage[$template->id] ?? 0 }} IPCR(s), which keep this version.
                </div>
            </div>
            <div class="ms-auto">
                @if ($editable)
                    <span class="small"><i class="bi bi-pencil"></i> Draft: edit below, then publish.</span>
                @elseif (! $hasDraft)
                    @include('hr.partials.new-draft', ['source' => $template, 'compact' => true])
                @else
                    <span class="small text-secondary">A draft version exists. Choose it in the Version filter to edit it.</span>
                @endif
            </div>
        </div>

        <x-card title="Form details">
            <form method="POST" action="{{ route('hr.templates.update', $template) }}">
                @csrf
                @method('PUT')
                <fieldset @disabled(! $editable)>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="commitment_period" class="form-label small fw-semibold">Commitment period</label>
                            <input id="commitment_period" name="commitment_period" class="form-control @error('commitment_period') is-invalid @enderror" value="{{ old('commitment_period', $template->commitment_period) }}">
                            @error('commitment_period')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label for="commitment_statement" class="form-label small fw-semibold">Commitment statement</label>
                            <textarea id="commitment_statement" name="commitment_statement" rows="2" class="form-control @error('commitment_statement') is-invalid @enderror">{{ old('commitment_statement', $template->commitment_statement) }}</textarea>
                            @error('commitment_statement')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <div class="form-label small fw-semibold">Rating scale</div>
                            @foreach ($template->scale() as $value => $label)
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text" style="width:2.5rem">{{ $value }}</span>
                                    <input name="rating_scale[{{ $value }}]" aria-label="Label for rating {{ $value }}" class="form-control @error('rating_scale.'.$value) is-invalid @enderror" value="{{ old('rating_scale.'.$value, $label) }}">
                                </div>
                            @endforeach
                        </div>
                        <div class="col-lg-6">
                            <div class="form-label small fw-semibold">Signatory labels</div>
                            @foreach (\App\Models\IpcrTemplate::SIGNATORY_FIELDS as $key => $name)
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text" style="width:8rem">{{ $name }}</span>
                                    <input name="signatories[{{ $key }}]" aria-label="{{ $name }} label" class="form-control @error('signatories.'.$key) is-invalid @enderror" value="{{ old('signatories.'.$key, $template->signatory($key)) }}">
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($editable)
                        <div class="text-end mt-3"><button class="btn btn-primary">Save form details</button></div>
                    @endif
                </fieldset>
            </form>
        </x-card>

        @foreach (\App\Models\Ipcr::SECTIONS as $section => $sectionLabel)
            @php($items = $template->items->where('section', $section)->values())
            <x-card :title="$sectionLabel" :id="$section" flush>
                @if ($editable)
                    <x-slot:actions>
                        @if ($section === 'strategic')
                            <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#add-po-{{ $section }}">+ Add program / outcome</button>
                        @endif
                        <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#add-{{ $section }}">+ Add indicator</button>
                    </x-slot:actions>
                @endif
                <div class="table-responsive">
                    <table class="table table-eipcr align-top">
                        <thead><tr><th style="width:20%">Program / outcome</th><th style="width:35%">Performance indicator</th><th style="width:32%">Target</th>@if ($editable)<th class="text-end" style="width:13%">Edit · Remove</th>@endif</tr></thead>
                        <tbody>
                        @forelse ($items as $item)
                            @if ($editable && (int) $editing === $item->id)
                                <tr>
                                    <td><input form="edit-{{ $item->id }}" name="program_outcome" class="form-control form-control-sm @error('program_outcome') is-invalid @enderror" value="{{ old('program_outcome', $item->program_outcome) }}" aria-label="Program / outcome"></td>
                                    <td><textarea form="edit-{{ $item->id }}" name="indicator" rows="3" class="form-control form-control-sm @error('indicator') is-invalid @enderror" aria-label="Performance indicator">{{ old('indicator', $item->indicator) }}</textarea></td>
                                    <td><textarea form="edit-{{ $item->id }}" name="target" rows="3" class="form-control form-control-sm @error('target') is-invalid @enderror" aria-label="Target">{{ old('target', $item->target) }}</textarea></td>
                                    <td class="text-end text-nowrap">
                                        <form id="edit-{{ $item->id }}" method="POST" action="{{ route('hr.templates.items.update', [$template, $item]) }}" class="d-inline">
                                            @csrf @method('PUT')
                                            <button class="btn btn-sm btn-primary">Save</button>
                                        </form>
                                        <a href="{{ route('hr.templates', $query()) }}#{{ $section }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <td>{{ $item->program_outcome ?? '—' }}</td>
                                    <td>{{ $item->indicator }}</td>
                                    <td>{{ $item->target }}</td>
                                    @if ($editable)
                                        <td class="text-end text-nowrap">
                                            <a href="{{ route('hr.templates', $query(['edit' => $item->id])) }}#{{ $section }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="POST" action="{{ route('hr.templates.items.destroy', [$template, $item]) }}" class="d-inline" onsubmit="return confirm('Remove this indicator from the draft?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger">Remove</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="4" class="text-secondary">No indicators in this section yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($editable)
                    @php($openAdd = old('section') === $section)
                    @if ($section === 'strategic')
                        <div class="collapse {{ $openAdd && old('new_outcome') ? 'show' : '' }}" id="add-po-{{ $section }}">
                            @include('hr.partials.add-item', ['newOutcome' => true])
                        </div>
                    @endif
                    <div class="collapse {{ $openAdd && ! old('new_outcome') ? 'show' : '' }}" id="add-{{ $section }}">
                        @include('hr.partials.add-item', ['newOutcome' => false])
                    </div>
                @endif
            </x-card>
        @endforeach
    @endif

    <x-card title="Change log" flush id="change-log">
        <div class="table-responsive">
            <table class="table table-eipcr">
                <thead><tr><th>Date</th><th>Who</th><th>Version</th><th>What changed</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                        <td>{{ $log->user?->name ?? 'Former account' }}</td>
                        <td>Version {{ $log->template->version }}</td>
                        <td>{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-secondary">No changes recorded for this period and employee type.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    {{ $logs->fragment('change-log')->links() }}
@endsection
