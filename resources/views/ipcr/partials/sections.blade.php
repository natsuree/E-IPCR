{{--
    The three function tables.
    $mode: 'employee' (Actual Accomplishment and MOV link editable), 'review' (Rating and Remarks editable) or 'readonly'.
    Inputs are named items[<id>][field]; the surrounding view supplies the <form>.
    The MOV link sits below the remarks in each target's Remarks cell, in every mode.
    In employee mode, an added target's Remove button submits a separate form (remove-target-<id>) that the view renders outside the main form.
--}}
@php($scale = $ipcr->template->scale())
@foreach ($ipcr->itemsBySection() as $section => $items)
    <x-card :title="\App\Models\Ipcr::SECTIONS[$section]" :id="$section" flush>
        <div class="table-responsive">
            <table class="table table-eipcr table-bordered align-top">
                <thead>
                <tr>
                    <th style="width:3%">#</th>
                    <th style="width:14%">Program / outcome</th>
                    <th style="width:19%">Performance indicator</th>
                    <th style="width:18%">Target</th>
                    <th style="width:24%">Actual accomplishment</th>
                    <th style="width:9%">Rating</th>
                    <th style="width:13%">Remarks</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    @php($n = $loop->iteration)
                    @php($label = \App\Models\Ipcr::SECTIONS[$section].' indicator '.$n)
                    <tr>
                        <td class="text-secondary">{{ $n }}</td>
                        @if ($mode === 'employee' && $item->employee_target_key)
                            @foreach (['program_outcome' => 'Program / outcome', 'indicator' => 'Performance indicator', 'target' => 'Target'] as $field => $fieldLabel)
                                <td>
                                    <textarea @class(['form-control form-control-sm', 'is-invalid' => $errors->has("personal.{$item->id}.{$field}")]) aria-label="{{ $fieldLabel }} for {{ $label }} (added target)" name="personal[{{ $item->id }}][{{ $field }}]">{{ old("personal.{$item->id}.{$field}", $item->$field) }}</textarea>
                                    @if ($field === 'program_outcome')
                                        <span class="badge text-bg-light mt-1">Added by you</span>
                                        <button type="submit" form="remove-target-{{ $item->id }}" class="btn btn-link btn-sm text-danger p-0 d-block mt-1">
                                            <i class="bi bi-trash"></i> Remove target
                                        </button>
                                    @endif
                                </td>
                            @endforeach
                        @else
                            <td>{{ $item->program_outcome ?? '—' }}</td>
                            <td>{{ $item->indicator }}@if ($item->employee_target_key)<div><span class="badge text-bg-light">Added by employee</span></div>@endif</td>
                            <td>{{ $item->target }}</td>
                        @endif
                        <td>
                            @if ($mode === 'employee')
                                <label class="visually-hidden" for="actual-{{ $item->id }}">Actual accomplishment for {{ $label }}</label>
                                <textarea id="actual-{{ $item->id }}" name="items[{{ $item->id }}][actual_accomplishment]" rows="3"
                                          @class(['form-control', 'is-invalid' => $errors->has("items.{$item->id}.actual_accomplishment")])
                                          @if ($errors->has("items.{$item->id}.actual_accomplishment")) aria-invalid="true" aria-describedby="actual-error-{{ $item->id }}" @endif
                                          placeholder="Describe what you accomplished for this indicator">{{ old("items.{$item->id}.actual_accomplishment", $item->actual_accomplishment) }}</textarea>
                                @error("items.{$item->id}.actual_accomplishment")<div class="invalid-feedback" id="actual-error-{{ $item->id }}">{{ $message }}</div>@enderror
                            @else
                                {{ $item->actual_accomplishment ?: '—' }}
                            @endif
                        </td>
                        <td>
                            @if ($mode === 'review')
                                <label class="visually-hidden" for="rating-{{ $item->id }}">Rating for {{ $label }}</label>
                                <select id="rating-{{ $item->id }}" name="items[{{ $item->id }}][rating]" data-rating
                                        @class(['form-select form-select-sm', 'is-invalid' => $errors->has("items.{$item->id}.rating")])>
                                    <option value="">Rate</option>
                                    @foreach ($scale as $value => $scaleLabel)
                                        <option value="{{ $value }}" @selected((string) old("items.{$item->id}.rating", $item->rating) === (string) $value)>{{ $value }} – {{ $scaleLabel }}</option>
                                    @endforeach
                                </select>
                                @error("items.{$item->id}.rating")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @elseif ($item->rating)
                                <span class="fw-semibold">{{ $item->rating }}</span>
                                <div class="small text-secondary">{{ $scale[$item->rating] ?? '' }}</div>
                            @else
                                <span class="text-secondary">Not rated</span>
                            @endif
                        </td>
                        <td class="remarks-cell">
                            @if ($mode === 'review')
                                <label class="visually-hidden" for="remarks-{{ $item->id }}">Remarks for {{ $label }}</label>
                                <textarea id="remarks-{{ $item->id }}" name="items[{{ $item->id }}][remarks]" rows="2" class="form-control form-control-sm" style="min-width:150px">{{ old("items.{$item->id}.remarks", $item->remarks) }}</textarea>
                            @else
                                <div class="item-remarks">{{ $item->remarks ?: '—' }}</div>
                            @endif

                            {{-- MOV link: below the remarks. --}}
                            <div class="item-mov mt-2">
                                @if ($mode === 'employee')
                                    <label class="form-label small mb-1" for="mov-{{ $item->id }}">MOV link</label>
                                    <input type="url" @class(['form-control form-control-sm', 'is-invalid' => $errors->has("items.{$item->id}.evidence_url")]) id="mov-{{ $item->id }}" name="items[{{ $item->id }}][evidence_url]" placeholder="https://" value="{{ old("items.{$item->id}.evidence_url", $item->evidence_url) }}">
                                    @error("items.{$item->id}.evidence_url")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @elseif ($item->evidence_url)
                                    <a href="{{ $item->evidence_url }}" rel="noopener noreferrer" target="_blank" class="small"><i class="bi bi-link-45deg"></i> View MOV</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-secondary">This template has no indicators in this section.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endforeach
