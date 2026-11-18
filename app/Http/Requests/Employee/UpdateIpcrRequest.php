<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIpcrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ipcr'));
    }

    public function rules(): array
    {
        $adding = $this->input('action') === 'add_target'
            || $this->anyFilled(['new_target.indicator', 'new_target.target', 'new_target.program_outcome']);
        $required = Rule::requiredIf($adding);

        return [
            'new_target' => ['sometimes', 'array:key,section,program_outcome,indicator,target'],
            'new_target.key' => [$required, 'nullable', 'uuid'],
            'new_target.section' => [$required, 'nullable', Rule::in(array_keys(\App\Models\Ipcr::SECTIONS))],
            'new_target.program_outcome' => ['nullable', 'string', 'max:255'],
            'new_target.indicator' => [$required, 'nullable', 'string', 'max:2000'],
            'new_target.target' => [$required, 'nullable', 'string', 'max:2000'],
            'personal' => ['sometimes', 'array'],
            'personal.*' => ['array:indicator,target,program_outcome'],
            'personal.*.indicator' => ['required', 'string', 'max:2000'],
            'personal.*.target' => ['required', 'string', 'max:2000'],
            'personal.*.program_outcome' => ['nullable', 'string', 'max:255'],
            'action' => ['required', Rule::in(['save', 'print', 'submit', 'add_target'])],
            'items' => ['array'],
            'items.*' => ['array:actual_accomplishment,evidence_url'],
            'items.*.evidence_url' => ['nullable', 'url:http,https', 'max:2048'],
            'items.*.actual_accomplishment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['items.*.actual_accomplishment' => 'actual accomplishment'];
    }
}
