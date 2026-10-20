<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'integer', 'exists:rating_periods,id'],
            'type' => ['nullable', Rule::in(['teaching', 'nonteaching'])],
            'version' => ['nullable', 'integer', 'min:1'],
            'edit' => ['nullable', 'integer'],
        ];
    }
}
