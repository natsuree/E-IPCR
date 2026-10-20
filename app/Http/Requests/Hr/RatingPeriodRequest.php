<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RatingPeriodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('rating_periods', 'name')->ignore($this->route('period'))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'submission_deadline' => ['required', 'date', 'after_or_equal:starts_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'ends_on.after' => 'The end date must be after the start date.',
            'submission_deadline.after_or_equal' => 'The submission deadline cannot be before the start date.',
            'name.unique' => 'A rating period with this name already exists. Use a different name, for example "January – June 2027".',
        ];
    }

    public function attributes(): array
    {
        return ['starts_on' => 'start date', 'ends_on' => 'end date', 'submission_deadline' => 'submission deadline'];
    }
}
