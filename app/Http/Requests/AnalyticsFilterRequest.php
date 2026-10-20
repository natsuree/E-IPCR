<?php

namespace App\Http\Requests;

use App\Services\IpcrAnalytics;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters for the analytics and reports pages. Scope checks (403) happen in AnalyticsScope. */
class AnalyticsFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'integer', 'exists:rating_periods,id'],
            'type' => ['nullable', Rule::in(['teaching', 'nonteaching'])],
            'college' => ['nullable', 'integer'],
            'department' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([...array_keys(IpcrAnalytics::STATUS_LABELS), 'in_review', 'not_submitted'])],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(array_keys(IpcrAnalytics::SORTS))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function filters(): array
    {
        return array_filter($this->validated(), fn ($v) => $v !== null && $v !== '');
    }

    public function intOrNull(string $key): ?int
    {
        return $this->filled($key) ? (int) $this->validated($key) : null;
    }
}
