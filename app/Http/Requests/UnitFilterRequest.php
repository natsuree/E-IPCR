<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Search and unit filters shared by the review queue, records and reports pages. */
class UnitFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'integer', 'exists:rating_periods,id'],
            'college' => ['nullable', 'integer', 'exists:colleges,id'],
            'department' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }

    public function filters(): array
    {
        return array_filter($this->validated(), fn ($v) => $v !== null && $v !== '');
    }
}
