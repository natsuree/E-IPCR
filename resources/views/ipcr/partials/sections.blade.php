{{--
    The three function tables.
    $mode: 'employee' (Actual Accomplishment editable), 'review' (Rating and Remarks editable) or 'readonly'.
    Inputs are named items[<id>][field]; the surrounding view supplies the <form>.
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
                    <tr>
                        <td class="text-secondary">{{ $n }}</td>
                        <td>{{ $item->program_outcome ?? '—' }}</td>
                        <td>{{ $item->indicator }}</td>
                        <td>{{ $item->target }}</td>
                        <td>
                            @if ($mode === 'employee')
                                <label class="visually-hidden" for="actual-{{ $item->id }}">Actual accomplishment for {{ $item->indicator }}</label>
                                <textarea id="actual-{{ $item->id }}" name="items[{{ $item->id }}][actual_accomplishment]" rows="3"
                                          @class(['form-control', 'is-invalid' => $errors->has("items.{$item->id}.actual_accomplishment")])
                                          placeholder="Describe what you accomplished for this indicator">{{ old("items.{$item->id}.actual_accomplishment", $item->actual_accomplishment) }}</textarea>
                                @error("items.{$item->id}.actual_accomplishment")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @else
                                {{ $item->actual_accomplishment ?: '—' }}
                            @endif
                        </td>
                        <td>
                            @if ($mode === 'review')
                                <label class="visually-hidden" for="rating-{{ $item->id }}">Rating for {{ $item->indicator }}</label>
                                <select id="rating-{{ $item->id }}" name="items[{{ $item->id }}][rating]" data-rating
                                        @class(['form-select form-select-sm', 'is-invalid' => $errors->has("items.{$item->id}.rating")])>
                                    <option value="">Rate</option>
                                    @foreach ($scale as $value => $label)
                                        <option value="{{ $value }}" @selected((string) old("items.{$item->id}.rating", $item->rating) === (string) $value)>{{ $value }} – {{ $label }}</option>
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
                        <td>
                            @if ($mode === 'review')
                                <label class="visually-hidden" for="remarks-{{ $item->id }}">Remarks for {{ $item->indicator }}</label>
                                <textarea id="remarks-{{ $item->id }}" name="items[{{ $item->id }}][remarks]" rows="2" class="form-control form-control-sm" style="min-width:150px">{{ old("items.{$item->id}.remarks", $item->remarks) }}</textarea>
                            @else
                                {{ $item->remarks ?: '—' }}
                            @endif
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
