<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['required', 'integer', 'exists:rating_periods,id'],
            'type' => ['required', Rule::in(['teaching', 'nonteaching'])],
            'copy_from' => ['nullable', 'integer', Rule::exists('ipcr_templates', 'id')->whereNull('deleted_at')],
        ];
    }
}
