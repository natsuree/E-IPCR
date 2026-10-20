<?php

namespace App\Http\Requests\Hr;

use App\Models\IpcrTemplate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateDetailsRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'commitment_period' => ['required', 'string', 'max:100'],
            'commitment_statement' => ['required', 'string', 'max:1000'],
            'rating_scale' => ['required', 'array'],
            'signatories' => ['required', 'array'],
        ];
        foreach (array_keys(IpcrTemplate::DEFAULT_SCALE) as $value) {
            $rules["rating_scale.{$value}"] = ['required', 'string', 'max:40'];
        }
        foreach (array_keys(IpcrTemplate::SIGNATORY_FIELDS) as $key) {
            $rules["signatories.{$key}"] = ['required', 'string', 'max:80'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach (array_keys(IpcrTemplate::DEFAULT_SCALE) as $value) {
            $attributes["rating_scale.{$value}"] = "label for rating {$value}";
        }
        foreach (IpcrTemplate::SIGNATORY_FIELDS as $key => $label) {
            $attributes["signatories.{$key}"] = "\"{$label}\" label";
        }

        return $attributes;
    }

    public function details(): array
    {
        $data = $this->validated();
        $data['rating_scale'] = array_intersect_key($data['rating_scale'], IpcrTemplate::DEFAULT_SCALE);
        $data['signatories'] = array_intersect_key($data['signatories'], IpcrTemplate::SIGNATORY_FIELDS);

        return $data;
    }
}
