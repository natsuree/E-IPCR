<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'college' => ['nullable', 'integer', 'exists:colleges,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
