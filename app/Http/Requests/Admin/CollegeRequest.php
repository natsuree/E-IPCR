<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollegeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('colleges', 'name')->ignore($this->route('college'))],
            'code' => ['nullable', 'string', 'max:20'],
            'kind' => ['required', Rule::in(['college', 'office'])],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'A college or office with this name already exists.'];
    }
}
