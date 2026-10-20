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
        return [
            'action' => ['required', Rule::in(['save', 'print', 'submit'])],
            'items' => ['array'],
            'items.*' => ['array:actual_accomplishment'],
            'items.*.actual_accomplishment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['items.*.actual_accomplishment' => 'actual accomplishment'];
    }
}
