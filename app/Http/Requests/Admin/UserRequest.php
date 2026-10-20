<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or edit an account. Passwords are never set here: users set their own through the emailed link. */
class UserRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_id')->ignore($user?->id)],
            'role' => ['required', Rule::enum(Role::class)],
            'position' => ['nullable', 'string', 'max:255'],
            'college_id' => [
                Rule::requiredIf(fn () => $this->input('role') !== Role::Admin->value),
                'nullable', 'integer',
                Rule::exists('colleges', 'id')->where('active', true),
            ],
            'department_id' => [
                Rule::requiredIf(fn () => $this->input('role') === Role::DepartmentHead->value),
                'nullable', 'integer',
                Rule::exists('departments', 'id')->where('college_id', $this->integer('college_id'))->where('active', true),
            ],
            'active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.exists' => 'Choose an active department that belongs to the selected college/office.',
            'college_id.required' => 'Choose the college or office for this account.',
            'college_id.exists' => 'Choose an active college or office.',
            'department_id.required' => 'A Department Head needs a department.',
            'email.unique' => 'Another account already uses this email.',
            'employee_id.unique' => 'Another account already uses this employee ID.',
        ];
    }

    public function attributes(): array
    {
        return ['college_id' => 'college/office', 'department_id' => 'department', 'employee_id' => 'employee ID'];
    }

    public function userData(): array
    {
        $data = $this->validated();
        $data['active'] = $this->boolean('active');
        $data['employee_type'] = Role::from($data['role'])->employeeType();

        return $data;
    }
}
