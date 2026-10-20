<?php

namespace App\Http\Requests\Hr;

use App\Models\Ipcr;
use App\Services\FinalRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RatingSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'method' => ['required', Rule::in(array_keys(FinalRating::METHODS))],
            'weights' => ['required', 'array'],
            'bands' => ['required', 'array'],
        ];
        foreach (array_keys(Ipcr::SECTIONS) as $section) {
            $rules["weights.{$section}"] = ['required', 'numeric', 'between:0,100'];
        }
        foreach (array_keys(FinalRating::DEFAULT_BANDS) as $i => $label) {
            $rules["bands.{$i}"] = ['required', 'numeric', 'between:1,5'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach (Ipcr::SECTIONS as $key => $label) {
            $attributes["weights.{$key}"] = "{$label} weight";
        }
        foreach (array_keys(FinalRating::DEFAULT_BANDS) as $i => $label) {
            $attributes["bands.{$i}"] = "lowest rating for {$label}";
        }

        return $attributes;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('method') === 'weighted') {
                $total = array_sum(array_map('floatval', $this->input('weights', [])));
                if (abs($total - 100) > 0.001) {
                    $validator->errors()->add('weights', "The section weights must add up to 100% (they add up to {$total}%).");
                }
            }

            $bands = array_map('floatval', array_values($this->input('bands', [])));
            for ($i = 1; $i < count($bands); $i++) {
                if ($bands[$i] >= $bands[$i - 1]) {
                    $validator->errors()->add('bands', 'Each adjectival rating must start lower than the one above it (Outstanding highest, Unsatisfactory lowest).');
                    break;
                }
            }
        }];
    }

    /** Validated settings in the stored shape. */
    public function settings(): array
    {
        $bands = [];
        foreach (array_keys(FinalRating::DEFAULT_BANDS) as $i => $label) {
            $bands[$label] = round((float) $this->validated("bands.{$i}"), 2);
        }

        return [
            'method' => $this->validated('method'),
            'weights' => array_map(fn ($w) => round((float) $w, 2), $this->validated('weights')),
            'bands' => $bands,
        ];
    }
}
