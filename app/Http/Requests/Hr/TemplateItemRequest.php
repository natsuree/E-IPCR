<?php

namespace App\Http\Requests\Hr;

use App\Models\Ipcr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add (POST, section required) or edit (PUT, section taken from the item) a template indicator. */
class TemplateItemRequest extends FormRequest
{
    public function section(): ?string
    {
        return $this->route('item')?->section ?? $this->input('section');
    }

    public function rules(): array
    {
        return [
            'section' => [$this->route('item') ? 'nullable' : 'required', Rule::in(array_keys(Ipcr::SECTIONS))],
            'program_outcome' => [$this->section() === 'strategic' ? 'required' : 'nullable', 'string', 'max:255'],
            'indicator' => ['required', 'string', 'max:1000'],
            'target' => ['required', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['program_outcome' => 'program/outcome', 'indicator' => 'performance indicator'];
    }
}
