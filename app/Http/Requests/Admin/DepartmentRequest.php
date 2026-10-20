<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('departments', 'name')
                    ->where('college_id', $this->route('college')->id)
                    ->ignore($this->route('department')),
            ],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'This college already has a department with this name.'];
    }
}
