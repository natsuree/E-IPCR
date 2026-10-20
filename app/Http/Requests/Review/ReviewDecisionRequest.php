<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('ipcr'));
    }

    public function isApproval(): bool
    {
        return $this->input('action') === 'approve';
    }

    public function rules(): array
    {
        $approve = $this->isApproval();
        $rules = [
            'action' => ['required', Rule::in(['approve', 'return'])],
            'remarks' => [$approve ? 'nullable' : 'required', 'string', 'max:2000'],
            'items' => ['array'],
        ];

        foreach ($this->route('ipcr')->items as $item) {
            $rules["items.{$item->id}.rating"] = [$approve ? 'required' : 'nullable', 'integer', 'between:1,5'];
            $rules["items.{$item->id}.remarks"] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'remarks.required' => 'Write remarks that tell the employee what to correct, then return the IPCR.',
            'items.*.rating.required' => 'Give a rating from 1 to 5 for :attribute before approving.',
        ];
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach ($this->route('ipcr')->items as $n => $item) {
            $attributes["items.{$item->id}.rating"] = 'indicator '.($n + 1);
            $attributes["items.{$item->id}.remarks"] = 'the remarks for indicator '.($n + 1);
        }

        return $attributes;
    }

    /** @return array<int, array{rating: ?int, remarks: ?string}> */
    public function ratings(): array
    {
        return $this->validated('items') ?? [];
    }
}
